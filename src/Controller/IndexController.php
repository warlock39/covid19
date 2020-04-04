<?php /** @noinspection PhpUnused */

namespace App\Controller;

use App\DataProvider\DataProvider;
use App\Exception;
use App\When;
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
            $data[$row['datetime']][$row['state_id']] = [
                'confirmed' => $row['confirmed'],
                'deaths' => $row['deaths'],
                'recovered' => $row['recovered'],
            ];
        }
        return $this->json($data);
    }
    /**
     * @Route("/api/deaths-gender-age", name="deathsGenderAge")
     */
    public function deathsGenderAge(): JsonResponse
    {
        $actual = [
            'gender' => [
                'male' => 9,
                'female' => 19,
            ],
            'age' => [
                '30-39' => 2,
                '40-49' => 2,
                '50-59' => 9,
                '60-69' => 9,
                '70-79' => 5,
                '80-89' => 1,
            ],
        ];
        $data = [
            'actual' => $actual,
            '2020-04-04' => $actual,
            '2020-04-02' => [
                'gender-age' => [
                    'male' => [
                        '40-49' => 1,
                        '50-59' => 2,
                        '60-69' => 1,
                        '80-89' => 1,
                    ],
                    'female' => [
                        '30-39' => 2,
                        '50-59' => 6,
                        '60-69' => 5,
                        '70-79' => 2,
                    ],
                ],
                'gender' => [
                    'male' => 5,
                    'female' => 15,
                ],
            ]
        ];
        return $this->json($data);
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
