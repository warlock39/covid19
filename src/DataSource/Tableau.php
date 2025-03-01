<?php

declare(strict_types=1);

namespace App\DataSource;


use App\DataSource\Tableau\Crawler;
use App\DataSource\Tableau\Exception;
use App\DataSource\Tableau\Geocoder;
use App\DataSource\Tableau\Hospital;
use App\DataSource\Tableau\InvalidRecord;
use App\DataSource\Tableau\MappingSet;
use App\DataSource\Tableau\Record;
use App\When;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;

use function count;

class Tableau implements DataSource
{
    private Connection $connection;
    private LoggerInterface $logger;
    private Crawler $crawler;
    private Geocoder $geocoder;
    private MappingSet $mappingSet;

    public function __construct(
        Connection $connection,
        Crawler $crawler,
        Geocoder $geocoder,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->crawler = $crawler;
        $this->geocoder = $geocoder;
        $this->mappingSet = MappingSet::active();
    }

    /** @noinspection PhpRedundantCatchClauseInspection */
    public function actualize(DateTimeImmutable $date): void
    {
        $this->logger->info('Start actualization of Tableau datasource');
        try {
            $csv = $this->crawler->grab();
        } catch (Downloader\Exception | Exception | TransportException $e) {
            $this->logger->critical($e->getMessage());
            return;
        }

        $data = $this->crawler->readCsv($csv, $this->mappingSet);
        $this->saveRawData($data);
        $this->aggregateOperationalStat();
        $this->actualizeHospitalsInfo();
        $this->aggregateHospitalsStat();

        $this->logger->info('End of actualization of Tableau datasource');
    }

    private function saveRawData(iterable $data): void
    {
        $this->connection->beginTransaction();

        $actualizedAt = When::todayRfc3339();
        $i = $j = 0;
        foreach ($data as $row) {
            try {
                $this->connection->insert('cases_tableau_raw',
                    Record::validated($row, $this->mappingSet) + ['actualized_at' => $actualizedAt]
                );
                $i++;
            } catch (InvalidRecord $e) {
                $this->logger->warning($e->getMessage());
                $j++;
                continue;
            }
            if ($i % 500 === 0) {
                $this->logger->info(sprintf('%d lines read so far, %d succeed and %d failed', $i + $j, $i, $j));
            }
        }
        $this->connection->commit();
    }

    private function aggregateOperationalStat(): void
    {
        $this->logger->info('Aggregate operational data from RAW data');
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('TRUNCATE cases_tableau');
        $this->connection->executeQuery(
            <<<SQL
INSERT INTO cases_tableau
SELECT
    report_date,
    state_id,
    confirmed_new,
    recovered_new,
    deaths_new,
    suspicion_new
FROM cases_tableau_raw
WHERE 
      actualized_at = (SELECT MAX(actualized_at) FROM cases_tableau_raw)
  AND (confirmed_new > 0 OR deaths_new > 0 OR recovered_new > 0)
SQL
        );
        $this->connection->commit();
    }

    private function aggregateHospitalsStat(): void
    {
        $this->logger->info('Aggregate hospitals data by RAW data');

        $this->connection->beginTransaction();
        $this->connection->executeUpdate('TRUNCATE hospital_stat ');
        $this->connection->executeQuery(
            <<<SQL
INSERT INTO hospital_stat
WITH stat AS (
    SELECT
        report_date,
        state_id,
        COALESCE(hospital, 'self-isolation') hospital,
        edrpo,
        SUM(suspicion_new) suspicion_new,
        SUM(confirmed_new) confirmed_new,
        SUM(deaths_new) deaths_new,
        SUM(recovered_new) recovered_new,
        SUM(suspicion_active) suspicion_active,
        SUM(confirmed_active) confirmed_active
    FROM cases_tableau_raw
    WHERE
            actualized_at = (SELECT MAX(actualized_at) FROM cases_tableau_raw)
    GROUP BY report_date, state_id, hospital, edrpo
    ORDER BY report_date DESC, confirmed_new DESC
)
SELECT 
      report_date,
      s.state_id,
      hospital,
      edrpo,
      suspicion_new,
      confirmed_new,
      deaths_new,
      recovered_new,
      suspicion_active,
      confirmed_active,
      h.lat,
      h.long,
      h.address
FROM stat s LEFT JOIN hospital h USING(edrpo)

SQL);
        $this->connection->commit();
    }

    private function actualizeHospitalsInfo(): void
    {
        $this->logger->info('Add new hospitals if not exists');
        $newHospitals = $this->connection->fetchAll(<<<SQL
SELECT 
    DISTINCT new.edrpo,
    new.state_id,
    new.district,
    new.address,
    new.hospital AS name
FROM 
     cases_tableau_raw new 
         LEFT JOIN hospital exst ON new.edrpo = exst.edrpo
WHERE 
      new.actualized_at = (SELECT MAX(actualized_at) FROM cases_tableau_raw)
  AND LOWER(new.edrpo) != 'самоізоляція'
  AND new.address IS NOT NULL
  AND exst.edrpo IS NULL;
SQL);
        $cntNew = count($newHospitals);
        if ($cntNew > 0) {
            $this->logger->info("There are {$cntNew} new hospitals. Resolve their coordinates");
        } else {
            $this->logger->info('There are no new hospitals');
        }
        foreach ($newHospitals as $row) {
            $hospital = Hospital::fromArray($row);
            $this->geocode($hospital);
            $this->connection->insert('hospital', $hospital->toArray());
        }
    }

    private function geocode(Hospital $hospital): void
    {
        try {
            usleep(100000);
            $hospital->geocode($this->geocoder);
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());
        }
    }
}