<?php
// app/Http/Requests/StoreTransactionRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'account_id' => 'required|exists:accounts,id',
            'mobile_money_type' => 'nullable|in:send_money,paybill,buy_goods,pochi_la_biashara',
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Transaction key is missing. Please refresh the page and try again.',
            'date.required' => 'Transaction date is required.',
            'description.required' => 'Transaction description is required.',
            'amount.required' => 'Transaction amount is required.',
            'amount.min' => 'Transaction amount must be greater than 0.',
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'Selected category is invalid.',
            'account_id.required' => 'Please select an account.',
            'account_id.exists' => 'Selected account is invalid.',
        ];
    }

    // Note: We don't validate uniqueness of idempotency_key here
    // The controller handles duplicate detection and returns the existing transaction
}
