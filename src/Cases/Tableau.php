<?php


namespace App\Cases;


use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use RuntimeException;

class Tableau implements DataProvider
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }
    public function casesBy(DateTimeImmutable $date): array
    {
        $query = <<<SQL
SELECT
    state_id,
    SUM(confirmed) confirmed,
    SUM(deaths) deaths,
    SUM(recovered) recovered
FROM
    cases_tableau
WHERE
       report_date  <= :date
GROUP BY state_id
ORDER BY confirmed DESC;
SQL;
        return $this->conn->fetchAll($query, [
            'date' => $date->format('Y-m-d 23:59:59')
        ]);
    }

    public function casesAt(DateTimeImmutable $date): array
    {
        $query = <<<SQL
SELECT
    state_id,
    SUM(confirmed) confirmed,
    SUM(deaths) deaths,
    SUM(recovered) recovered
FROM
    cases_tableau
WHERE
        report_date  = :date
GROUP BY state_id
ORDER BY confirmed DESC;
SQL;
        return $this->conn->fetchAll($query, [
            'date' => $date->format('Y-m-d')
        ]);
    }

    public function casesDaily(): array
    {
        $query = <<<SQL
SELECT
    report_date::date AS datetime,
    SUM(confirmed) confirmed,
    SUM(deaths) deaths,
    SUM(recovered) recovered
FROM
    cases_tableau
GROUP BY report_date::date
ORDER BY confirmed DESC;
SQL;
        return $this->conn->fetchAll($query);
    }

    public function casesDailyDetailed(): array
    {
        $query = <<<SQL
SELECT
    report_date::date AS datetime,
    state_id,
    SUM(confirmed) confirmed,
    SUM(deaths) deaths,
    SUM(recovered) recovered
FROM
    cases_tableau
GROUP BY report_date::date, state_id
ORDER BY report_date::date DESC, confirmed DESC;
SQL;
        return $this->conn->fetchAll($query);
    }

    public function newCases(): array
    {
        throw new RuntimeException('Not implemented');
    }
}