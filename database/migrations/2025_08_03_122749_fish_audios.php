<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('fish_audios', function (Blueprint $table) {
            $table->id(); // id 欄位
            $table->unsignedBigInteger('fish_id'); // fish_id 欄位
            $table->string('url'); // 音訊檔案網址資訊
            $table->string('locate')->nullable(); // 位置資訊
            $table->timestamps();

            $table->foreign('fish_id')->references('id')->on('fish')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('fish_audios');
    }
};
