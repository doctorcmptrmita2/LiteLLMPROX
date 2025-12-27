<?php

namespace App\Services\Llm;

use App\Exceptions\Llm\BadRequestException;

class AdmissionControl
{
    /**
     * Clamp request payload to tier limits.
     */
    public function clamp(array $payload, string $tier): array
    {
        $limits = config("codexflow.admission.{$tier}", [
            'max_input' => 8000,
            'max_output' => 900,
        ]);

        // Estimate input tokens
        $inputTokens = $this->estimateInputTokens($payload['messages'] ?? []);

        // Clamp max_tokens if set
        if (isset($payload['max_tokens'])) {
            $payload['max_tokens'] = min($payload['max_tokens'], $limits['max_output']);
        } else {
            $payload['max_tokens'] = $limits['max_output'];
        }

        // Reject if input is too large
        if ($inputTokens > $limits['max_input']) {
            throw new BadRequestException(
                "Input too large for {$tier} tier. Max {$limits['max_input']} tokens, got approximately {$inputTokens}."
            );
        }

        return $payload;
    }

    /**
     * Estimate input tokens from messages.
     */
    protected function estimateInputTokens(array $messages): int
    {
        $chars = 0;
        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            
            // Handle array content (for vision)
            if (is_array($content)) {
                foreach ($content as $part) {
                    if (isset($part['text'])) {
                        $chars += strlen($part['text']);
                    }
                }
            } else {
                $chars += strlen($content);
            }
        }

        // Rough estimation: ~4 chars per token
        return (int) ceil($chars / 4);
    }

    /**
     * Get timeout for tier.
     */
    public function getTimeout(string $tier): int
    {
        return config("codexflow.admission.{$tier}.timeout", 60);
    }
}

