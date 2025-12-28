<?php

namespace App\Exceptions\Llm;

class TimeoutException extends LlmException
{
    protected string $errorType = 'timeout_error';
    protected int $httpStatus = 504;

    public function __construct(string $message = 'Request timed out')
    {
        parent::__construct($message, 504);
    }
}


