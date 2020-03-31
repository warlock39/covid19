<?php


namespace App\DataSource;


use App\Exception;
use App\When;
use App\DataSource;
use DateTimeImmutable;

class Actualizer
{
    /** @var DataSource\DataSource[] */
    private array $dataSources;

    public const DS_TABLEAU = 'tableau';
    public const DS_TKMEDIA = 'tkmedia';
    public const SUPPORTED_DATASOURCES = [
        self::DS_TABLEAU,
        self::DS_TKMEDIA,
    ];

    public function __construct(DataSource\Tkmedia $tkmedia, DataSource\Tableau $tableau)
    {
        $this->dataSources = [
            self::DS_TABLEAU => $tableau,
            self::DS_TKMEDIA => $tkmedia,
        ];
    }

    public function actualize(string $dateStr = null): void
    {
        foreach($this->dataSources as $dataSource) {
            $dataSource->actualize($this->date($dateStr));
        }
    }
    public function actualizeDataSource(string $dataSource, string $dateStr = null): void
    {
        $this->dataSource($dataSource)->actualize($this->date($dateStr));
    }

    public function dataSource(string $dataSource): DataSource\DataSource
    {
        if (isset($this->dataSources[$dataSource])) {
            return $this->dataSources[$dataSource];
        }
        throw Exception::dataSourceNotSupported();
    }

    private function date(string $dateStr = null): DateTimeImmutable
    {
        return When::fromString($dateStr ?? date('Y-m-d'));
    }
}