<?php
declare(strict_types=1);
namespace App\DataSource;

use App\DataProvider\TkmediaDataProvider;
use App\Exception;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\DomCrawler\Crawler;

class Tkmedia
{
    private Connection $connection;
    private TkmediaDataProvider $dataProvider;

    public function __construct(Connection $connection, TkmediaDataProvider $dataProvider)
    {
        $this->connection = $connection;
        $this->dataProvider = $dataProvider;
    }

    /**
     * @param DateTimeImmutable $date
     */
    public function actualize(DateTimeImmutable $date): void
    {
        $dateStr = $date->format('Y-m-d');

        $link = "https://tk.media/coronavirus/{$dateStr}";

        $crawler = new Crawler(file_get_contents($link));
        $today = $crawler->filter('.virus_table_item')->each(static function (Crawler $node) {
            $state = $node->filter('.virus_table_region')->text();
            $confirmed = (int) $node->filter('.virus_table_sick')->text();
            $recovered = (int) $node->filter('.virus_table_recover')->text();
            $deaths = (int) $node->filter('.virus_table_dead')->text();
            return [$state, $confirmed, $recovered, $deaths];
        });

        if (count($today) === 0) {
            throw Exception::actualizationFailed('TkMedia crawler got no results');
        }
        array_shift($today); // remove "totals" row
        $byStates = $today;

        $statesMap = self::$statesMap;
        $this->connection->delete('cases_aggregated_tkmedia', ['date' => $dateStr]);
        foreach ($byStates as [$state, $confirmed, $recovered, $deaths]) {
            if (!isset($statesMap[$state])) {
                continue;
            }
            // TODO use Assert::keyExists($statesMap, $state)
            $this->connection->insert('cases_aggregated_tkmedia', [
                'date' => $dateStr,
                'state_id' => $statesMap[$state],
                'confirmed' => $confirmed,
                'recovered' => $recovered,
                'deaths' => $deaths,
            ]);
        }

        $this->deaggregate($date);
    }

    /**
     * @param DateTimeImmutable $date
     */
    public function deaggregate(DateTimeImmutable $date): void
    {
        $this->connection->delete('cases_tkmedia', ['datetime::date' => $date->format('Y-m-d')]);

        $today = $this->dataProvider->casesBy($date);
        $yesterday = $this->dataProvider->casesBy($date->modify('-1 day'));
        $yesterday = $this->groupByState($yesterday);

        $cases = array_map(static function ($case) use ($yesterday) {
            $case['confirmed'] -= $yesterday[$case['state_id']]['confirmed'] ?? 0;
            $case['recovered'] -= $yesterday[$case['state_id']]['recovered'] ?? 0;
            $case['deaths'] -= $yesterday[$case['state_id']]['deaths'] ?? 0;
            return $case;
        }, $today);
        $cases = array_filter($cases, static function ($case) {
            return $case['confirmed'] > 0 || $case['recovered'] > 0 || $case['deaths'] > 0;
        });

        foreach ($cases as $case) {
            foreach (['confirmed', 'recovered', 'deaths'] as $event) {
                if ($case[$event] > 0) {
                    $this->connection->insert('cases_tkmedia', [
                        'datetime' => $date->format(DATE_RFC3339_EXTENDED),
                        'state_id' => $case['state_id'],
                        'event' => $event,
                        'count' => $case[$event],
                    ]);
                }
            }
        }
    }

    private function groupByState(array $cases): array
    {
        $data = [];
        foreach ($cases as $row) {
            $data[$row['state_id']] = $row;
        }
        return $data;
    }

    public static array $statesMap = [
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
    ];
}