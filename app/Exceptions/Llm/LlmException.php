<?php

namespace App\Exceptions\Llm;

use Exception;

abstract class LlmException extends Exception
{
    protected string $errorType;
    protected int $httpStatus = 500;
    protected ?int $retryAfter = null;

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function toArray(): array
    {
        $data = [
            'error' => [
                'message' => $this->getMessage(),
                'type' => $this->errorType,
                'code' => $this->getCode(),
            ],
        ];

        if ($this->retryAfter) {
            $data['error']['retry_after'] = $this->retryAfter;
        }

        return $data;
    }
}



