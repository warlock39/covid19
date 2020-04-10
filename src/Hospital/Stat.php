<?php


namespace App\Hospital;


use App\When;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

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
        return $this->statQb()
            ->where('report_date = ?')
            ->setParameters([$date->format('Y-m-d')])
            ->execute()->fetchAll();
    }
    public function daily(): array
    {
        return $this->statQb()->execute()->fetchAll();
    }

    private function statQb(): QueryBuilder
    {
        return clone $this->conn->createQueryBuilder()
            ->select([
                'report_date',
                'state_id',
                'hospital',
                'edrpo',
                'suspicion',
                'confirmed',
                'deaths',
                'recovered',
                'lat',
                'long',
            ])
            ->from('hospital_stat');
    }
}
