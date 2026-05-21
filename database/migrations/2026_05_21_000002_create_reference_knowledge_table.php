<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reference_knowledge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fish_id')->constrained('fish')->cascadeOnDelete();
            $table->foreignId('reference_id')->constrained('references')->cascadeOnDelete();
            $table->text('content');
            $table->string('pages');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_knowledge');
    }
};

