<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('presence.online', function (User $user): array {
    return ['id' => $user->id, 'name' => $user->name];
});

Broadcast::channel('messages.{userId}', function (User $user, int $userId): bool {
    $authorized = $user->id === $userId;

    Log::debug('Channel auth: messages.{userId}', [
        'auth_user_id' => $user->id,
        'channel_user_id' => $userId,
        'authorized' => $authorized,
    ]);

    return $authorized;
});
