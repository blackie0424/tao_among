<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_sessions', function (Blueprint $table): void {
            $table->id();
            $table->date('capture_date');
            $table->string('tribe', 32);
            $table->string('capture_method');
            $table->foreignId('place_id')->nullable()->constrained('places', 'id', 'capture_sessions_place_fk')->restrictOnDelete();
            $table->string('location_hint')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['capture_date', 'id'], 'capture_sessions_date_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_sessions');
    }
};
