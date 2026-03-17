<?php

namespace Tests\Feature\Key;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_store_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/keys', [
            'public_key' => str_repeat('A', 60),
            'encrypted_private_key' => str_repeat('B', 250),
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'public_key' => str_repeat('A', 60),
        ]);
    }

    public function test_unauthenticated_user_cannot_store_keys(): void
    {
        $response = $this->postJson('/keys', [
            'public_key' => 'test-public-key',
            'encrypted_private_key' => 'test-encrypted-private-key',
        ]);

        $response->assertUnauthorized();
    }

    public function test_store_keys_validates_public_key_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/keys', [
            'encrypted_private_key' => 'test-encrypted-private-key',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['public_key']);
    }

    public function test_store_keys_accepts_public_key_without_encrypted_private_key(): void
    {
        $user = User::factory()->create();

        // encrypted_private_key is optional — omitted when sessionStorage AES key is unavailable
        $response = $this->actingAs($user)->postJson('/keys', [
            'public_key' => str_repeat('A', 60),
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'public_key' => str_repeat('A', 60),
            'encrypted_private_key' => null,
        ]);
    }

    public function test_store_keys_validates_encrypted_private_key_min_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/keys', [
            'public_key' => str_repeat('A', 60),
            'encrypted_private_key' => 'too-short',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['encrypted_private_key']);
    }

    public function test_authenticated_user_can_get_own_keys(): void
    {
        $user = User::factory()->e2eeEnabled()->create();

        $response = $this->actingAs($user)->getJson('/keys/me');

        $response->assertOk()
            ->assertJsonStructure(['public_key', 'encrypted_private_key']);
    }

    public function test_authenticated_user_can_get_public_key_of_another_user(): void
    {
        $requester = User::factory()->create();
        $target = User::factory()->e2eeEnabled()->create();

        $response = $this->actingAs($requester)->getJson("/keys/{$target->id}");

        $response->assertOk()->assertJsonStructure(['public_key']);
        $response->assertJsonMissing(['encrypted_private_key']);
    }

    public function test_get_public_key_returns_404_if_key_not_set(): void
    {
        $requester = User::factory()->create();
        $target = User::factory()->create(); // no keys

        $response = $this->actingAs($requester)->getJson("/keys/{$target->id}");

        $response->assertNotFound();
    }
}
