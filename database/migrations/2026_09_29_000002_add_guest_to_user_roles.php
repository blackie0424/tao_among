<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['guest', 'viewer', 'editor', 'admin'])
                ->default('admin')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'guest')->update(['role' => 'viewer']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['viewer', 'editor', 'admin'])
                ->default('admin')
                ->change();
        });
    }
};
