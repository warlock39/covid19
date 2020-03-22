<?php
namespace App;

use DateTimeImmutable;

class When
{
    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
    public static function yesterday(): DateTimeImmutable
    {
        return new DateTimeImmutable('yesterday');
    }
}