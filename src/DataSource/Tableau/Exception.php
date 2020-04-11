<?php


namespace App\DataSource\Tableau;

use App;


class Exception extends App\Exception
{
    public static function sessionIdResolveFailed(string $msg): Exception
    {
        return new self(__FUNCTION__, self::msg('Could not resolve sessionId', $msg));
    }
    public static function coordsNotFound(App\DataSource\Tableau\Hospital $hospital): Exception
    {
        return new self(__FUNCTION__, self::msg(
            'Coordinates could not be resolved',
            "Hospital name: {$hospital->name()} (edrpo: {$hospital->edrpo()})"));
    }
}