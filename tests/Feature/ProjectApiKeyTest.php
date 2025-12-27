<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiKeyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 'active',
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'plan_code' => 'trial_free',
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'status' => 'trial',
            'is_trial' => true,
        ]);

        $this->project = Project::create([
            'user_id' => $this->user->id,
            'name' => 'Test Project',
            'slug' => 'test-project',
            'status' => 'active',
        ]);

        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /** @test */
    public function user_can_create_api_key()
    {
        $response = $this->postJson("/api/v1/projects/{$this->project->id}/keys", [
            'name' => 'My API Key',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'key'],
            ]);

        // Key should start with cf_
        $this->assertStringStartsWith('cf_', $response->json('data.key'));
    }

    /** @test */
    public function user_can_list_api_keys()
    {
        // Create a key first
        $this->postJson("/api/v1/projects/{$this->project->id}/keys", [
            'name' => 'Test Key',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/keys", [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'masked_key', 'is_active']],
            ]);

        // Key should be masked
        $this->assertStringContainsString('****', $response->json('data.0.masked_key'));
    }

    /** @test */
    public function user_can_revoke_api_key()
    {
        // Create a key
        $createResponse = $this->postJson("/api/v1/projects/{$this->project->id}/keys", [
            'name' => 'Key to revoke',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $keyId = $createResponse->json('data.id');

        // Revoke it
        $response = $this->deleteJson("/api/v1/projects/{$this->project->id}/keys/{$keyId}", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'API key revoked successfully.');
    }

    /** @test */
    public function user_cannot_create_more_than_10_keys()
    {
        // Create 10 keys
        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/v1/projects/{$this->project->id}/keys", [
                'name' => "Key {$i}",
            ], [
                'Authorization' => "Bearer {$this->token}",
            ]);
        }

        // 11th should fail
        $response = $this->postJson("/api/v1/projects/{$this->project->id}/keys", [
            'name' => 'Too many',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422);
    }
}

