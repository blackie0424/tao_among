<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. users 表新增欄位
        Schema::table('users', function (Blueprint $table) {
            $table->string('source')->default('web')->after('password');
            $table->enum('role', ['viewer', 'editor', 'admin'])->default('admin')->after('source');
            $table->string('line_user_id')->nullable()->unique()->after('role');
            $table->string('picture_url')->nullable()->after('line_user_id');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        // 2. 現有 users 表的 web 帳號設定 source 和 role
        DB::table('users')->update(['source' => 'web', 'role' => 'admin']);

        // 3. 移除 is_admin 欄位（統一由 role 管理）
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });

        // 4. 將 line_users 資料搬移到 users 表
        if (Schema::hasTable('line_users')) {
            $lineUsers = DB::table('line_users')->get();
            foreach ($lineUsers as $lineUser) {
                // line_users 的 admin role 降為 editor（admin 只給 web 帳號）
                $role = $lineUser->role === 'admin' ? 'editor' : $lineUser->role;
                DB::table('users')->insert([
                    'name'         => $lineUser->display_name,
                    'email'        => null,
                    'password'     => null,
                    'source'       => 'line',
                    'role'         => $role,
                    'line_user_id' => $lineUser->line_user_id,
                    'picture_url'  => $lineUser->picture_url,
                    'created_at'   => $lineUser->created_at,
                    'updated_at'   => $lineUser->updated_at,
                ]);
            }

            // 5. 移除 line_users 表
            Schema::dropIfExists('line_users');
        }
    }

    public function down(): void
    {
        // 還原：重建 line_users 表
        Schema::create('line_users', function (Blueprint $table) {
            $table->id();
            $table->string('line_user_id')->unique();
            $table->string('display_name');
            $table->string('picture_url')->nullable();
            $table->enum('role', ['viewer', 'editor', 'admin'])->default('viewer');
            $table->timestamps();
        });

        // 將 source=line 的資料搬回 line_users
        $lineUsers = DB::table('users')->where('source', 'line')->get();
        foreach ($lineUsers as $user) {
            DB::table('line_users')->insert([
                'line_user_id' => $user->line_user_id,
                'display_name' => $user->name,
                'picture_url'  => $user->picture_url,
                'role'         => $user->role,
                'created_at'   => $user->created_at,
                'updated_at'   => $user->updated_at,
            ]);
        }

        // 刪除 line 使用者
        DB::table('users')->where('source', 'line')->delete();

        // 還原 users 表結構
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email');
            $table->dropColumn(['source', 'role', 'line_user_id', 'picture_url']);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
