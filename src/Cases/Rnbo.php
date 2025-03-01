<?php

namespace App\Cases;


use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class Rnbo implements DataProvider, World
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
  SUM(confirmed) AS confirmed,
  SUM(deaths) AS deaths,
  SUM(recovered) AS recovered,
  SUM(suspicion) AS suspicion
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
        $query = <<<SQL
SELECT
  state_id,
  SUM(delta_confirmed) AS confirmed,
  SUM(delta_deaths) AS deaths,
  SUM(delta_recovered) AS recovered,
  SUM(delta_suspicion) AS suspicion
FROM 
     cases_rnbo
WHERE report_date = :date
GROUP BY state_id
HAVING
       SUM(delta_confirmed) > 0 
    OR SUM(delta_deaths) > 0 
    OR SUM(delta_recovered) > 0
    OR SUM(delta_suspicion) > 0
ORDER BY confirmed DESC 
SQL;
        return $this->conn->fetchAll($query, [
            'date' => $date->format('Y-m-d'),
        ]);
    }

    public function casesDaily(): array
    {
        $query = <<<SQL
SELECT
  report_date AS datetime,
  SUM(delta_confirmed) AS confirmed,
  SUM(delta_deaths) AS deaths,
  SUM(delta_recovered) AS recovered,
  SUM(delta_suspicion) AS suspicion
FROM 
     cases_rnbo
GROUP BY report_date
HAVING 
       SUM(delta_confirmed) > 0 
    OR SUM(delta_deaths) > 0 
    OR SUM(delta_recovered) > 0
    OR SUM(delta_suspicion) > 0
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
  SUM(delta_recovered) AS recovered,
  SUM(delta_suspicion) AS suspicion
FROM 
     cases_rnbo
GROUP BY report_date, state_id
HAVING 
       SUM(delta_confirmed) > 0 
    OR SUM(delta_deaths) > 0 
    OR SUM(delta_recovered) > 0
    OR SUM(delta_suspicion) > 0
ORDER BY report_date DESC, confirmed DESC 
SQL;
        return $this->conn->fetchAll($query);
    }

    public function byCountries(): array
    {
        $query = <<<SQL
SELECT
  report_date as datetime,
  country,
  SUM(delta_confirmed) AS confirmed,
  SUM(delta_deaths) AS deaths,
  SUM(delta_recovered) AS recovered,
  SUM(delta_suspicion) AS suspicion
FROM 
     cases_rnbo_world
WHERE country IN(
    'us',
    'spain',
    'italy',
    'germany',
    'france',
    'china',
    'iran',
    'united_kingdom',
    'russia',
    'belarus',
    'poland',
    'slovakia',
    'hungary',
    'romania',
    'moldova'
)
GROUP BY report_date, country
HAVING 
       SUM(delta_confirmed) > 0 
    OR SUM(delta_deaths) > 0 
    OR SUM(delta_recovered) > 0
    OR SUM(delta_suspicion) > 0
ORDER BY report_date DESC, confirmed DESC 
SQL;
        return $this->conn->fetchAll($query);
    }
}