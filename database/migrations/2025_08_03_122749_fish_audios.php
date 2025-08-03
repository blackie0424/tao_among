<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('fish_audio', function (Blueprint $table) {
            $table->id(); // id 欄位
            $table->unsignedBigInteger('fish_id'); // fish_id 欄位
            $table->string('url');
            $table->string('locate');
            $table->timestamps();
            $table->softDeletes();


            $table->foreign('fish_id')->references('id')->on('fish')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('fish_audio');
    }
};
