<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $fishIdColumnType = Schema::getColumnType('fish', 'id');

        Schema::create('reference_knowledge', function (Blueprint $table) use ($fishIdColumnType) {
            $table->id();

            if (in_array($fishIdColumnType, ['int', 'integer'], true)) {
                $table->unsignedInteger('fish_id');
            } else {
                $table->unsignedBigInteger('fish_id');
            }

            $table->foreignId('reference_id')->constrained('references')->cascadeOnDelete();
            $table->text('content');
            $table->string('pages');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('fish_id')->references('id')->on('fish')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_knowledge');
    }
};
