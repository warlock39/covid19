<?php


namespace App\DataSource\Tableau;


use Webmozart\Assert\Assert;

class MappingSet
{
    public const V_2020_03_28 = '2020-03-28';
    public const V_2020_03_30 = '2020-03-30';
    public const V_2020_04_11 = '2020-04-11';

    private string $version;

    private int $cntCols;
    private array $map;
    private array $required;

    public static function active(): self
    {
        return new self(self::V_2020_04_11);
    }
    public function __construct(string $version)
    {
        Assert::oneOf($version, [
            self::V_2020_03_28,
            self::V_2020_03_30,
            self::V_2020_04_11,
        ]);
        $this->version = $version;
        $this->cntCols = count(array_keys(self::$maps[$this->version]));
        $this->map = $this->initMap();
        $this->required = array_values($this->map);
    }

    public function satisfies(array $row): bool
    {
        return $this->cntCols === count($row);
    }

    public function map(): array
    {
        return $this->map;
    }
    public function required(): array
    {
        return $this->required;
    }

    private function initMap(): array
    {
        $map = [];
        $i = 0;
        foreach (self::$maps[$this->version] as $newKey) {
            if ($newKey !== null) {
                $map[$i] = $newKey;
            }
            $i++;
        }
        return $map;
    }
    private static array $maps = [
        self::V_2020_04_11 => [
            '﻿Info' => null,
            'max date' => null,
            'type' => Record::PATIENT_STATUS,
            'Лікарень' => null,
            'Number of Records' => null,
            'active_confirm' => Record::CONFIRMED_ACTIVE,
            'active_hosp' => Record::HOSPITALIZED_ACTIVE,
            'addresses' => Record::ADDRESS,
            'edrpou_hosp' => Record::EDRPO,
            'lat' => Record::LAT,
            'legal_entity_name_hosp' => Record::HOSPITAL,
            'lng' => Record::LONG,
            'new_confirm'  => Record::CONFIRMED_NEW,
            'new_death'    => Record::DEATHS_NEW,
            'new_hosp'     => Record::HOSPITALIZED_NEW,
            'new_recover'  => Record::RECOVERED_NEW,
            'new_susp'     => Record::SUSPICION_NEW,
            'pending_susp' => Record::SUSPICION_ACTIVE,
            'registration_area' => null,
            'Область' => Record::STATE,
            'total_area' => null,
            'zvit_date' => Record::REPORT_DATE,
        ],
        self::V_2020_03_28 => [
            '﻿кількість лікарень' => null,
            'кількість ліжкомісць' => Record::SEATS_ALL,
            'Info' => null,
            'max date' => null,
            'Geometry' => null,
            'Number of Records' => null,
            'adress' => null,
            'edrpo' => null,
            'name' => null,
            'oblast' => null,
            'Адреса' => Record::ADDRESS,
            'Госпіталізація' => null,
            'Звітна дата' => Record::REPORT_DATE,
            'Код ЄДРПОУ закладу охорони здоров\'я' => Record::EDRPO,
            'Кількість апаратів штучної вентиляції легень у закладі охорони здоров\'я' => Record::VENTILATORS,
            'Кількість випадків одужання серед осіб із підтвердженим діагнозом COVID-19 протягом звітного періоду' => Record::RECOVERED_NEW,
            'Кількість ліжкомісць, пристосованих для госпіталізації осіб з підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV)' => Record::SEATS_HOSPITALIZATION,
            'Кількість осіб з підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV), випадки яких очікують лабораторного підтвердження' => Record::SUSPICION_ACTIVE,
            'Кількість осіб із підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV), випадки яких зафіксовано протягом звітного період' => Record::SUSPICION_NEW,
            'Кількість осіб із підтвердженим діагнозом COVID-19, спричиненим коронавірусом SARS-CoV-2 (2019-nCoV), які перебувають на лікува' => Record::CONFIRMED_ACTIVE,
            'Кількість осіб, діагноз COVID-19 яких підтвердився протягом звітного періоду' => Record::CONFIRMED_NEW,
            'Кількість смертельних випадків серед осіб із підтвердженим діагнозом COVID-19 протягом звітного періоду' => Record::DEATHS_NEW,
            'Назва закладу охорони здоров\'я' => Record::HOSPITAL,
            'Область' => Record::STATE,
            'Район' => Record::DISTRICT,
            'Стан:' => Record::PATIENT_STATUS,
        ],
        self::V_2020_03_30 => [
            '﻿ADDRESS' => null,
            'кількість лікарень' => null,
            'кількість ліжкомісць' => Record::SEATS_ALL,
            'Info' => null,
            'max date' => null,
            'EDRPO' => null,
            'Geometry' => null,
            'NAME' => null,
            'Number of Records' => null,
            'OBLAST' => null,
            'OTHER' => null,
            'RAYON' => null,
            'addrlocat' => null,
            'addrtype' => null,
            'Адреса' => Record::ADDRESS,
            'Госпіталізація' => Record::HOSPITALIZED_NEW,
            'Звітна дата' => Record::REPORT_DATE,
            'Код ЄДРПОУ закладу охорони здоров\'я' => Record::EDRPO,
            'Кількість апаратів штучної вентиляції легень у закладі охорони здоров\'я' => Record::VENTILATORS,
            'Кількість випадків одужання серед осіб із підтвердженим діагнозом COVID-19 протягом звітного періоду' => Record::RECOVERED_NEW,
            'Кількість ліжкомісць, пристосованих для госпіталізації осіб з підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV)' => Record::SEATS_HOSPITALIZATION,
            'Кількість осіб з підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV), випадки яких очікують лабораторного підтвердження' => Record::SUSPICION_ACTIVE,
            'Кількість осіб із підозрою на зараження коронавірусом SARS-CoV-2 (2019-nCoV), випадки яких зафіксовано протягом звітного період' => Record::SUSPICION_NEW,
            'Кількість осіб із підтвердженим діагнозом COVID-19, спричиненим коронавірусом SARS-CoV-2 (2019-nCoV), які перебувають на лікува' => Record::CONFIRMED_ACTIVE,
            'Кількість осіб, діагноз COVID-19 яких підтвердився протягом звітного періоду' => Record::CONFIRMED_NEW,
            'Кількість смертельних випадків серед осіб із підтвердженим діагнозом COVID-19 протягом звітного періоду' => Record::DEATHS_NEW,
            'Назва закладу охорони здоров\'я' => Record::HOSPITAL,
            'Область' => Record::STATE,
            'Район' => Record::DISTRICT,
            'Стан:' => Record::PATIENT_STATUS,
        ],
    ];

}