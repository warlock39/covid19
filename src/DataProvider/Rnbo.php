<?php

namespace App\DataProvider;


use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use RuntimeException;

class Rnbo implements DataProvider
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }
    public function newCases(): array
    {
        $query = <<<SQL
SELECT
  state_id,
  SUM(delta_confirmed) AS confirmed,
  SUM(delta_deaths) AS deaths,
  SUM(delta_recovered) AS recovered
FROM 
     cases_rnbo
WHERE report_date = NOW()::date
GROUP BY state_id
-- HAVING
--        SUM(delta_confirmed) > 0 
--     OR SUM(delta_deaths) > 0 
--     OR SUM(delta_recovered) > 0
ORDER BY confirmed DESC 
SQL;
        return $this->conn->fetchAll($query);
    }
    public function casesBy(DateTimeImmutable $date): array
    {
        $query = <<<SQL
SELECT
  state_id,
  SUM(confirmed) AS confirmed,
  SUM(deaths) AS deaths,
  SUM(recovered) AS recovered
FROM 
     cases_rnbo
WHERE 
     report_date = :date
GROUP BY state_id
ORDER BY confirmed DESC
SQL;
        return $this->conn->fetchAll($query, [
            'date' => $date->format('Y-m-d')
        ]);
    }

    public function casesAt(DateTimeImmutable $date): array
    {
        throw new RuntimeException('Not implemented');
    }

    public function casesDaily(): array
    {
        $query = <<<SQL
SELECT
  report_date AS datetime,
  SUM(delta_confirmed) AS confirmed,
  SUM(delta_deaths) AS deaths,
  SUM(delta_recovered) AS recovered
FROM 
     cases_rnbo
GROUP BY report_date
HAVING 
       SUM(delta_confirmed) > 0 
    OR SUM(delta_deaths) > 0 
    OR SUM(delta_recovered) > 0
ORDER BY report_date DESC, confirmed DESC 
SQL;
        return $this->conn->fetchAll($query);
    }

    public function casesDailyDetailed(): array
    {
        $query = <<<SQL
SELECT
  report_date as datetime,
  state_id,
  SUM(delta_confirmed) AS confirmed,
  SUM(delta_deaths) AS deaths,
  SUM(delta_recovered) AS recovered
FROM 
     cases_rnbo
GROUP BY report_date, state_id
HAVING 
       SUM(delta_confirmed) > 0 
    OR SUM(delta_deaths) > 0 
    OR SUM(delta_recovered) > 0
ORDER BY report_date DESC, confirmed DESC 
SQL;
        return $this->conn->fetchAll($query);
    }
}