<?php


namespace App\DataSource\Tableau;


use App\Exception;
use App\States;
use App\When;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

class Record
{
    public const REPORT_DATE = 'report_date';
    public const PATIENT_STATUS = 'patient_status';
    public const SEATS_ALL = 'seats';
    public const SEATS_HOSPITALIZATION = 'seats_hospitalization';
    public const ADDRESS = 'address';
    public const EDRPO = 'edrpo';
    public const VENTILATORS = 'ventilators';
    public const RECOVERED_NEW = 'recovered_new';
    public const SUSPICION_ACTIVE = 'suspicion_active';
    public const SUSPICION_NEW = 'suspicion_new';
    public const HOSPITALIZED_NEW = 'hospitalized_new';
    public const HOSPITALIZED_ACTIVE = 'hospitalized_active';
    public const CONFIRMED_ACTIVE = 'confirmed_active';
    public const CONFIRMED_NEW = 'confirmed_new';
    public const DEATHS_NEW = 'deaths_new';
    public const HOSPITAL = 'hospital';
    public const STATE = 'state';
    public const DISTRICT = 'district';
    public const LAT = 'lat';
    public const LONG = 'long';

    public static function validated(array $data, MappingSet $mappingSet): array
    {
        $result = [];
        try {
            foreach ($mappingSet->required() as $key) {
                Assert::keyExists($data, $key);

                switch ($key) {
                    case self::RECOVERED_NEW:
                    case self::SUSPICION_ACTIVE:
                    case self::SUSPICION_NEW:
                    case self::CONFIRMED_ACTIVE:
                    case self::CONFIRMED_NEW:
                    case self::DEATHS_NEW:
                    case self::HOSPITALIZED_NEW:
                    case self::HOSPITALIZED_ACTIVE:
                    case self::SEATS_ALL:
                    case self::SEATS_HOSPITALIZATION:
                    case self::VENTILATORS:
                        $result[$key] = (int) $data[$key];
                        break;
                    case self::STATE:
                        $result['state_id'] = States::default()->keyOf($data[$key]);
                        break;
                    case self::DISTRICT:
                        $result[$key] = empty($data[$key]) || trim($data[$key]) === '-' ? null : $data[$key];
                        break;
                    case self::REPORT_DATE:
                        $result[$key] = When::fromString($data[$key], 'n/d/Y')->format('Y-m-d');
                        break;
                    case self::LAT:
                    case self::LONG:
                        $result[$key] = (float) $data[$key];
                        break;
                    case self::PATIENT_STATUS:
                    case self::ADDRESS:
                    case self::EDRPO:
                    case self::HOSPITAL:
                        $result[$key] = $data[$key] ?: null;
                        break;
                }
            }
        } catch (InvalidArgumentException $e) {
            throw InvalidRecord::argument($e);
        } catch (Exception $e) {
            $code = $e->errorCode();
            if ($code === 'stateKeyNotRecognized') {
                throw InvalidRecord::state($e);
            }
            if ($code === 'invalidDate') {
                throw InvalidRecord::invalidDate($e->getMessage());
            }
        }
        return $result;
    }
}