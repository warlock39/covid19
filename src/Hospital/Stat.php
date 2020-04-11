<?php


namespace App\Hospital;


use App\When;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class Stat
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function new(): array
    {
        return $this->at(When::today());
    }

    public function at(DateTimeImmutable $date): array
    {
        $query = <<<SQL
SELECT
    state_id,
    hospital,
    edrpo,
    suspicion_active AS suspicion,
    confirmed_active AS confirmed,
    deaths_new AS deaths,
    recovered_new AS recovered,
    lat,
    long
FROM hospital_stat
WHERE report_date::date = :date
ORDER BY confirmed DESC;
SQL;
        return $this->conn->fetchAll($query, [
            'date' => $date->format('Y-m-d')
        ]);
    }
    public function by(DateTimeImmutable $date): array
    {
        $query = <<<SQL
SELECT
    state_id,
    hospital,
    edrpo,
    SUM(suspicion_new) suspicion,
    SUM(confirmed_new) confirmed,
    SUM(deaths_new) deaths,
    SUM(recovered_new) recovered,
    MAX(lat) AS lat,
    MAX(long) AS long
FROM hospital_stat
WHERE report_date::date <= :date
GROUP BY state_id, hospital, edrpo
ORDER BY confirmed DESC;
SQL;
        return $this->conn->fetchAll($query, [
            'date' => $date->format('Y-m-d')
        ]);
    }
    public function daily(): array
    {
        $query = <<<SQL
SELECT
    report_date,
    state_id,
    hospital,
    edrpo,
    SUM(suspicion_new) suspicion,
    SUM(confirmed_new) confirmed,
    SUM(deaths_new) deaths,
    SUM(recovered_new) recovered,
    MAX(lat) AS lat,
    MAX(long) AS long
FROM hospital_stat
GROUP BY report_date, state_id, hospital, edrpo
ORDER BY report_date DESC, confirmed DESC;
SQL;
        return $this->conn->fetchAll($query);
    }
}
