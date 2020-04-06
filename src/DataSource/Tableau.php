<?php /** @noinspection JsonEncodingApiUsageInspection */

declare(strict_types=1);

namespace App\DataSource;


use App\States;
use App\When;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use ErrorException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SplFileObject;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Webmozart\Assert\Assert;
use App\Exception as CommonException;

use function count;
use function json_decode;

class Tableau implements DataSource
{
    private string $downloadDir;
    private Connection $connection;
    private LoggerInterface $logger;
    private HttpClientInterface $http;

    private const V_2020_03_28 = '2020-03-28';
    private const V_2020_03_30 = '2020-03-30';

    public function __construct(Connection $connection, HttpClientInterface $http, string $actualizerDir, LoggerInterface $logger)
    {
        $this->downloadDir = $actualizerDir;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->http = $http;
    }

    /** @noinspection PhpRedundantCatchClauseInspection */
    public function actualize(DateTimeImmutable $date): void
    {
        $this->logger->info('Start actualization of Tableau datasource');
        try {
            $csv = $this->downloadCsv();
        } catch(ErrorException | RuntimeException | ClientException | Exception $e) {
            $this->logger->critical($e->getMessage());
            return;
        }

        $this->connection->beginTransaction();
        $this->connection->delete('cases_tableau_raw', ['actualized_at::date' => $dateStr = $date->format('Y-m-d')]);

        $this->logger->info('Start reading of CSV file...');
        $states = States::default();
        $i = $j = 0;
        foreach ($this->readCsv($csv) as $row) {

            try {

                $this->connection->insert('cases_tableau_raw', [
                    'actualized_at' => $date->format(DATE_RFC3339_EXTENDED),
                    'report_date' => When::fromString($row['report_date'], 'n/d/Y')->format('Y-m-d'),
                    'state_id' => $states->keyOf($row['state']),
                    'confirmed' => (int) $row['confirmed'],
                    'recovered' => (int) $row['recovered'],
                    'deaths' => (int) $row['deaths'],
                    'seats' => (int) $row['seats'],
                    'seats_hospitalization' => (int) $row['seats_hospitalization'],
                    'address' => $row['address'],
                    'edrpo' => $row['edrpo'],
                    'ventilators' => (int) $row['ventilators'],
                    'suspected_sum' => (int) $row['suspected_sum'],
                    'suspected' => (int) $row['suspected'],
                    'confirmed_sum' => (int) $row['confirmed_sum'],
                    'hospital' => $row['hospital'],
                    'district' => $row['district'],
                    'patient_status' => $row['patient_status'],
                ]);
                $i++;
            } catch (Exception | CommonException $e) {
                $this->logger->warning($e->getMessage(), [
                    'filename' => $csv->getFilename(),
                ]);
                $j++;
                continue;
            }
            if ($i % 500 === 0) {
                $this->logger->info(sprintf('%d lines read so far, %d succeed and %d failed', $i+$j, $i, $j));
            }
        }

        $this->connection->executeUpdate('TRUNCATE cases_tableau');
        $this->connection->executeQuery(<<<SQL
INSERT INTO cases_tableau
SELECT
    report_date,
    state_id,
    confirmed,
    recovered,
    deaths,
    seats,
    seats_hospitalization,
    ventilators,
    suspected,
    district,
    hospital,
    address,
    edrpo
FROM cases_tableau_raw
WHERE confirmed > 0 OR deaths > 0 OR recovered > 0
SQL);
        $this->connection->commit();
        $this->logger->info('End of actualization of Tableau datasource');
    }

    private function readCsv(SplFileObject $file): iterable
    {
        $columnsVersion = self::V_2020_03_30;
        $cntCols = count(array_keys(self::$maps[$columnsVersion]));

        $map = [];
        $i = 0;
        foreach (self::$maps[$columnsVersion] as $newKey) {
            if ($newKey !== null) {
                $map[$i] = $newKey;
            }
            $i++;
        }

        $headerSkipped = false;
        foreach ($file as $row) {
            if (!$headerSkipped) {
                $headerSkipped = true;
                continue;
            }
            if ($cntCols !== count($row)) {
                // skip last line
                if ($row[0] === null) {
                    continue;
                }
                $this->logger->warning('CSV columns count not equals to expected. Probably input format changed');
                continue;
            }
            $newRow = [];
            foreach ($map as $index => $key) {
                $newRow[$key] = $row[$index];
            }
            yield $newRow;
        }
    }

    /**
     * @throws ErrorException
     * @noinspection PhpDocRedundantThrowsInspection
     * @noinspection PhpRedundantCatchClauseInspection
     */
    private function downloadCsv(): SplFileObject
    {
        $this->logger->info('Downloading CSV file...');

        $dir = $this->downloadDir.'/tableau/';
        if (!file_exists($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->downloadDir));
        }

        $link1 = 'https://public.tableau.com/vizql/w/monitor_15841091301660/v/sheet0/viewData/sessions/%s/views/7294020847002097674_2837170078324805029?csv=true&showall=true';
        $link2 = 'https://public.tableau.com/vizql/w/monitor_15841091301660/v/sheet0/vud/sessions/%s/views/7294020847002097674_2837170078324805029?csv=true&showall=true';

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

        $filename = $dir.date('Y_m_d_H_i').'.csv';
        file_put_contents($filename, $content);

        $file = new SplFileObject($filename);
        $file->setFlags(SplFileObject::READ_CSV);

        $this->logger->info('CSV file has been downloaded');

        return $file;
    }

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
                'body' => http_build_query([ 'sheet_id' => '%D0%9E%D0%BF%D0%B5%D1%80%D0%B0%D1%82%D0%B8%D0%B2%D0%BD%D0%B8%D0%B9%20%D0%BC%D0%BE%D0%BD%D1%96%D1%82%D0%BE%D1%80%D0%B8%D0%BD%D0%B3' ]),
            ]);

            $content = $response->getContent();
        } catch (ClientException $e) {
            throw Exception::sessionIdResolveFailed($e->getMessage());
        }
        $content = substr($content, strpos($content, ';')+1);

        preg_match_all('/}\d{4,7};{/', $content, $matches, PREG_OFFSET_CAPTURE);
        $content = substr($content, 0, ($matches[0][0][1] ?? 0) +1);
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

        $crawler = new Crawler($content);
        $sessionId = json_decode($crawler->filter('#tsConfigContainer')->text(), true)['sessionid'] ?? null;

        if ($sessionId === null) {
            throw Exception::sessionIdResolveFailed('Bootstrap session ID could not be parsed');
        }
        return $sessionId;
    }

    private static array $maps = [
        self::V_2020_03_28 => [
            '﻿кількість лікарень' => null,
            'кількість ліжкомісць' => 'seats',
            'Info' => null,
            'max date' => null,
            'Geometry' => null,
            'Number of Records' => null,
            'adress' => null,
            'edrpo' => null,
            'name' => null,
            'oblast' => null,
            'Адреса' => 'address',
            'Госпіталізація' => null,
            'Звітна дата' => 'report_date',
            'Код ЄДРПОУ закладу охорони здоров\'я' => 'edrpo',
            'Кількість апаратів штучної вентиляції легень у закладі охорони здоров\'я' => 'ventilators',
            'Кількість випадків одужання серед осіб із підтвердженим діагнозом COVID-19 протягом звітного періоду' => 'recovered',
            'Кількість ліжкомісць, пристосованих для госпіталізації осіб з підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV)' => 'seats_hospitalization',
            'Кількість осіб з підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV), випадки яких очікують лабораторного підтвердження' => 'suspected_sum',
            'Кількість осіб із підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV), випадки яких зафіксовано протягом звітного період' => 'suspected',
            'Кількість осіб із підтвердженим діагнозом COVID-19, спричиненим коронавірусом SARS-CoV-2 (2019-nCoV), які перебувають на лікува' => 'confirmed_sum',
            'Кількість осіб, діагноз COVID-19 яких підтвердився протягом звітного періоду' => 'confirmed',
            'Кількість смертельних випадків серед осіб із підтвердженим діагнозом COVID-19 протягом звітного періоду' => 'deaths',
            'Назва закладу охорони здоров\'я' => 'hospital',
            'Область' => 'state',
            'Район' => 'district',
            'Стан:' => 'patient_status',
        ],
        self::V_2020_03_30 => [
            '﻿ADDRESS' => null,
            'кількість лікарень' => null,
            'кількість ліжкомісць' => 'seats',
            'Info' => null,
            'max date' => null,
            'EDRPO' => null,
            'Geometry' => null,
            'NAME' => null,
            'Number of Records' => null,
            'OBLAST' => null,
            'OTHER' => null,
            'RAYON' => null,
            'addrlocat' => null,
            'addrtype' => null,
            'Адреса' => 'address',
            'Госпіталізація' => 'hospitalization',
            'Звітна дата' => 'report_date',
            'Код ЄДРПОУ закладу охорони здоров\'я' => 'edrpo',
            'Кількість апаратів штучної вентиляції легень у закладі охорони здоров\'я' => 'ventilators',
            'Кількість випадків одужання серед осіб із підтвердженим діагнозом COVID-19 протягом звітного періоду' => 'recovered',
            'Кількість ліжкомісць, пристосованих для госпіталізації осіб з підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV)' => 'seats_hospitalization',
            'Кількість осіб з підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV), випадки яких очікують лабораторного підтвердження' => 'suspected_sum',
            'Кількість осіб із підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV), випадки яких зафіксовано протягом звітного період' => 'suspected',
            'Кількість осіб із підтвердженим діагнозом COVID-19, спричиненим коронавірусом SARS-CoV-2 (2019-nCoV), які перебувають на лікува' => 'confirmed_sum',
            'Кількість осіб, діагноз COVID-19 яких підтвердився протягом звітного періоду' => 'confirmed',
            'Кількість смертельних випадків серед осіб із підтвердженим діагнозом COVID-19 протягом звітного періоду' => 'deaths',
            'Назва закладу охорони здоров\'я' => 'hospital',
            'Область' => 'state',
            'Район' => 'district',
            'Стан:' => 'patient_status',
        ],
    ];
}