<?php /** @noinspection PhpUnused */

namespace App\Controller;

use App\DataProvider\DataProvider;
use App\When;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    private DataProvider $stat;

    public function __construct(DataProvider $dataProvider)
    {
        $this->stat = $dataProvider;
    }

    /**
     * @Route("/api/new", name="new")
     */
    public function new(): JsonResponse
    {
        $data = $this->stat->newCases();
        if (empty($data)) {
            $data = $this->stat->casesAt(When::yesterday());
        }
        return $this->json([
            'data' => $data,
        ]);
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
                    'suspicion' => 0,
                ];
            }
            $data[$row['datetime']]['confirmed'] += $row['confirmed'];
            $data[$row['datetime']]['deaths'] += $row['deaths'];
            $data[$row['datetime']]['recovered'] += $row['recovered'];
            $data[$row['datetime']]['suspicion'] += $row['suspicion'];
        }
        return $this->json($data);
    }

    /**
     * @Route("/api/totals", name="totals")
     */
    public function totals(): JsonResponse
    {
        $data = $this->stat->casesBy(When::today());

        return $this->json([
            'data' => $data,
        ]);
    }
    /**
     * @Route("/api/detailed", name="detailed")
     */
    public function detailed(): JsonResponse
    {
        $data = [];
        foreach($this->stat->casesDailyDetailed() as $row) {
            $data[$row['datetime']][$row['state_id']] = [
                'confirmed' => $row['confirmed'],
                'deaths' => $row['deaths'],
                'recovered' => $row['recovered'],
                'suspicion' => $row['suspicion'],
            ];
        }
        return $this->json($data);
    }
    /**
     * @Route("/api/gender-age", name="genderAge")
     */
    public function genderAge(Connection $conn): JsonResponse
    {
        $data = $conn->fetchColumn('SELECT data FROM gender_age ORDER BY date DESC LIMIT 1');
        return new JsonResponse($data, 200, [], true);
    }
    /**
     * @Route("/api/world", name="world")
     */
    public function world(): JsonResponse
    {
        $data = [];
        foreach($this->stat->casesWorld() as $row) {
            $data[$row['datetime']][$row['country']] = [
                $row['confirmed'],
                $row['deaths'],
                $row['recovered'],
            ];
        }
        return $this->json($data);
    }
}
