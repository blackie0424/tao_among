<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capture_records', function (Blueprint $table) {
            $table->string('image_position')->nullable()->after('notes');
            $table->decimal('image_scale', 3, 2)->nullable()->after('image_position');
        });
    }

    public function down(): void
    {
        Schema::table('capture_records', function (Blueprint $table) {
            $table->dropColumn(['image_position', 'image_scale']);
        });
    }
};
