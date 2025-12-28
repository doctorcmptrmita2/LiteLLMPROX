<?php

namespace App\Services\Llm\Agents;

use App\Services\Llm\LiteLlmClient;
use App\Services\Llm\Pipeline\PipelineContext;

/**
 * Test Agent - Generates test cases for code changes.
 * 
 * Uses: cf-cheap-coder (Claude Haiku)
 * Output: Test files + how to run instructions.
 */
class TestAgent
{
    protected LiteLlmClient $client;
    
    public function __construct(LiteLlmClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * Generate tests for code changes
     */
    public function generate(PipelineContext $context): string
    {
        $model = 'cf-cheap-coder';
        
        // Use balanced for high/critical risk
        if (in_array($context->getRisk(), ['high', 'critical'])) {
            $model = 'cf-balanced-coder';
        }
        
        $systemPrompt = $this->buildSystemPrompt($context);
        $userPrompt = $this->buildUserPrompt($context);
        
        $response = $this->client->chatCompletion([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => 3500,
            'temperature' => 0.1,
        ]);
        
        return $response['choices'][0]['message']['content'] ?? '';
    }
    
    /**
     * Build system prompt
     */
    protected function buildSystemPrompt(PipelineContext $context): string
    {
        $triage = $context->triage ?? [];
        
        return <<<PROMPT
You are a test writer for a Laravel application. Generate comprehensive tests.

TASK CONTEXT:
- Type: {$triage['task_type']}
- Risk: {$triage['risk']}

OUTPUT FORMAT:
1. Start with "## How to Run" section
2. Then provide test file patches in unified diff format

TEST REQUIREMENTS:
- Use PHPUnit for Laravel
- Create both Unit and Feature tests as appropriate
- Cover happy path and edge cases
- Mock external dependencies
- Use factories for models
- Follow naming: test_<action>_<expected_result>

EXAMPLE OUTPUT:
## How to Run

```bash
php artisan test --filter=ExampleTest
```

## Edge Cases Covered
- Empty input handling
- Invalid data validation
- Authorization checks

## Test Files

--- /dev/null
+++ b/tests/Feature/ExampleTest.php
@@ -0,0 +1,30 @@
+<?php
+
+namespace Tests\\Feature;
+
+use Tests\\TestCase;
+
+class ExampleTest extends TestCase
+{
+    public function test_example_returns_success(): void
+    {
+        \$response = \$this->get('/api/example');
+        \$response->assertStatus(200);
+    }
+}
PROMPT;
    }
    
    /**
     * Build user prompt
     */
    protected function buildUserPrompt(PipelineContext $context): string
    {
        $code = $context->code ?? '';
        $review = $context->review ?? [];
        
        $prompt = "CODE CHANGES:\n{$code}\n\n";
        
        if (!empty($review['test_gaps'])) {
            $prompt .= "IDENTIFIED TEST GAPS:\n";
            foreach ($review['test_gaps'] as $gap) {
                $prompt .= "- {$gap}\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Generate comprehensive tests for these changes.";
        
        return $prompt;
    }
}

