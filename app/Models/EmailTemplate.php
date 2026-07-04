<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'subject',
        'body_html',
        'variables',
        'active',
        'system',
    ];

    protected $casts = [
        'variables' => 'array',
        'active' => 'boolean',
        'system' => 'boolean',
    ];
}
