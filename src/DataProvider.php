<?php
namespace App;


use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class DataProvider
{
    /** @var Connection */
    private $conn;

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
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered
FROM 
     cases 
WHERE 
      datetime <= :date
GROUP BY state_id
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
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered
FROM 
     cases 
WHERE 
      datetime::date = :date
GROUP BY state_id
SQL;
        return $this->conn->fetchAll($query, [
            'date' => $date->format('Y-m-d')
        ]);
    }

    public function casesDaily(): array
    {
        $query = <<<SQL
SELECT
  datetime,
  state_id,
  SUM(CASE WHEN event = 'confirmed' THEN count ELSE 0 END) AS confirmed,
  SUM(CASE WHEN event = 'death' THEN count ELSE 0 END) AS deaths,
  SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered
FROM 
     cases 
GROUP BY datetime, state_id
ORDER BY datetime DESC
SQL;
        return $this->conn->fetchAll($query);
    }
}