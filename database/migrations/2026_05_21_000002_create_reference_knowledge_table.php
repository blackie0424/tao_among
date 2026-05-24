<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reference_knowledge', function (Blueprint $table) {
            $table->id();
            // 指向舊表 fish（int）→ 用 integer
            $table->integer('fish_id');
            $table->foreign('fish_id')->references('id')->on('fish')->cascadeOnDelete();
    
            // 指向新表 references（bigint unsigned）→ 用 foreignId
            $table->foreignId('reference_id')->constrained('references')->cascadeOnDelete();
    
            $table->text('content');
            $table->string('pages');
            $table->text('note')->nullable();
            $table->integer('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_knowledge');
    }
};
