<?php


namespace App;


class States
{
    private array $statesMap = [
        'Івано-Франківська область' => 'ua-if',
        'Кіровоградська область' => 'ua-kh',
        'Луганська область' => 'ua-lh',
        'Львівська область' => 'ua-lv',
        'Миколаївська область' => 'ua-mk',
        'Одеська область' => 'ua-od',
        'Полтавська область' => 'ua-pl',
        'Рівненська область' => 'ua-rv',
        'Сумська область' => 'ua-sm',
        'Тернопільська область' => 'ua-tp',
        'Харківська область' => 'ua-kk',
        'Херсонська область' => 'ua-ks',
        'Хмельницька область' => 'ua-km',
        'Черкаська область' => 'ua-ck',
        'Чернігівська область' => 'ua-ch',
        'Чернівецька область' => 'ua-cv',
        'Запорізька область' => 'ua-zp',
        'Закарпатська область' => 'ua-zk',
        'Житомирська область' => 'ua-zt',
        'Донецька область' => 'ua-dt',
        'Дніпропетровська область' => 'ua-dp',
        'Волинська область' => 'ua-vo',
        'Вінницька область' => 'ua-vi',
        'Київська область' => 'ua-kv',
        'Київ' => 'ua-kc',
    ];
    private array $statesMapRev;

    private array $aliases = [
        'м. Київ' => 'ua-kc',
        'Автономна Республіка Крим' => 'ua-kr',
        'Киев' => 'ua-kc',
        'Одесская область' => 'ua-od',
        'Херсонская область' => 'ua-ks',
        'Житомирская область' => 'ua-zt',
        'Сумская область' => 'ua-sm',
        'Донецкая область' => 'ua-dt',
        'Днепропетровская область' => 'ua-dp',
        'Харьковская область' => 'ua-kk',
        'Луганская область' => 'ua-lh',
        'Полтавская область' => 'ua-pl',
        'Запорожская область' => 'ua-zp',
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
        'Odessa' => 'ua-od',
        'Kherson' => 'ua-ks',
        'Kiev City' => 'ua-kc',
        'Zhytomyr' => 'ua-zt',
        'Sumy' => 'ua-sm',
        'Donets\'k' => 'ua-dt',
        'Dnipropetrovs\'k' => 'ua-dp',
        'Kharkiv' => 'ua-kk',
        'Luhans\'k' => 'ua-lh',
        'Poltava' => 'ua-pl',
        'Zaporizhzhya' => 'ua-zp',
        'Sevastopol' => 'ua-sc',
        'Crimea' => 'ua-kr',
        'Chernihiv' => 'ua-ch',
        'Rivne' => 'ua-rv',
        'Chernivtsi' => 'ua-cv',
        'Ivano-Frankivs\'k' => 'ua-if',
        'Khmel\'nyts\'kyy' => 'ua-km',
        'L\'viv' => 'ua-lv',
        'Ternopil' => 'ua-tp',
        'Transcarpathia' => 'ua-zk',
        'Volyn' => 'ua-vo',
        'Cherkasy' => 'ua-ck',
        'Kirovohrad' => 'ua-kh',
        'Kiev' => 'ua-kv',
        'Mykolayiv' => 'ua-mk',
        'Vinnytsya' => 'ua-vi',
    ];

    protected static States $defaultStates;

    public static function default(): self
    {
        return static::$defaultStates ??= new self();
    }

    public function nameBy(string $key)
    {
        $this->statesMapRev ??= array_flip($this->statesMap);
        if (!isset($this->statesMapRev[$key])) {
            throw Exception::invalidStateKey($key, array_keys($this->statesMapRev));
        }
        return $this->statesMapRev[$key];
    }
    public function keyOf(string $name)
    {
        foreach([$this->statesMap, $this->aliases] as $container) {
            foreach([$name, $suffixedName = $name.' область'] as $needle) {
                if (isset($container[$needle])) {
                    return $container[$needle];
                }
            }
        }
        throw Exception::stateKeyNotRecognized($name);
    }
}