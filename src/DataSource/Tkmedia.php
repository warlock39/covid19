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

        $statesMap = array_flip(self::$statesMap);
        $this->connection->delete('cases_aggregated_tkmedia', ['date' => $dateStr]);
        foreach ($byStates as [$state, $confirmed, $recovered, $deaths]) {
            // TODO use Assert::keyExists($statesMap, $state)
            $this->connection->insert('cases_aggregated_tkmedia', [
                'date' => $dateStr,
                'state_id' => $statesMap[$state] ?? $state,
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
        'ua-od' => 'Одесская область',
        'ua-ks' => 'Херсонская область',
        'ua-kc' => 'Киев',
        'ua-zt' => 'Житомирская область',
        'ua-sm' => 'Сумская область',
        'ua-dt' => 'Донецкая область',
        'ua-dp' => 'Днепропетровская область',
        'ua-kk' => 'Харьковская область',
        'ua-lh' => 'Луганская область',
        'ua-pl' => 'Полтавская область',
        'ua-zp' => 'Запорожская область',
        'ua-sc' => 'Sevastopol',
        'ua-kr' => 'Автономная Республика Крым',
        'ua-ch' => 'Черниговская область',
        'ua-rv' => 'Ровенская область',
        'ua-cv' => 'Черновицкая область',
        'ua-if' => 'Ивано-Франковская область',
        'ua-km' => 'Хмельницкая область',
        'ua-lv' => 'Львовская область',
        'ua-tp' => 'Тернопольская область',
        'ua-zk' => 'Закарпатская область',
        'ua-vo' => 'Волынская область',
        'ua-ck' => 'Черкасская область',
        'ua-kh' => 'Кировоградская область',
        'ua-kv' => 'Киевская область',
        'ua-mk' => 'Николаевская область',
        'ua-vi' => 'Винницкая область',
    ];
}