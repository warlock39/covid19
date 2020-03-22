<?php
namespace App;


use Throwable;

class Exception extends \Exception
{
    /** @var string */
    private $errorCode;
    /** @var string */
    private $errorMsg;

    public function __construct(string $errorCode, string $errorMsg)
    {
        $this->errorCode = $errorCode;
        $this->errorMsg = $errorMsg;
        parent::__construct($errorMsg);
    }

    public function getContext(): array
    {
        return [
            'error_code' => $this->errorCode,
            'error_msg' => $this->errorMsg,
        ];
    }
    public static function noStateId(): Exception
    {
        return new self(__FUNCTION__, 'State ID is required field');
    }

    public static function invalidDate(string $msg): Throwable
    {
        return new self(__FUNCTION__, $msg);
    }
}