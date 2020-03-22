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
     * @Route("/api/{date}", name="byDate")
     * @param Request $request
     * @param string $date
     * @return JsonResponse
     * @throws \Exception
     * @throws Throwable
     */
    public function date(Request $request, string $date): JsonResponse
    {
        $when = $this->createDate($date);
        $data = $this->stat->casesBy($when);

        return $this->serialize($data, $request);
    }

    /**
     * @Route("/api/at/{date}", name="atDate")
     * @param Request $request
     * @param string $date
     * @return JsonResponse
     * @throws Throwable
     */
    public function at(Request $request, string $date): JsonResponse
    {
        $when = $this->createDate($date);
        $data = $this->stat->casesAt($when);

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

    /**
     * @param string $date
     * @return DateTimeImmutable
     * @throws Throwable
     */
    private function createDate(string $date): DateTimeImmutable
    {
        $when = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$when instanceof DateTimeImmutable) {
            throw Exception::invalidDate('Invalid date provided. Specify date in Y-m-d format');
        }
        if ($when > new DateTimeImmutable()) {
            throw Exception::invalidDate('You specified date in the future. Hope there will be 0 cases. Health everybody!');
        }

        return $when;
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
