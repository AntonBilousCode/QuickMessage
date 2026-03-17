<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('public_key', 2048)->nullable()->after('email');
            $table->text('encrypted_private_key')->nullable()->after('public_key');
            $table->boolean('e2ee_enabled')->default(false)->after('encrypted_private_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['public_key', 'encrypted_private_key', 'e2ee_enabled']);
        });
    }
};
