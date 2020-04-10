<?php
declare(strict_types=1);
namespace App\DataSource;

use App\Cases\Tkmedia as TkmediaDataProvider;
use App\Exception;
use App\States;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use ErrorException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

class Tkmedia implements DataSource
{
    private Connection $connection;
    private TkmediaDataProvider $dataProvider;
    private LoggerInterface $logger;
    private string $downloadDir;

    public function __construct(Connection $connection, string $actualizerDir, LoggerInterface $logger, TkmediaDataProvider $dataProvider)
    {
        $this->connection = $connection;
        $this->dataProvider = $dataProvider;
        $this->downloadDir = $actualizerDir;
        $this->logger = $logger;
    }

    public function actualize(DateTimeImmutable $date): void
    {
        $this->logger->info('Start actualization of Tkmedia datasource');
        $dateStr = $date->format('Y-m-d');

        try {
            $html = $this->downloadHtml($dateStr);
        } catch(ErrorException | RuntimeException$e) {
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
     * @throws ErrorException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    private function downloadHtml(string $date): string
    {
        $dir = $this->downloadDir.'/tkmedia/';
        if (!file_exists($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->downloadDir));
        }
        $destination = $dir.date('Y_m_d_H_i').'.html';
        $source = "https://tk.media/coronavirus/{$date}";
        copy($source, $destination);

        return file_get_contents($destination);
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