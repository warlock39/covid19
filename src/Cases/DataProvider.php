<?php

namespace App\Cases;

use DateTimeImmutable;

interface DataProvider
{
    public function casesBy(DateTimeImmutable $date): array;

    public function casesAt(DateTimeImmutable $date): array;

    public function casesDaily(): array;

    public function casesDailyDetailed(): array;
}