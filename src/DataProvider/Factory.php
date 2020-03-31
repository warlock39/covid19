<?php

namespace App\DataProvider;


use App\DataSource\Exception;
use Doctrine\DBAL\Connection;

class Factory
{
    public const COMPOSITE = 'composite';
    public const UKRAINE_CORONA = 'ukraine-corona';
    public const TKMEDIA = 'tkmedia';
    public const TABLEAU = 'tableau';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function __invoke()
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
                return new UkraineCoronaDataProvider($this->connection);
            case self::TABLEAU:
                return new TableauDataProvider($this->connection);
            default:
                if (empty($name)) {
                    return new UkraineCoronaDataProvider($this->connection);
                }
                throw Exception::dataSourceNotSupported();
        }
    }
}