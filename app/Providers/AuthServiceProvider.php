<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Transaction;
use App\Policies\AccountPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\LoanPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Account::class => AccountPolicy::class,
        Loan::class => LoanPolicy::class,
        Transaction::class => TransactionPolicy::class,
        Category::class => CategoryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
