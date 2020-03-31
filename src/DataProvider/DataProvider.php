<?php

namespace App\DataProvider;

use DateTimeImmutable;

interface DataProvider
{
    public function newCases(): array;

    public function casesBy(DateTimeImmutable $date): array;

    public function casesAt(DateTimeImmutable $date): array;

    public function casesDaily(): array;

    public function casesDailyDetailed(): array;

}