<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $participant_1_id
 * @property int $participant_2_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Message|null $latestMessage
 * @property-read Collection<int, Message> $messages
 * @property-read int|null $messages_count
 * @property-read User $participant1
 * @property-read User $participant2
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereLastMessageAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereParticipant1Id($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereParticipant2Id($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
