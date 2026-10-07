<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capture_records', function (Blueprint $table): void {
            $table->foreignId('session_id')->nullable()->after('fish_id')
                ->constrained('capture_sessions', 'id', 'capture_records_session_fk')->restrictOnDelete();
            $table->string('location', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('capture_records')->whereNull('location')->update(['location' => '']);
        Schema::table('capture_records', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['session_id'] : 'capture_records_session_fk');
            $table->dropColumn('session_id');
            $table->string('location', 255)->nullable(false)->change();
        });
    }
};
