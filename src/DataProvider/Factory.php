<?php

namespace App\DataProvider;


use Doctrine\DBAL\Connection;

class Factory
{
    public const COMPOSITE = 'composite';
    public const UKRAINE_CORONA = 'ukraine-corona';
    public const TKMEDIA = 'tkmedia';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function create(): DataProvider
    {
        $col = $this->connection->fetchColumn("SELECT value FROM settings WHERE key = 'data-source'");
        $name = $col ?: 'ukraine-corona';

        return $this->byName($name);
    }

    public function byName(string $name)
    {
        switch ($name) {
            case self::TKMEDIA:
                return new TkmediaDataProvider($this->connection);
                break;
            case self::COMPOSITE:
                return new CompositeDataProvider(
                    $this->connection,
                    new TkmediaDataProvider($this->connection),
                    new UkraineCoronaDataProvider($this->connection),
                );
                break;
            case self::UKRAINE_CORONA:
            default:
                return new UkraineCoronaDataProvider($this->connection);
        }
    }

    public function composite(): CompositeDataProvider
    {
        return $this->byName(self::COMPOSITE);
    }
}