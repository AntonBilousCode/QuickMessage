<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'public_key',
        'encrypted_private_key',
        'e2ee_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'encrypted_private_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'e2ee_enabled' => 'boolean',
        ];
    }

    /**
     * Returns true when both this user and $other have E2EE enabled with a key pair uploaded,
     * meaning messages between them can be end-to-end encrypted.
     */
    public function canEncryptWith(self $other): bool
    {
        return $this->e2ee_enabled
            && $other->e2ee_enabled
            && $this->public_key !== null
            && $other->public_key !== null;
    }

    /**
     * Messages sent by this user.
     *
     * @return HasMany<Message, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages received by this user.
     *
     * @return HasMany<Message, $this>
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }
}
