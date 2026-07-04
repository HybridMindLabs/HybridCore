<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

// DM typing indicator (client-to-client whisper) — only the two participants may join.
Broadcast::channel('conversation.{id}', function (User $user, int $id) {
    $conversation = Conversation::find($id);

    if (! $conversation) {
        return false;
    }

    return in_array($user->id, [$conversation->participant_1_id, $conversation->participant_2_id], true);
});

Broadcast::channel('online-users', function (User $user) {
    return [
        'id' => $user->id,
        'username' => $user->username,
        'name' => $user->name,
        'avatar' => $user->avatar,
    ];
});
