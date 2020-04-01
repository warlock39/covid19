<?php /** @noinspection PhpRedundantCatchClauseInspection */


namespace App\DataSource;


use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Webmozart\Assert\Assert;

class Rnbo implements DataSource
{
    private HttpClientInterface $http;
    private LoggerInterface $logger;
    private string $downloadDir;
    private Connection $connection;

    public function __construct(Connection $connection, string $actualizerDir, HttpClientInterface $http, LoggerInterface $logger)
    {
        $this->http = $http;
        $this->logger = $logger;
        $this->downloadDir = $actualizerDir;
        $this->connection = $connection;
    }
    public function actualize(DateTimeImmutable $date): void
    {
        $this->logger->info('Start actualization of RNBO datasource');
        try {
            $json = $this->download($date);
        } catch(ClientException | ServerException | InvalidArgumentException $e) {
            $this->logger->critical($e->getMessage());
            return;
        } catch (JsonException $e) {
            $this->logger->critical('JSON '.$e->getMessage());
            return;
        }

        $this->connection->beginTransaction();
        $this->connection->delete('cases_rnbo', ['report_date' => $dateStr = $date->format('Y-m-d')]);

        foreach ($json as $row) {

            try {
                Assert::keyExists($row, 'label');
                Assert::keyExists($row['label'], 'uk');
                Assert::keyExists(self::STATES_MAP, $row['label']['uk'], "Couldn't not find 'state_id' for {$row['label']['uk']}");

                [
                    'confirmed' => $confirmed,
                    'deaths' => $deaths,
                    'recovered' => $recovered,
                ] = $row;

                if ($confirmed === $deaths && $deaths === $recovered && $recovered === 0) {
                    continue;
                }

                $this->connection->insert('cases_rnbo', [
                    'actualized_at' => (new DateTimeImmutable())->format(DATE_RFC3339_EXTENDED),
                    'report_date' => $date->format('Y-m-d'),
                    'state_id' => self::STATES_MAP[$row['label']['uk']],
                    'confirmed' => $confirmed,
                    'recovered' => $recovered,
                    'deaths' => $deaths,
                    'existing' =>  (int) $row['existing'],
                    'suspicion' => (int) $row['suspicion'],
                    'lat' => (float) $row['lat'],
                    'lng' =>  (float) $row['lng'],
                    'delta_confirmed' => (int) $row['delta_confirmed'],
                    'delta_deaths' => (int) $row['delta_deaths'],
                    'delta_recovered' => (int) $row['delta_recovered'],
                    'delta_existing' => (int) $row['delta_existing'],
                    'delta_suspicion' => (int) $row['delta_suspicion']
                ]);
            } catch (Exception | InvalidArgumentException $e) {
                $this->logger->warning($e->getMessage());
                continue;
            }
        }
        $this->connection->commit();
        $this->logger->info('End of actualization of RNBO datasource');
    }

    private function download(DateTimeImmutable $date): array
    {
        $response = $this->http->request('GET', 'https://api-covid19.rnbo.gov.ua/data?to='.$date->format('Y-m-d'), [
            'verify_peer' => false,
            'headers' => [
                'Connection: keep-alive',
                'Pragma: no-cache',
                'Cache-Control: no-cache',
                'Accept: application/json, text/plain, */*',
                'User-Agent: Mozilla/5.0',
                'Origin: https://covid19.rnbo.gov.ua',
                'Sec-Fetch-Site: same-site',
                'Sec-Fetch-Mode: cors',
                'Referer: https://covid19.rnbo.gov.ua/',
            ]
        ]);
        $content = $response->getContent();

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        Assert::keyExists($data, 'ukraine');

        $dir = $this->downloadDir.'/rnbo/';
        if (!file_exists($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->downloadDir));
        }
        $filename = $dir.date('Y_m_d_H_i').'.json';
        file_put_contents($filename, $content);

        return $data['ukraine'];
    }
}