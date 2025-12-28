<?php

namespace App\Exceptions\Llm;

/**
 * Exception thrown when a quality gate check fails.
 */
class QualityGateFailedException extends LlmException
{
    public string $gate;
    public bool $retriable;
    
    public function __construct(
        string $gate,
        string $message,
        bool $retriable = false
    ) {
        parent::__construct($message);
        $this->gate = $gate;
        $this->retriable = $retriable;
    }
    
    public function getGate(): string
    {
        return $this->gate;
    }
    
    public function isRetriable(): bool
    {
        return $this->retriable;
    }
}

