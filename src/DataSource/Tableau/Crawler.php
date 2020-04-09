<?php /** @noinspection JsonEncodingApiUsageInspection */


namespace App\DataSource\Tableau;

use App\DataSource\Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SplFileObject;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function json_decode;

class Crawler
{
    private string $downloadDir;
    private LoggerInterface $logger;
    private HttpClientInterface $http;

    public function __construct(
        HttpClientInterface $http,
        string $actualizerDir,
        LoggerInterface $logger
    ) {
        $this->downloadDir = $actualizerDir;
        $this->logger = $logger;
        $this->http = $http;
    }
    /**
     * @throws Exception
     * @noinspection PhpRedundantCatchClauseInspection
     */
    public function grab(): string
    {
        $this->logger->info('Downloading CSV file...');

        $dir = $this->downloadDir . '/tableau/';
        if (!file_exists($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->downloadDir));
        }

        $link1 = 'https://public.tableau.com/vizql/w/monitor_15841091301660/v/sheet0/vud/sessions/%s/views/7294020847002097674_2837170078324805029?csv=true&showall=true';
        $link2 = 'https://public.tableau.com/vizql/w/monitor_15841091301660/v/sheet0/viewData/sessions/%s/views/7294020847002097674_2837170078324805029?csv=true&showall=true';

        $sessionId = $this->resolveSessionId();

        $download = function ($link) use ($sessionId) : string {
            $link = sprintf($link, $sessionId);
            $response = $this->http->request('GET', $link);

            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            if ($contentType !== 'text/csv;charset=utf-8') {
                throw Exception::contentTypeNotCsv();
            }
            return $response->getContent();
        };

        try {
            $content = $download($link1);
        } catch (ClientException $e) {
            throw Exception::csvNotDownloaded($e->getMessage());
        } catch (Exception $e) {
            $content = $download($link2);
        }

        $filename = $dir . date('Y_m_d_H_i') . '.csv';
        file_put_contents($filename, $content);

        $this->logger->info('CSV file has been downloaded');

        return $filename;
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