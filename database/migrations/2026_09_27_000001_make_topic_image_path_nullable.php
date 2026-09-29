<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->string('image_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('topics')->whereNull('image_path')->update(['image_path' => '']);

        Schema::table('topics', function (Blueprint $table) {
            $table->string('image_path')->nullable(false)->change();
        });
    }
};
