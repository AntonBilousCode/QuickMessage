<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Log::debug('Migration: creating messages table');

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('receiver_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('body');
            // read_at: null means unread (offline missed messages support)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Index for conversation queries (both directions)
            $table->index(['sender_id', 'receiver_id'], 'idx_messages_conversation');

            // Index for unread messages queries (receiver + unread filter)
            $table->index(['receiver_id', 'read_at'], 'idx_messages_unread');
        });

        Log::debug('Migration: messages table created');
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
