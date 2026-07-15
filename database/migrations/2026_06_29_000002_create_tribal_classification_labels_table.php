<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tribal_classification_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tribal_classification_id');
            $table->unsignedBigInteger('fish_label_id');
            $table->timestamps();

            $table->foreign('tribal_classification_id')
                ->references('id')
                ->on('tribal_classifications')
                ->onDelete('cascade');

            $table->foreign('fish_label_id')
                ->references('id')
                ->on('fish_labels')
                ->onDelete('cascade');

            $table->unique(['tribal_classification_id', 'fish_label_id'], 'tcl_tc_id_fl_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tribal_classification_labels');
    }
};
