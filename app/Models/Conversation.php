<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['participant_1_id', 'participant_2_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function participant1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_1_id');
    }

    public function participant2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_2_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function otherParticipant(int $userId): User
    {
        return $this->participant_1_id === $userId
            ? $this->participant2
            : $this->participant1;
    }

    public static function between(int $userA, int $userB): ?self
    {
        [$p1, $p2] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        return self::where('participant_1_id', $p1)
            ->where('participant_2_id', $p2)
            ->first();
    }

    public static function firstOrCreateBetween(int $userA, int $userB): self
    {
        [$p1, $p2] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        return self::firstOrCreate([
            'participant_1_id' => $p1,
            'participant_2_id' => $p2,
        ]);
    }

    public function unreadCountFor(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
