<?php


namespace App\Cases;


use App\When;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class TkmediaUkraineCorona implements DataProvider
{
    private Connection $conn;
    private Tkmedia $tkmedia;
    private UkraineCorona $corona;

    public function __construct(
        Connection $connection,
        Tkmedia $tkmedia,
        UkraineCorona $corona
    )
    {
        $this->tkmedia = $tkmedia;
        $this->corona = $corona;
        $this->conn = $connection;
    }
    public function casesBy(DateTimeImmutable $date): array
    {
        return $this->tkmedia->casesBy($date);
    }

    public function newCases(): array
    {
        $sql = <<<SQL
WITH last_corona AS (
    SELECT
        c.state_id,
        SUM(CASE WHEN event = 'confirmed' THEN count ELSE 0 END) AS confirmed,
        SUM(CASE WHEN event = 'death' THEN count ELSE 0 END) AS deaths,
        SUM(CASE WHEN event = 'recovered' THEN count ELSE 0 END) AS recovered
    FROM
        cases c
    WHERE datetime::date != NOW()::date
    GROUP BY c.state_id
)
SELECT
    tk.state_id,
    GREATEST(0, tk.confirmed - c.confirmed) AS confirmed,
    GREATEST(0, tk.deaths - c.deaths) AS deaths,
    GREATEST(0, tk.recovered - c.recovered) AS recovered,
    0 AS suspicion
FROM
    cases_aggregated_tkmedia tk LEFT JOIN
    last_corona c ON c.state_id = tk.state_id
WHERE 
     tk.date = NOW()::date
ORDER BY confirmed DESC
SQL;
        return $this->conn->fetchAll($sql);
    }


    public function casesDaily(): array
    {
        return $this->corona->casesDaily();
    }

    public function casesAt(DateTimeImmutable $date): array
    {
        if (When::isToday($date)) {
            return $this->newCases();
        }
        return $this->corona->casesAt($date);
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