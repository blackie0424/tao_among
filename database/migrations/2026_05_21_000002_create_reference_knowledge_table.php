<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $fishIdDefinition = $this->resolveFishIdDefinition();

        if (!Schema::hasTable('reference_knowledge')) {
            Schema::create('reference_knowledge', function (Blueprint $table) use ($fishIdDefinition) {
                $table->id();
                $this->addFishIdColumn($table, $fishIdDefinition);
                $table->unsignedBigInteger('reference_id');
                $table->text('content');
                $table->string('pages');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('fish_id')->references('id')->on('fish')->cascadeOnDelete();
                $table->foreign('reference_id')->references('id')->on('references')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        } else {
            $missingColumns = [
                'fish_id' => !Schema::hasColumn('reference_knowledge', 'fish_id'),
                'reference_id' => !Schema::hasColumn('reference_knowledge', 'reference_id'),
                'content' => !Schema::hasColumn('reference_knowledge', 'content'),
                'pages' => !Schema::hasColumn('reference_knowledge', 'pages'),
                'note' => !Schema::hasColumn('reference_knowledge', 'note'),
                'created_by' => !Schema::hasColumn('reference_knowledge', 'created_by'),
                'created_at' => !Schema::hasColumn('reference_knowledge', 'created_at'),
                'updated_at' => !Schema::hasColumn('reference_knowledge', 'updated_at'),
                'deleted_at' => !Schema::hasColumn('reference_knowledge', 'deleted_at'),
            ];

            Schema::table('reference_knowledge', function (Blueprint $table) use ($fishIdDefinition, $missingColumns) {
                if ($missingColumns['fish_id']) {
                    $this->addFishIdColumn($table, $fishIdDefinition);
                }

                if ($missingColumns['reference_id']) {
                    $table->unsignedBigInteger('reference_id');
                }

                if ($missingColumns['content']) {
                    $table->text('content');
                }

                if ($missingColumns['pages']) {
                    $table->string('pages');
                }

                if ($missingColumns['note']) {
                    $table->text('note')->nullable();
                }

                if ($missingColumns['created_by']) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }

                if ($missingColumns['created_at']) {
                    $table->timestamp('created_at')->nullable();
                }

                if ($missingColumns['updated_at']) {
                    $table->timestamp('updated_at')->nullable();
                }

                if ($missingColumns['deleted_at']) {
                    $table->softDeletes();
                }
            });
        }

        $this->syncExistingFishIdColumn($fishIdDefinition);
        $this->ensureForeignKeys();
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_knowledge');
    }

    private function addFishIdColumn(Blueprint $table, array $fishIdDefinition): void
    {
        if ($fishIdDefinition['type'] === 'int') {
            if ($fishIdDefinition['unsigned']) {
                $table->unsignedInteger('fish_id');
            } else {
                $table->integer('fish_id');
            }

            return;
        }

        if ($fishIdDefinition['unsigned']) {
            $table->unsignedBigInteger('fish_id');
        } else {
            $table->bigInteger('fish_id');
        }
    }

    private function ensureForeignKeys(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (!$this->hasForeignKey('reference_knowledge', 'reference_knowledge_fish_id_foreign')) {
            Schema::table('reference_knowledge', function (Blueprint $table) {
                $table->foreign('fish_id')->references('id')->on('fish')->cascadeOnDelete();
            });
        }

        if (!$this->hasForeignKey('reference_knowledge', 'reference_knowledge_reference_id_foreign')) {
            Schema::table('reference_knowledge', function (Blueprint $table) {
                $table->foreign('reference_id')->references('id')->on('references')->cascadeOnDelete();
            });
        }

        if (!$this->hasForeignKey('reference_knowledge', 'reference_knowledge_created_by_foreign')) {
            Schema::table('reference_knowledge', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.table_constraints')
                ->where('constraint_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('constraint_name', $constraintName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $foreignKeys = DB::select("PRAGMA foreign_key_list('{$table}')");
            $expectedColumn = match ($constraintName) {
                'reference_knowledge_fish_id_foreign' => 'fish_id',
                'reference_knowledge_reference_id_foreign' => 'reference_id',
                'reference_knowledge_created_by_foreign' => 'created_by',
                default => null,
            };

            return collect($foreignKeys)->contains(function (object $foreignKey) use ($expectedColumn): bool {
                return $foreignKey->from === $expectedColumn;
            });
        }

        return false;
    }

    private function resolveFishIdDefinition(): array
    {
        if (DB::getDriverName() === 'mysql') {
            $column = DB::table('information_schema.columns')
                ->select(['data_type', 'column_type'])
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'fish')
                ->where('column_name', 'id')
                ->first();

            if ($column !== null) {
                return [
                    'type' => in_array($column->data_type, ['int', 'integer'], true) ? 'int' : 'bigint',
                    'unsigned' => str_contains($column->column_type, 'unsigned'),
                    'sql' => strtoupper($column->column_type),
                ];
            }
        }

        $type = Schema::getColumnType('fish', 'id');

        return [
            'type' => in_array($type, ['int', 'integer'], true) ? 'int' : 'bigint',
            'unsigned' => true,
            'sql' => in_array($type, ['int', 'integer'], true) ? 'INT UNSIGNED' : 'BIGINT UNSIGNED',
        ];
    }

    private function syncExistingFishIdColumn(array $fishIdDefinition): void
    {
        if (!Schema::hasColumn('reference_knowledge', 'fish_id')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `reference_knowledge` MODIFY COLUMN `fish_id` %s NOT NULL',
            $fishIdDefinition['sql']
        ));
    }
};
