<?php
declare(strict_types=1);
namespace App\DataSource;

use App\Cases\Tkmedia as TkmediaDataProvider;
use App\DataSource\Downloader\Downloader;
use App\DataSource\Downloader\Exception as DownloaderException;
use App\Exception;
use App\States;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

class Tkmedia implements DataSource
{
    private Connection $connection;
    private TkmediaDataProvider $dataProvider;
    private LoggerInterface $logger;
    private Downloader $downloader;

    public function __construct(
        Connection $connection,
        Downloader $downloader,
        LoggerInterface $logger,
        TkmediaDataProvider $dataProvider
    )
    {
        $this->connection = $connection;
        $this->dataProvider = $dataProvider;
        $this->logger = $logger;
        $this->downloader = $downloader;
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection PhpRedundantCatchClauseInspection
     */
    public function actualize(DateTimeImmutable $date): void
    {
        $this->logger->info('Start actualization of Tkmedia datasource');
        $dateStr = $date->format('Y-m-d');

        try {
            $html = $this->downloader->html('tkmedia')->download("https://tk.media/coronavirus/{$dateStr}")->content;
        } catch(DownloaderException $e) {
            $this->logger->critical($e->getMessage());
            return;
        }

        $byStates = $this->parse($html);

        $this->connection->beginTransaction();
        $this->connection->delete('cases_aggregated_tkmedia', ['date' => $dateStr]);

        foreach ($byStates as [$state, $confirmed, $recovered, $deaths]) {
            try {
                $this->connection->insert('cases_aggregated_tkmedia', [
                    'date' => $dateStr,
                    'state_id' => States::default()->keyOf($state),
                    'confirmed' => $confirmed,
                    'recovered' => $recovered,
                    'deaths' => $deaths,
                ]);
            } catch (Exception $e) {
                $this->logger->warning($e->getMessage());
                continue;
            }
        }
        $this->deaggregate($date);
        $this->connection->commit();
        $this->logger->info('End of actualization of Tkmedia datasource');
    }

    private function parse(string $html): array
    {
        $crawler = new Crawler($html);
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
        return $today;
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

}