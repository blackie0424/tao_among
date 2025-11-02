<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            if (!Schema::hasColumn('fish_audios', 'name')) {
                $table->string('name')->nullable();
            }
            if (!Schema::hasColumn('fish_audios', 'locate')) {
                $table->string('locate')->nullable();
            }
        });

        Schema::table('fish_audios', function (Blueprint $table) {
            $table->index(['fish_id', 'name']);
        });
    }

    public function down()
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            $table->dropIndex(['fish_id', 'name']);
        });
    }
};
