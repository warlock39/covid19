<?php


namespace App\DataSource\Downloader;


use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Webmozart\Assert\Assert;

class Downloader
{
    public const JSON = 'json';
    public const CSV = 'csv';
    public const HTML = 'html';

    public static array $formats = [self::CSV, self::JSON, self::HTML];

    private string $format;
    private string $actualizedDir;
    private string $downloadDir;
    private HttpClientInterface $http;
    private LoggerInterface $logger;

    public function __construct(string $actualizerDir, HttpClientInterface $http, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->actualizedDir = $actualizerDir;
        $this->http = $http;
    }
    public function json(string $dir)
    {
        return $this->customDownloader(self::JSON, $dir);
    }
    public function csv(string $dir)
    {
        return $this->customDownloader(self::CSV, $dir);
    }
    public function html(string $dir)
    {
        return $this->customDownloader(self::HTML, $dir);
    }

    protected function download(string $link, array $options = [])
    {
        $this->logger->info("Downloading {$this->format} file...");

        $dir = $this->downloadDir;
        if (!file_exists($this->downloadDir) &&
            !mkdir($this->downloadDir, 0777, true) &&
            !is_dir($this->downloadDir)
        ) {
            throw Exception::dirNotCreated($this->downloadDir);
        }
        try {
            $response = $this->http->request('GET', $link, $options);

            if ($this->format === self::CSV) {
                $contentType = $response->getHeaders()['content-type'][0] ?? '';
                if (stripos('text/csv', $contentType) === false) {
                    throw Exception::notCsv();
                }
            }
            $content = $response->getContent();

        } catch (ClientException | ServerException | TransportException $e) {
            throw Exception::notDownloaded($this->format, $e->getMessage());
        }

        $filename = $dir.date('Y_m_d_H_i').".{$this->format}";
        file_put_contents($filename, $content);

        $this->logger->info("{$this->format} file has been downloaded");
        return new class($filename, $content) {
            public string $content;
            public string $filename;

            public function __construct(string $filename, string $content)
            {
                $this->filename = $filename;
                $this->content = $content;
            }
        };
    }

    /**
     * @throws Exception
     * @noinspection PhpDocRedundantThrowsInspection
     */
    private function customDownloader(string $format, string $dirname)
    {
        Assert::oneOf($format, self::$formats);
        $this->format = $format;
        $this->downloadDir = "$this->actualizedDir/{$dirname}/";

        $fn = fn(string $link, array $options = []) => $this->download($link, $options);
        return new class ($fn) {
            private $downloader;

            public function __construct(callable $downloader)
            {
                $this->downloader = $downloader;
            }
            public function download(string $link, array $options = [])
            {
                return ($this->downloader)($link, $options);
            }
        };
    }
}