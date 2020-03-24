<?php

namespace App\DataProvider;


use Doctrine\DBAL\Connection;

class Factory
{
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
        $type = $col ?: 'ukraine-corona';

        switch ($type) {
            case self::TKMEDIA:
                return new TkmediaDataProvider($this->connection);
                break;
            case self::UKRAINE_CORONA:
            default:
                return new UkraineCoronaDataProvider($this->connection);
        }
    }
}