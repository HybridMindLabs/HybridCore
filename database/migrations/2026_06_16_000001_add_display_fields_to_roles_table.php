<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('color', 7)->default('#64748b')->after('description');
            $table->string('icon')->default('ShieldCheck')->after('color');
            $table->boolean('is_system')->default(false)->after('icon');
            $table->unsignedInteger('sort')->default(100)->after('is_system');
        });

        $defaults = [
            'owner' => ['color' => '#ef4444', 'icon' => 'Crown', 'is_system' => true, 'sort' => 1],
            'super-admin' => ['color' => '#f59e0b', 'icon' => 'ShieldCheck', 'is_system' => false, 'sort' => 10],
            'admin' => ['color' => '#3b82f6', 'icon' => 'ShieldHalf', 'is_system' => false, 'sort' => 20],
            'support' => ['color' => '#22c55e', 'icon' => 'Headphones', 'is_system' => false, 'sort' => 30],
            'developer' => ['color' => '#a855f7', 'icon' => 'Code2', 'is_system' => false, 'sort' => 40],
            'user' => ['color' => '#64748b', 'icon' => 'User', 'is_system' => false, 'sort' => 100],
        ];

        foreach ($defaults as $slug => $values) {
            DB::table('roles')->where('slug', $slug)->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['color', 'icon', 'is_system', 'sort']);
        });
    }
};
