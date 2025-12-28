<?php

namespace App\Exceptions\Llm;

/**
 * Exception thrown when request cost exceeds budget cap.
 */
class BudgetExceededException extends LlmException
{
    public string $budgetClass;
    public float $costUsd;
    public float $capUsd;
    
    public function __construct(
        string $budgetClass,
        float $costUsd,
        float $capUsd
    ) {
        parent::__construct(
            "Request cost \${$costUsd} exceeds {$budgetClass} budget cap of \${$capUsd}"
        );
        
        $this->budgetClass = $budgetClass;
        $this->costUsd = $costUsd;
        $this->capUsd = $capUsd;
    }
}

