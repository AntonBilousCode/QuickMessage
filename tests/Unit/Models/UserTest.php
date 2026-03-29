<?php

namespace Tests\Unit\Models;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_messages_relation_returns_messages_sent_by_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Message::factory()->count(2)->create([
            'sender_id' => $alice->id,
            'receiver_id' => $bob->id,
        ]);

        // Message sent to Alice — must not appear
        Message::factory()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
        ]);

        $this->assertCount(2, $alice->sentMessages);
        $alice->sentMessages->each(fn ($m) => $this->assertEquals($alice->id, $m->sender_id));
    }

    public function test_received_messages_relation_returns_messages_received_by_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Message::factory()->count(3)->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
        ]);

        // Message sent by Alice — must not appear
        Message::factory()->create([
            'sender_id' => $alice->id,
            'receiver_id' => $bob->id,
        ]);

        $this->assertCount(3, $alice->receivedMessages);
        $alice->receivedMessages->each(fn ($m) => $this->assertEquals($alice->id, $m->receiver_id));
    }

    public function test_can_encrypt_with_returns_true_when_both_users_have_e2ee_and_keys(): void
    {
        $alice = User::factory()->e2eeEnabled()->create();
        $bob = User::factory()->e2eeEnabled()->create();

        $this->assertTrue($alice->canEncryptWith($bob));
    }

    public function test_can_encrypt_with_returns_false_when_sender_has_no_e2ee(): void
    {
        $alice = User::factory()->create(); // e2ee_enabled = false
        $bob = User::factory()->e2eeEnabled()->create();

        $this->assertFalse($alice->canEncryptWith($bob));
    }

    public function test_can_encrypt_with_returns_false_when_receiver_has_no_e2ee(): void
    {
        $alice = User::factory()->e2eeEnabled()->create();
        $bob = User::factory()->create(); // e2ee_enabled = false

        $this->assertFalse($alice->canEncryptWith($bob));
    }

    public function test_can_encrypt_with_returns_false_when_receiver_has_no_public_key(): void
    {
        $alice = User::factory()->e2eeEnabled()->create();
        $bob = User::factory()->e2eeEnabledNoKeys()->create();

        $this->assertFalse($alice->canEncryptWith($bob));
    }
}
