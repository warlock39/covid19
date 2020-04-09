<?php


namespace App\DataSource\Tableau;


use App\Exception;
use InvalidArgumentException;

class InvalidRecord extends Exception
{
    public static function argument(InvalidArgumentException $e): self
    {
        return new self('invalid_argument', 'Invalid tableau stat record: '.$e->getMessage());
    }

    public static function state(Exception $e): self
    {
        return new self('invalid_state', 'Invalid tableau stat record: '.$e->getMessage());
    }
}