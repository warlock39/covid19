<?php
declare(strict_types=1);
namespace App\DataSource;

use App\Exception;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\DomCrawler\Crawler;

class Tkmedia
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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