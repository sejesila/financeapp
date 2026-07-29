<?php

namespace Database\Seeders;

use App\Models\Referrer;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReferrerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 's@s.com')->first();

        if (!$user) {
            $this->command->warn('No user found — skipping ReferrerSeeder.');
            return;
        }

        Referrer::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'David'],
            ['default_share_percentage' => 40.00, 'is_active' => true]
        );

        Referrer::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Phides'],
            ['default_share_percentage' => 40.00, 'is_active' => true]
        );
    }
}
