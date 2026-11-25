<?php

namespace App\Exceptions;

use Exception;

class AiServiceException extends Exception
{
    public string $errorCode;

    public function __construct(string $errorCode, string $message = "", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
