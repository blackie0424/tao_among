<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FieldResearcherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $researchers = [
            [
                'name' => 'user1',
                'email' => 'user1@pongsonotao.org',
                'password' => 'meyrapa',
            ],
            [
                'name' => 'user2',
                'email' => 'user2@pongsonotao.org',
                'password' => 'meyrapa',
            ],
        ];

        foreach ($researchers as $researcher) {
            // 檢查是否已存在，不存在才建立
            if (! User::where('email', $researcher['email'])->exists()) {
                User::create([
                    'name' => $researcher['name'],
                    'email' => $researcher['email'],
                    'password' => Hash::make($researcher['password']),
                ]);
            }
        }
    }
}
