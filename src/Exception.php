<?php
namespace App;

class Exception extends \Exception
{
    private string $errorCode;

    public function __construct(string $errorCode, string $errorMsg)
    {
        $this->errorCode = $errorCode;
        parent::__construct($errorMsg);
    }

    public static function dataSourceNotSupported(): self
    {
        return new self(__FUNCTION__, 'Data source not supported');
    }
    public static function dataSourceNoWorldStat(): self
    {
        return new self(__FUNCTION__, 'Data source doesn\'t support World stat');
    }

    public static function actualizationFailed(string $msg): self
    {
        return new self(__FUNCTION__, $msg);
    }
    public static function invalidStateKey(string $key, array $available): self
    {
        $msg = "State key \"$key\" is not supported. Next ones available: ".implode(', ', $available);
        return new self(__FUNCTION__, $msg);
    }
    public static function stateKeyNotRecognized(string $name): self
    {
        $msg = "State key is not recognized by name \"$name\". Consider adding alias";
        return new self(__FUNCTION__, $msg);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function invalidDate(string $msg): Exception
    {
        return new self(__FUNCTION__, $msg);
    }

    protected static function msg(string $base, string $detailed = ''): string
    {
        return $detailed !== '' ? "$base: $detailed" : $base;
    }
}