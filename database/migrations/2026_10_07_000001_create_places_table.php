<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table): void {
            $table->id();
            $nameKey = $table->string('name_key', 191);
            if (DB::getDriverName() === 'mysql') {
                $nameKey->collation('utf8mb4_bin');
            }
            $table->string('name', 191);
            $table->string('tao_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('name_key', 'places_name_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
