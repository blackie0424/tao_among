<?php

use App\Services\PlaceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->string('tribe', 32)->nullable()->after('id');
            $table->string('scope_key', 32)->default('')->after('tribe');
            $table->boolean('is_provisional')->default(false)->after('notes');
        });

        DB::table('places')->orderBy('id')->each(function (object $place): void {
            DB::table('places')->where('id', $place->id)->update(['scope_key' => PlaceService::scopeKey($place->tribe)]);
        });

        Schema::table('places', function (Blueprint $table): void {
            $table->dropUnique('places_name_key_unique');
            $table->unique(['scope_key', 'name_key'], 'places_scope_name_unique');
        });
    }

    public function down(): void
    {
        $duplicateCount = DB::table('places')->select('name_key')->groupBy('name_key')->havingRaw('COUNT(*) > 1')->get()->count();
        if ($duplicateCount > 0) {
            throw new \RuntimeException("無法還原地名唯一規則：有 {$duplicateCount} 組重複名稱");
        }

        Schema::table('places', function (Blueprint $table): void {
            $table->dropUnique('places_scope_name_unique');
            $table->unique('name_key', 'places_name_key_unique');
            $table->dropColumn(['tribe', 'scope_key', 'is_provisional']);
        });
    }
};
