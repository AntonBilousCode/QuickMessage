<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk()->assertViewIs('settings.index');
    }

    public function test_unauthenticated_user_cannot_view_settings(): void
    {
        $response = $this->get('/settings');

        $response->assertRedirect('/login');
    }

    public function test_user_can_enable_e2ee(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/e2ee', ['enabled' => true]);

        $response->assertOk()->assertJson(['ok' => true, 'e2ee_enabled' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'e2ee_enabled' => true,
        ]);
    }

    public function test_user_can_disable_e2ee(): void
    {
        $user = User::factory()->e2eeEnabled()->create();

        $response = $this->actingAs($user)->postJson('/settings/e2ee', ['enabled' => false]);

        $response->assertOk()->assertJson(['ok' => true, 'e2ee_enabled' => false]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'e2ee_enabled' => false,
        ]);
    }

    public function test_disabling_e2ee_clears_keys(): void
    {
        $user = User::factory()->e2eeEnabled()->create();

        $this->actingAs($user)->postJson('/settings/e2ee', ['enabled' => false]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'public_key' => null,
            'encrypted_private_key' => null,
        ]);
    }

    public function test_e2ee_toggle_validates_enabled_field_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/e2ee', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['enabled']);
    }

    public function test_messages_index_passes_e2ee_encryption_mode_when_both_users_have_e2ee(): void
    {
        $authUser = User::factory()->e2eeEnabled()->create();
        $recipient = User::factory()->e2eeEnabled()->create();

        $response = $this->actingAs($authUser)->get("/messages/{$recipient->id}");

        $response->assertOk()->assertViewHas('encryptionMode', 'e2ee');
    }

    public function test_messages_index_passes_standard_mode_when_receiver_has_no_e2ee(): void
    {
        $authUser = User::factory()->e2eeEnabled()->create();
        $recipient = User::factory()->create(); // no e2ee

        $response = $this->actingAs($authUser)->get("/messages/{$recipient->id}");

        $response->assertOk()->assertViewHas('encryptionMode', 'standard');
    }
}
