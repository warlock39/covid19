<?php

namespace App\Cases;


use App\DataSource\Exception;
use Doctrine\DBAL\Connection;

class DataProviderFactory
{
    public const TKMEDIA_UKRAINE_CORONA = 'tkmedia.ukraine-corona';
    public const UKRAINE_CORONA = 'ukraine-corona';
    public const TKMEDIA = 'tkmedia';
    public const TABLEAU = 'tableau';
    public const RNBO = 'rnbo';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function __invoke()
    {
        $name = $this->fromSettings();
        return $this->byName($name);
    }

    private function fromSettings(): string
    {
        $col = $this->connection->fetchColumn("SELECT value FROM settings WHERE key = 'data-source'");
        return $col ?: 'ukraine-corona';
    }

    public function byName(string $name)
    {
        switch ($name) {
            case self::TKMEDIA:
                return new Tkmedia($this->connection);
                break;
            case self::TKMEDIA_UKRAINE_CORONA:
                return new TkmediaUkraineCorona(
                    $this->connection,
                    new Tkmedia($this->connection),
                    new UkraineCorona($this->connection),
                );
                break;
            case self::UKRAINE_CORONA:
                return new UkraineCorona($this->connection);
            case self::TABLEAU:
                return new Tableau($this->connection);
            case self::RNBO:
                return new Rnbo($this->connection);
            default:
                if (empty($name)) {
                    return new UkraineCorona($this->connection);
                }
                throw Exception::dataSourceNotSupported();
        }
    }

    public function worldStat(): World
    {
        $name = $this->fromSettings();

        $rnbo = new Rnbo($this->connection);
        if ($name === self::RNBO) {
            return $rnbo;
        }
        if (empty($name)) {
            return $rnbo;
        }
        throw Exception::dataSourceNoWorldStat();
    }
}