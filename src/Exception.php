<?php
namespace App;

class Exception extends \Exception
{
    private string $errorCode;
    /** @var string */
    private string $errorMsg;

    public function __construct(string $errorCode, string $errorMsg)
    {
        $this->errorCode = $errorCode;
        $this->errorMsg = $errorMsg;
        parent::__construct($errorMsg);
    }

    public static function dataSourceNotSupported(): self
    {
        return new self(__FUNCTION__, 'Data source not supported');
    }

    public static function actualizationFailed(string $msg): self
    {
        return new self(__FUNCTION__, $msg);
    }

    public function getContext(): array
    {
        return [
            'error_code' => $this->errorCode,
            'error_msg' => $this->errorMsg,
        ];
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