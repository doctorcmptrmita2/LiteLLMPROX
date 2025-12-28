<?php

namespace App\Exceptions\Llm;

class ProviderException extends LlmException
{
    protected string $errorType = 'provider_error';
    protected int $httpStatus = 502;

    public function __construct(string $message = 'Provider error', int $statusCode = 502)
    {
        parent::__construct($message, $statusCode);
        $this->httpStatus = $statusCode;
    }
}


