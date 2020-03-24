<?php /** @noinspection PhpUnused */

namespace App\Controller;

use App\DataProvider;
use App\Exception;
use App\When;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class IndexController extends AbstractController
{
    /** @var DataProvider */
    private $stat;

    public function __construct(DataProvider $dataProvider)
    {
        $this->stat = $dataProvider;
    }
    /**
     * @Route("/api/today", name="today")
     * @param Request $request
     * @return JsonResponse
     */
    public function today(Request $request): JsonResponse
    {
        $data = $this->stat->casesBy(When::today());

        return $this->serialize($data, $request);
    }

    /**
     * @Route("/api/yesterday", name="yesterday")
     * @param Request $request
     * @return JsonResponse
     */
    public function yesterday(Request $request): JsonResponse
    {
        $data = $this->stat->casesBy(When::yesterday());

        return $this->serialize($data, $request);
    }

    /**
     * @Route("/api/daily", name="daily")
     * @param Request $request
     * @return JsonResponse
     */
    public function daily(Request $request): JsonResponse
    {
        // detailed data
        if ((int) $request->get('detailed') === 1) {
            $data = [];
            foreach($this->stat->casesDaily() as $row) {
                $data[$row['datetime']][$row['state_id']][] = [
                    'confirmed' => $row['confirmed'],
                    'deaths' => $row['deaths'],
                    'recovered' => $row['recovered'],
                ];
            }
        } else {
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
        }
        return $this->json($data);
    }

    /**
     * @Route("/api/actualize", name="actualize", methods={"POST"})
     */
    public function actualize(
        Request $request,
        Connection $connection
    ): JsonResponse
    {
        $date = When::fromString($request->get('date') ?? date('Y-m-d'));

        (new DataSource\Tkmedia($connection))->actualize($date);

        return $this->json([
            'success' => true,
        ]);
    }

    /**
     * @Route("/api/{date}", name="byDate")
     */
    public function date(Request $request, string $date): JsonResponse
    {
        $data = $this->stat->casesBy(When::fromString($date));

        return $this->serialize($data, $request);
    }

    /**
     * @Route("/api/at/{date}", name="atDate")
     */
    public function at(Request $request, string $date): JsonResponse
    {
        $data = $this->stat->casesAt(When::fromString($date));

        return $this->serialize($data, $request);
    }

    private function serialize(array $data, Request $req): JsonResponse
    {
        // short data (default)
        $projection = static function ($item) {
            return [$item['state_id'], $item['confirmed']];
        };

        // detailed data
        if ((int) $req->get('detailed') === 1) {
            $projection = $this->projection(['state_title', 'confirmed', 'deaths', 'recovered']);
        }

        $resp = [
            'data' => array_map($projection, $data),
        ];
        return $this->json($resp);
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
