<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reference_knowledge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fish_id')->constrained('fish')->onDelete('cascade'); // 外鍵 fish_id
            $table->foreignId('reference_id')->constrained('references')->onDelete('cascade'); // 外鍵 reference_id
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
