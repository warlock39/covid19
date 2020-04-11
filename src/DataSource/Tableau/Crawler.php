<?php /** @noinspection JsonEncodingApiUsageInspection */


namespace App\DataSource\Tableau;

use App\DataSource\Downloader\Downloader;
use Psr\Log\LoggerInterface;
use SplFileObject;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function json_decode;

class Crawler
{
    private LoggerInterface $logger;
    private HttpClientInterface $http;
    private Downloader $downloader;

    public function __construct(
        HttpClientInterface $http,
        Downloader $downloader,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->http = $http;
        $this->downloader = $downloader;
    }
    /**
     * @noinspection PhpRedundantCatchClauseInspection
     * @noinspection PhpUndefinedMethodInspection
     */
    public function grab(): string
    {
        $link1 = 'https://public.tableau.com/vizql/w/monitor_15841091301660/v/sheet0/vud/sessions/%s/views/7294020847002097674_2837170078324805029?csv=true&showall=true';
        $link2 = 'https://public.tableau.com/vizql/w/monitor_15841091301660/v/sheet0/viewData/sessions/%s/views/7294020847002097674_2837170078324805029?csv=true&showall=true';

        $download = fn($link) => $this->downloader->csv('tableau')->download(sprintf($link, $this->resolveSessionId()));

        try {
            $this->logger->info('Try download CSV from Link1');
            $result = $download($link1);
        } catch (Exception $e) {
            $this->logger->info('Try download CSV from Link2');
            $result = $download($link2);
        }

        return $result->filename;
    }
    public function readCsv(string $filename, MappingSet $mappingSet): iterable
    {
        $file = new SplFileObject($filename);
        $file->setFlags(SplFileObject::READ_CSV);

        $this->logger->info('Start reading of CSV file...');
        $headerSkipped = false;
        foreach ($file as $row) {
            if (!$headerSkipped) {
                $headerSkipped = true;
                continue;
            }
            if (!$mappingSet->satisfies($row)) {
                $this->logger->warning('CSV columns count not equals to expected. Probably input format changed');
                continue;
            }
            $newRow = [];
            foreach ($mappingSet->map() as $index => $key) {
                $newRow[$key] = $row[$index];
            }
            yield $newRow;
        }
    }
    /**
     * @throws Exception
     */
    private function resolveSessionId()
    {
        $this->logger->info('Multi-stage process of building download links started');
        $bootstrapSessionId = $this->bootstrapSessionId();

        $url = 'https://public.tableau.com/vizql/w/monitor_15841091301660/v/sheet0/bootstrapSession/sessions/%s';

        try {
            $response = $this->http->request('POST', sprintf($url, $bootstrapSessionId), [
                'headers' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Cookie: tableau_public_negotiated_locale=en-us',
                ],
                'body' => http_build_query(
                    ['sheet_id' => '%D0%9E%D0%BF%D0%B5%D1%80%D0%B0%D1%82%D0%B8%D0%B2%D0%BD%D0%B8%D0%B9%20%D0%BC%D0%BE%D0%BD%D1%96%D1%82%D0%BE%D1%80%D0%B8%D0%BD%D0%B3']
                ),
            ]);
            $content = $response->getContent();
        } catch (ClientException $e) {
            throw Exception::sessionIdResolveFailed($e->getMessage());
        }
        $content = substr($content, strpos($content, ';') + 1);

        preg_match_all('/}\d{4,7};{/', $content, $matches, PREG_OFFSET_CAPTURE);
        $content = substr($content, 0, ($matches[0][0][1] ?? 0) + 1);
        $sessionId = (json_decode($content, true) ?? [])['newSessionId'] ?? null;

        if ($sessionId === null) {
            throw Exception::sessionIdResolveFailed('Parsing got no results');
        }
        $this->logger->info('Link successfully built');
        return $sessionId;
    }
    private function bootstrapSessionId()
    {
        try {
            $url = 'https://public.tableau.com/views/monitor_15841091301660/sheet0?:showVizHome=no';
            $response = $this->http->request('GET', $url, [
                'headers' => [
                    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
                    'Cookie: tableau_locale=en; tableau_public_negotiated_locale=en-us',
                ],
            ]);
            $content = $response->getContent();
        } catch (ClientException $e) {
            throw Exception::sessionIdResolveFailed($e->getMessage());
        }

        $crawler = new \Symfony\Component\DomCrawler\Crawler($content);
        $sessionId = json_decode($crawler->filter('#tsConfigContainer')->text(), true)['sessionid'] ?? null;

        if ($sessionId === null) {
            throw Exception::sessionIdResolveFailed('Bootstrap session ID could not be parsed');
        }
        return $sessionId;
    }
}