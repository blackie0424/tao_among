<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fish', function (Blueprint $table) {
            $table->dropColumn(['type', 'locate', 'process']);
        });

        Schema::table('fish_notes', function (Blueprint $table) {
            $table->string('locate', 255)->nullable()->after('fish_id');        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fish', function (Blueprint $table) {
            $table->string('type', 255)->nullable()->after('image');
            $table->string('locate', 255)->nullable()->after('type');
            $table->string('process', 255)->nullable()->after('locate');
        });

        Schema::table('fish_notes', function (Blueprint $table) {
            $table->dropColumn("locate");
        });
    }
};
