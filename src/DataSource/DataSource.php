<?php


namespace App\DataSource;


use DateTimeImmutable;

interface DataSource
{
    public function actualize(DateTimeImmutable $date): void;

    public const STATES_MAP = [

        'Одесская область' => 'ua-od',
        'Херсонская область' => 'ua-ks',
        'Киев' => 'ua-kc',
        'Житомирская область' => 'ua-zt',
        'Сумская область' => 'ua-sm',
        'Донецкая область' => 'ua-dt',
        'Днепропетровская область' => 'ua-dp',
        'Харьковская область' => 'ua-kk',
        'Луганская область' => 'ua-lh',
        'Полтавская область' => 'ua-pl',
        'Запорожская область' => 'ua-zp',
        'Sevastopol' => 'ua-sc',
        'Черниговская область' => 'ua-ch',
        'Ровенская область' => 'ua-rv',
        'Черновицкая область' => 'ua-cv',
        'Ивано-Франковская область' => 'ua-if',
        'Хмельницкая область' => 'ua-km',
        'Львовская область' => 'ua-lv',
        'Тернопольская область' => 'ua-tp',
        'Закарпатская область' => 'ua-zk',
        'Волынская область' => 'ua-vo',
        'Черкасская область' => 'ua-ck',
        'Кировоградская область' => 'ua-kh',
        'Киевская область' => 'ua-kv',
        'Николаевская область' => 'ua-mk',
        'Винницкая область' => 'ua-vi',

        'Івано-Франківська область'  => 'ua-if',
        'Кіровоградська область'  => 'ua-kh',
        'Луганська область'  => 'ua-lh',
        'Львівська область'  => 'ua-lv',
        'Миколаївська область'  => 'ua-mk',
        'Одеська область'  => 'ua-od',
        'Полтавська область'  => 'ua-pl',
        'Рівненська область'  => 'ua-rv',
        'Сумська область'  => 'ua-sm',
        'Тернопільська область'  => 'ua-tp',
        'Харківська область'  => 'ua-kk',
        'Херсонська область'  => 'ua-ks',
        'Хмельницька область'  => 'ua-km',
        'Черкаська область'  => 'ua-ck',
        'Чернігівська область'  => 'ua-ch',
        'Чернівецька область'  => 'ua-cv',
        'Запорізька область'  => 'ua-zp',
        'Закарпатська область'  => 'ua-zk',
        'Житомирська область'  => 'ua-zt',
        'Донецька область'  => 'ua-dt',
        'Дніпропетровська область'  => 'ua-dp',
        'Волинська область'  => 'ua-vo',
        'Вінницька область'  => 'ua-vi',
        'Київська область'  => 'ua-kv',
        'Київ'  => 'ua-kc',

        'Луганська' => 'ua-lh',
        'Харківська' => 'ua-kk',
        'Миколаївська' => 'ua-mk',
        'Хмельницька' => 'ua-km',
        'Тернопільська' => 'ua-tp',
        'м. Київ' => 'ua-kc',
        'Черкаська' => 'ua-ck',
        'Чернігівська' => 'ua-ch',
        'Сумська' => 'ua-sm',
        'Полтавська' => 'ua-pl',
        'Івано-Франківська' => 'ua-if',
        'Чернівецька' => 'ua-cv',
        'Херсонська' => 'ua-ks',
        'Київська' => 'ua-kv',
        'Волинська' => 'ua-vo',
        'Кіровоградська' => 'ua-kh',
        'Рівненська' => 'ua-rv',
        'Донецька' => 'ua-dt',
        'Запорізька' => 'ua-zp',
        'Одеська' => 'ua-od',
        'Житомирська' => 'ua-zt',
        'Вінницька' => 'ua-vi',
        'Львівська' => 'ua-lv',
        'Закарпатська' => 'ua-zk',
        'Дніпропетровська' => 'ua-dp',
    ];
}