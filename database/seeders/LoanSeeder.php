<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Seeder;

class LoanSeeder extends Seeder
{
    public function run(): void
    {
        $user    = User::where('email', 's@s.com')->first();
        $account = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('name', 'I&M Bank')
            ->first();

        $loans = [
            [
                'source'           => 'KCB Mpesa Loan',
                'principal_amount' => 5000,
                'interest_rate'    => 8.64,
                'interest_amount'  => 432,
                'total_amount'     => 5432,
                'amount_paid'      => 2000,
                'balance'          => 3432,
                'disbursed_date'   => now()->subMonths(3)->toDateString(),
                'due_date'         => now()->addMonths(3)->toDateString(),
                'status'           => 'active',
                'loan_type'        => 'mpesa',
            ],
            [
                'source'           => 'Fuliza',
                'principal_amount' => 5000,
                'interest_rate'    => 0,
                'interest_amount'  => 0,
                'total_amount'     => 5000,
                'amount_paid'      => 0,
                'balance'          => 5000,
                'disbursed_date'   => now()->subWeeks(2)->toDateString(),
                'due_date'         => now()->addWeeks(2)->toDateString(),
                'status'           => 'active',
                'loan_type'        => 'mpesa',
            ],
        ];

        foreach ($loans as $loan) {
            Loan::withoutGlobalScopes()->firstOrCreate(
                ['user_id' => $user->id, 'source' => $loan['source']],
                array_merge($loan, [
                    'user_id'    => $user->id,
                    'account_id' => $account->id,
                ])
            );
        }
    }
}
