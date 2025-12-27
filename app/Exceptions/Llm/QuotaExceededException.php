<?php

namespace App\Exceptions\Llm;

class QuotaExceededException extends LlmException
{
    protected string $errorType = 'quota_exceeded';
    protected int $httpStatus = 429;

    public function __construct(string $message = 'Quota exceeded', int $retryAfter = 3600)
    {
        parent::__construct($message, 429);
        $this->retryAfter = $retryAfter;
    }
}

