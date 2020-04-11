<?php


namespace App\Controller;


use App\Hospital;
use App\When;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/hospitals", name="hospitals")
 */
class HospitalsController extends AbstractController
{
    private Hospital\Stat $stat;

    public function __construct(Hospital\Stat $stat)
    {
        $this->stat = $stat;
    }
    /**
     * @Route("/stat/new", name="statNew")
     */
    public function new(): JsonResponse
    {
        $data = $this->stat->new();
        if (empty($data)) {
            $data = $this->stat->at(When::yesterday());
        }
        return $this->json($data);
    }
    /**
     * @Route("/stat/cumulative", name="statCumulative")
     */
    public function cumulative(): JsonResponse
    {
        return $this->json($this->stat->by(When::today()));
    }
    /**
     * @Route("/stat/daily", name="statDaily")
     */
    public function daily(): JsonResponse
    {
        $data = $this->stat->daily();
        $response = [];
        foreach($data as $row) {
            $date = $row['report_date'];
            $row['lat'] = (float) $row['lat'];
            $row['long'] = (float) $row['long'];
            unset($row['report_date']);
            $response[$date][] = $row;
        }
        return $this->json($response);
    }
}