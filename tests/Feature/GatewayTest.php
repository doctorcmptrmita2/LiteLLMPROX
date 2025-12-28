<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectApiKey;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with trial subscription
        $this->user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'plan_code' => 'trial_free',
            'starts_at' => now(),
            'ends_at' => now()->addHours(24),
            'status' => 'trial',
            'is_trial' => true,
            'trial_ends_at' => now()->addHours(24),
        ]);

        $this->project = Project::create([
            'user_id' => $this->user->id,
            'name' => 'Test Project',
            'slug' => 'test-project',
            'status' => 'active',
        ]);

        // Create API key
        $keyData = ProjectApiKey::generateKey();
        $this->apiKey = $keyData['plaintext'];
        
        $this->project->apiKeys()->create([
            'name' => 'Test Key',
            'key_prefix' => $keyData['prefix'],
            'key_hash' => $keyData['hash'],
        ]);
    }

    /** @test */
    public function it_returns_401_without_api_key()
    {
        $response = $this->postJson('/api/v1/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_401_with_invalid_api_key()
    {
        $response = $this->postJson('/api/v1/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], [
            'Authorization' => 'Bearer cf_invalid_key_12345',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_401_with_revoked_api_key()
    {
        // Revoke the key
        $this->project->apiKeys()->first()->revoke();

        $response = $this->postJson('/api/v1/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], [
            'Authorization' => "Bearer {$this->apiKey}",
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_messages_in_payload()
    {
        $response = $this->postJson('/api/v1/chat/completions', [], [
            'Authorization' => "Bearer {$this->apiKey}",
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_processes_valid_fast_request()
    {
        // Mock LiteLLM response
        Http::fake([
            '*/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'cf-fast',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => ['role' => 'assistant', 'content' => 'Hello!'],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ]),
        ]);

        $response = $this->postJson('/api/v1/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], [
            'Authorization' => "Bearer {$this->apiKey}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'choices' => [['message' => ['role', 'content']]],
                'usage',
            ]);
    }

    /** @test */
    public function it_uses_deep_tier_with_x_quality_header()
    {
        Http::fake([
            '*/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'model' => 'cf-deep',
                'choices' => [
                    ['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Deep response']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ]),
        ]);

        $response = $this->postJson('/api/v1/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Complex task']],
        ], [
            'Authorization' => "Bearer {$this->apiKey}",
            'X-Quality' => 'deep',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_lists_available_models()
    {
        $response = $this->getJson('/api/v1/models', [
            'Authorization' => "Bearer {$this->apiKey}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'object',
                'data' => [['id', 'object', 'owned_by']],
            ]);
    }

    /** @test */
    public function it_includes_request_id_in_response()
    {
        Http::fake([
            '*/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'choices' => [['message' => ['content' => 'Hi']]],
                'usage' => ['total_tokens' => 10],
            ]),
        ]);

        $response = $this->postJson('/api/v1/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], [
            'Authorization' => "Bearer {$this->apiKey}",
            'X-Request-Id' => 'test-request-123',
        ]);

        $response->assertHeader('X-Request-Id', 'test-request-123');
    }
}



