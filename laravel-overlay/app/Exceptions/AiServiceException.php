<?php

namespace App\Exceptions;

use RuntimeException;

class AiServiceException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'ai_error')
    {
        parent::__construct($message);
    }
}
