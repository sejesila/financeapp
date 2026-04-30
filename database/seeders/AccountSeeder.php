<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 's@s.com')->first();
        $accounts = [
            [
                'name'            => 'Mpesa',
                'slug'            => 'mpesa',
                'type'            => 'mpesa',
                'initial_balance' => 0,
                'current_balance' => 0,
                'currency'        => 'KES',
                'is_active'       => true,
            ],
            [
                'name'            => 'KCB Bank',
                'slug'            => 'kcb-bank',
                'type'            => 'bank',
                'initial_balance' => 0,
                'current_balance' => 0,
                'currency'        => 'KES',
                'is_active'       => true,
            ],
            [
                'name'            => 'Cash',
                'slug'            => 'cash',
                'type'            => 'cash',
                'initial_balance' => 0,
                'current_balance' => 0,
                'currency'        => 'KES',
                'is_active'       => true,
            ],
        ];


        foreach ($accounts as $data) {
            Account::withoutGlobalScopes()->firstOrCreate(
                ['user_id' => $user->id, 'name' => $data['name']],
                array_merge($data, ['user_id' => $user->id])
            );
        }
    }
}
