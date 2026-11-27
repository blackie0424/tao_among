<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capture_records', function (Blueprint $table) {
            $table->decimal('image_focus_x', 5, 2)->nullable()->after('image_scale');
            $table->decimal('image_focus_y', 5, 2)->nullable()->after('image_focus_x');
            $table->decimal('image_zoom', 3, 2)->nullable()->after('image_focus_y');
            $table->unsignedSmallInteger('image_rotation')->nullable()->after('image_zoom');
        });
    }

    public function down(): void
    {
        Schema::table('capture_records', function (Blueprint $table) {
            $table->dropColumn(['image_focus_x', 'image_focus_y', 'image_zoom', 'image_rotation']);
        });
    }
};
