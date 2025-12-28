<?php

namespace App\Exceptions\Llm;

class BadRequestException extends LlmException
{
    protected string $errorType = 'bad_request';
    protected int $httpStatus = 400;

    public function __construct(string $message = 'Bad request')
    {
        parent::__construct($message, 400);
    }
}


