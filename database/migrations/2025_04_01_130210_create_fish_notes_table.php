<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fish_notes', function (Blueprint $table) {
            $table->id(); // 主鍵 id
            $table->foreignId('fish_id')->constrained('fish')->onDelete('cascade'); // 外鍵 fish_id
            $table->text('note'); // 筆記內容，文字量較大
            $table->string('note_type', 50); // 筆記類型，文字量較小
            $table->timestamps(); // created_at 和 updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fish_notes');
    }
};
