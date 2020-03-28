<?php /** @noinspection PhpUnused */

namespace App\Controller;

use App\DataProvider\CompositeDataProvider;
use App\DataProvider\Factory as DataProviderFactory;
use App\DataProvider\TkmediaDataProvider;
use App\Exception;
use App\DataSource;
use App\When;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    private CompositeDataProvider $stat;

    public function __construct(DataProviderFactory $factory)
    {
        $this->stat = $factory->composite();
    }

    /**
     * @Route("/api/new", name="new")
     */
    public function new(): JsonResponse
    {
        $data = $this->stat->newCases();

        return $this->serialize($data);
    }

    /**
     * @Route("/api/daily", name="daily")
     */
    public function daily(): JsonResponse
    {
        $data = [];
        foreach($this->stat->casesDaily() as $row) {
            if (!array_key_exists($row['datetime'], $data)) {
                $data[$row['datetime']] = [
                    'confirmed' => 0,
                    'deaths' => 0,
                    'recovered' => 0,
                ];
            }
            $data[$row['datetime']]['confirmed'] += $row['confirmed'];
            $data[$row['datetime']]['deaths'] += $row['deaths'];
            $data[$row['datetime']]['recovered'] += $row['recovered'];
        }
        return $this->json($data);
    }

    /**
     * @Route("/api/totals", name="totals")
     */
    public function totals(): JsonResponse
    {
        $data = $this->stat->casesBy(When::today());

        return $this->serialize($data);
    }
    /**
     * @Route("/api/detailed", name="detailed")
     */
    public function detailed(): JsonResponse
    {
        $data = [];
        foreach($this->stat->casesDailyDetailed() as $row) {
            $data[$row['datetime']][$row['state_id']][] = [
                'confirmed' => $row['confirmed'],
                'deaths' => $row['deaths'],
                'recovered' => $row['recovered'],
            ];
        }
        return $this->json($data);
    }

    /**
     * @Route("/api/actualize", name="actualize", methods={"POST"})
     */
    public function actualize(
        Request $request,
        Connection $connection,
        TkmediaDataProvider $dataProvider
    ): JsonResponse
    {
        $date = When::fromString($request->get('date') ?? date('Y-m-d'));

        (new DataSource\Tkmedia($connection, $dataProvider))->actualize($date);

        return $this->json([
            'success' => true,
        ]);
    }

    private function serialize(array $data): JsonResponse
    {
        $projection = $this->projection(['state_title', 'confirmed', 'deaths', 'recovered']);

        return $this->json([
            'data' => array_map($projection, $data),
        ]);
    }

    private function projection(array $fields): callable
    {
        return static function ($item) use ($fields) {
            if (empty($item['state_id'])) {
                throw Exception::noStateId();
            }
            $fields[] = 'state_id';
            return array_intersect_key($item, array_flip($fields));
        };
    }
}
