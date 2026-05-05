<?php
// app/Services/CategoryResolver.php

namespace App\Services;

use App\Models\Category;
use App\Models\User;

class CategoryResolver
{
    /**
     * Derive the human-readable category name from a parsed SMS array.
     */
    public function resolveName(array $parsed): string
    {
        if ($parsed['bank'] === 'im_bank') {
            return match ($parsed['subtype']) {
                'bank_to_mpesa' => 'Other Expenses',
                'bank_received' => 'Side Income',
                default         => 'Other Expenses',
            };
        }

        if ($parsed['subtype'] === 'paybill') {
            return $this->resolvePaybill(
                $parsed['recipient']       ?? '',
                $parsed['paybill_account'] ?? ''
            );
        }

        if (in_array($parsed['subtype'], ['till', 'buy_goods'])) {
            return $this->resolveTill($parsed['recipient'] ?? '');
        }

        return match ($parsed['subtype']) {
            'receive_money' => 'Side Income',
            'send_money'    => 'Other Expenses',
            'withdrawal'    => 'Other Expenses',
            'airtime'       => 'Airtime & Data',
            default         => 'Groceries',
        };
    }

    /**
     * Find an existing category for the user or create one on the fly.
     *
     * Lookup priority:
     *  1. Child category (has parent_id) — avoids grabbing a bare parent node
     *  2. Root-level category
     *  3. Create a new root-level category
     *
     * Special case: "Side Income" is always looked up under its "Income" parent
     * first so it stays nested correctly in the user's category tree.
     */
    public function findOrCreate(User $user, string $name, string $type): Category
    {
        $expectedType = $type === 'income' ? 'income' : 'expense';

        if ($name === 'Side Income' && $expectedType === 'income') {
            $category = $this->findSideIncomeUnderParent($user);
            if ($category) {
                return $category;
            }
        }

        // Prefer a child category so we don't accidentally attach transactions
        // to a parent/container node.
        $child = Category::where('user_id', $user->id)
            ->where('name', $name)
            ->where('type', $expectedType)
            ->whereNotNull('parent_id')
            ->first();

        if ($child) {
            return $child;
        }

        $root = Category::where('user_id', $user->id)
            ->where('name', $name)
            ->where('type', $expectedType)
            ->whereNull('parent_id')
            ->first();

        if ($root) {
            return $root;
        }

        return Category::create([
            'user_id'   => $user->id,
            'name'      => $name,
            'type'      => $expectedType,
            'is_active' => true,
            'parent_id' => null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    private function findSideIncomeUnderParent(User $user): ?Category
    {
        $parent = Category::where('user_id', $user->id)
            ->where('name', 'Income')
            ->where('type', 'income')
            ->whereNull('parent_id')
            ->first();

        if (!$parent) {
            return null;
        }

        return Category::where('user_id', $user->id)
            ->where('name', 'Side Income')
            ->where('type', 'income')
            ->where('parent_id', $parent->id)
            ->first();
    }

    private function resolvePaybill(string $recipient, string $accountNo = ''): string
    {
        $r = strtolower($recipient);

        if (str_contains($r, 'kplc')) {
            return 'Electricity';
        }

        if (str_contains($r, 'kcb') && $accountNo === '6330444') {
            return 'Rent';
        }

        if (str_contains($r, 'safaricom') || str_contains($r, 'zuku')
            || str_contains($r, 'faiba') || str_contains($r, 'airtel')) {
            return 'Internet and Communication';
        }

        if (str_contains($r, 'co-operative') && $accountNo === '1040616#0889') {
            return 'School Fees';
        }

        return 'Groceries';
    }

    private function resolveTill(string $recipient): string
    {
        $r = strtolower($recipient);

        if (str_contains($r, 'naivas') || str_contains($r, 'quick mart')
            || str_contains($r, 'vuno') || str_contains($r, 'jeremiah mutuku')
            || str_contains($r, 'waeconmatt')) {
            return 'Groceries';
        }

        return 'Groceries';
    }
}
