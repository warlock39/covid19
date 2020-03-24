<?php

namespace App\DataProvider;


use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class TkmediaDataProvider implements DataProvider
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
  confirmed,
  deaths,
  recovered
FROM 
  cases_aggregated_tkmedia
WHERE 
 date = :date AND (
   confirmed != 0 OR recovered != 0 OR deaths != 0
 )
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
  SUM(CASE WHEN event = 'confirmed' THEN count ELSE 0 END) AS confirmed,
  SUM(CASE WHEN event = 'death' THEN count ELSE 0 END) AS deaths,
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered
FROM 
     cases_tkmedia
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
  state_id,
  SUM(CASE WHEN event = 'confirmed' THEN count ELSE 0 END) AS confirmed,
  SUM(CASE WHEN event = 'death' THEN count ELSE 0 END) AS deaths,
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered
FROM 
     cases_tkmedia
GROUP BY datetime, state_id
ORDER BY datetime DESC, confirmed DESC
SQL;
        return $this->conn->fetchAll($query);
    }

}