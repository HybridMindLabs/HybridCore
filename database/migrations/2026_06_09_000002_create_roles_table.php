<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['name' => 'Owner',       'slug' => 'owner',       'description' => 'Full platform access. Cannot be restricted.',  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Full admin access except owner-only actions.', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Admin',       'slug' => 'admin',       'description' => 'General admin access.',                        'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Support',     'slug' => 'support',     'description' => 'Read-only admin access for support staff.',    'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Developer',   'slug' => 'developer',   'description' => 'Developer access for extension testing.',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'User',        'slug' => 'user',        'description' => 'Standard registered user.',                    'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
