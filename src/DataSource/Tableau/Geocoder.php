<?php


namespace App\DataSource\Tableau;


use Geocoder\Exception;
use Geocoder\Model\Coordinates;
use Geocoder\Provider\ArcGISOnline\ArcGISOnline;
use Geocoder\Query\GeocodeQuery;
use Geocoder\StatefulGeocoder;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class Geocoder
{
    private StatefulGeocoder $arcGisGeocoder;

    public function __construct(HttpClientInterface $client)
    {
        $provider = new ArcGISOnline(new HttplugClient($client), 'UA');
        $this->arcGisGeocoder = new StatefulGeocoder($provider, 'ua');
    }
    /**
     * @throws Exception\Exception
     * @throws Exception\CollectionIsEmpty
     */
    public function geocode(string $location): ?Coordinates
    {
        $result = $this->arcGisGeocoder->geocodeQuery(GeocodeQuery::create($location));
        return $result->first()->getCoordinates();
    }
}