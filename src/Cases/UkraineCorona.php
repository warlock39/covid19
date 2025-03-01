<?php

namespace App\Cases;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class UkraineCorona implements DataProvider
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
  SUM(CASE WHEN event = 'confirmed' THEN count ELSE 0 END) AS confirmed,
  SUM(CASE WHEN event = 'death' THEN count ELSE 0 END) AS deaths,
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered,
  0 AS suspicion
FROM 
     cases 
WHERE 
      datetime <= :date
GROUP BY state_id
ORDER BY confirmed DESC
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
  SUM(CASE WHEN event = 'confirmed' THEN count ELSE 0 END) AS confirmed,
  SUM(CASE WHEN event = 'death' THEN count ELSE 0 END) AS deaths,
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered,
  0 AS suspicion
FROM 
     cases 
WHERE 
      datetime::date = :date
GROUP BY state_id
ORDER BY confirmed DESC
SQL;
        return $this->conn->fetchAll($query, [
            'date' => $date->format('Y-m-d')
        ]);
    }

    public function casesDaily(): array
    {
        $query = <<<SQL
SELECT
  datetime::date,
  SUM(CASE WHEN event = 'confirmed' THEN count ELSE 0 END) AS confirmed,
  SUM(CASE WHEN event = 'death' THEN count ELSE 0 END) AS deaths,
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered,
  0 AS suspicion
FROM 
     cases 
GROUP BY datetime::date
ORDER BY datetime DESC, confirmed DESC 
SQL;
        return $this->conn->fetchAll($query);
    }

    public function casesDailyDetailed(): array
    {
        $query = <<<SQL
SELECT
  datetime::date,
  state_id,
  SUM(CASE WHEN event = 'confirmed' THEN count ELSE 0 END) AS confirmed,
  SUM(CASE WHEN event = 'death' THEN count ELSE 0 END) AS deaths,
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered,
  0 AS suspicion
FROM 
     cases 
GROUP BY datetime::date, state_id
ORDER BY datetime DESC, confirmed DESC 
SQL;
        return $this->conn->fetchAll($query);
    }
}
