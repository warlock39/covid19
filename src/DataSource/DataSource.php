<?php


namespace App\DataSource;


use DateTimeImmutable;

interface DataSource
{
    public function actualize(DateTimeImmutable $date): void;
}