<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A non-human token carrier for admin-issued integrations (Discord bots,
 * external dashboards, extension-owned services) — deliberately not a User,
 * so a token's lifetime and audit trail never depend on a specific admin's
 * account existing, and it can never log into the admin panel itself.
 */
class ServiceAccount extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
