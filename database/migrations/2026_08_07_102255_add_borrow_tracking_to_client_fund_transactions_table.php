<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_fund_transactions', function (Blueprint $table) {
            // Links a "borrowed" entry back to the Transfer that caused it.
            // Nullable because normal receipt/expense/profit entries are
            // still tied to a real Transaction, not a Transfer.
            $table->foreignId('transfer_id')
                ->nullable()
                ->after('transaction_id')
                ->constrained('transfers')
                ->nullOnDelete();

            // Distinguishes "money borrowed from this client fund for a
            // personal transfer" from a genuine client expense/profit entry,
            // even though both are stored with type = 'expense'.
            $table->boolean('is_borrowed')->default(false)->after('type');
        });

        // A borrow entry isn't tied to a ledger Transaction row (the money
        // already leaves the account via the Transfer itself), so
        // transaction_id must become nullable. Raw SQL here avoids adding a
        // doctrine/dbal dependency just for one column change.
        DB::statement('ALTER TABLE client_fund_transactions MODIFY transaction_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('client_fund_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfer_id');
            $table->dropColumn('is_borrowed');
        });

        DB::statement('ALTER TABLE client_fund_transactions MODIFY transaction_id BIGINT UNSIGNED NOT NULL');
    }
};
