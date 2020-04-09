<?php
namespace App;

use DateTimeImmutable;
use DateTimeZone;

class When
{
    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
    public static function todayRfc3339(): string
    {
        return self::today()->format(DATE_RFC3339_EXTENDED);
    }
    public static function yesterday(): DateTimeImmutable
    {
        return new DateTimeImmutable('yesterday', new DateTimeZone('UTC'));
    }
    public static function fromString(string $date, string $format = 'Y-m-d'): DateTimeImmutable
    {
        $when = DateTimeImmutable::createFromFormat($format, $date);
        if (!$when instanceof DateTimeImmutable) {
            throw Exception::invalidDate("Invalid date provided. Specify date in $format format");
        }
        if ($when > new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw Exception::invalidDate('You specified date in the future. Hope there will be 0 cases. Health everybody!');
        }
        return $when;
    }
}