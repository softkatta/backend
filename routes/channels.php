<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function ($user, string $userId) {
    return (string) $user->id === (string) $userId;
});
