<?php


namespace App\DataSource\Tableau;


use App\States;
use Geocoder\Exception\CollectionIsEmpty;
use Geocoder\Model\Coordinates;
use Webmozart\Assert\Assert;

class Hospital
{
    private string $edrpo;
    private string $name;
    private string $stateId;
    private string $district;
    private string $address;
    private Coordinates $coordinates;

    private string $location;

    public static function fromArray(array $array): self
    {
        $notEmpty = static function ($item, $key) {
            Assert::keyExists($item, $key);
            Assert::notEmpty($item[$key]);
        };

        $notEmpty($array, 'edrpo');
        $notEmpty($array, 'name');
        $notEmpty($array, 'state_id');
        $notEmpty($array, 'address');

        $hospital = new self();
        [
            'edrpo' => $hospital->edrpo,
            'name' => $hospital->name,
            'state_id' => $hospital->stateId,
            'address' => $hospital->address,
        ] = $array;

        $hospital->district = $array['district'] ?? '';
        $hospital->coordinates = new Coordinates(
            (float) ($array['lat'] ?? 0),
            (float) ($array['long'] ?? 0),
        );
        return $hospital;
    }

    public function geocode(Geocoder $geocoder): void
    {
        try {
            $coords = $geocoder->geocode($this->location());
            if ($coords === null) {
                throw Exception::coordsNotFound($this);
            }
            $this->coordinates = $coords;
        } catch (CollectionIsEmpty $e) {
            throw Exception::coordsNotFound($this);
        }
    }

    public function location(): string
    {
        return $this->location ??= $this->fullLocation();
    }

    private function fullLocation(): string
    {
        $parts = [
            States::default()->nameBy($this->stateId),
            $this->district,
            $this->address,
        ];
        $parts = array_filter($parts);
        return implode(', ', $parts);
    }

    public function edrpo(): string
    {
        return $this->edrpo;
    }
    public function name(): string
    {
        return $this->name;
    }
    public function toArray(): array
    {
        return [
            'edrpo' => $this->edrpo,
            'name' => $this->name,
            'state_id' => $this->stateId,
            'district' => $this->district,
            'address' => $this->address,
            'lat' => $this->coordinates->getLatitude(),
            'long' => $this->coordinates->getLongitude(),
        ];
    }
}