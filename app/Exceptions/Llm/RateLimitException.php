<?php

namespace App\Exceptions\Llm;

class RateLimitException extends LlmException
{
    protected string $errorType = 'rate_limit_error';
    protected int $httpStatus = 429;

    public function __construct(string $message = 'Rate limit exceeded', int $retryAfter = 60)
    {
        parent::__construct($message, 429);
        $this->retryAfter = $retryAfter;
    }
}


