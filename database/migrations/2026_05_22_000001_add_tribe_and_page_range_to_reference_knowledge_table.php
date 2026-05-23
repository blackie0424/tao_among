<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reference_knowledge', function (Blueprint $table) {
            $table->string('tribe')->nullable();
            $table->unsignedInteger('page_start')->nullable();
            $table->unsignedInteger('page_end')->nullable();
            $table->index(['reference_id', 'page_start', 'page_end'], 'reference_knowledge_reference_page_index');
        });

        DB::table('reference_knowledge')
            ->select(['id', 'pages'])
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    preg_match_all('/(\d+)(?:\s*-\s*(\d+))?/', (string) $item->pages, $matches, PREG_SET_ORDER);

                    if ($matches === []) {
                        continue;
                    }

                    $ranges = array_map(static function (array $match): array {
                        $start = (int) $match[1];
                        $end = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : $start;

                        return [
                            'start' => min($start, $end),
                            'end' => max($start, $end),
                        ];
                    }, $matches);

                    DB::table('reference_knowledge')
                        ->where('id', $item->id)
                        ->update([
                            'page_start' => min(array_column($ranges, 'start')),
                            'page_end' => max(array_column($ranges, 'end')),
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('reference_knowledge', function (Blueprint $table) {
            $table->dropIndex('reference_knowledge_reference_page_index');
            $table->dropColumn(['tribe', 'page_start', 'page_end']);
        });
    }
};
