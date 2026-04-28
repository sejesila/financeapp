<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate handled via $this->authorize() in the controller
    }

    public function rules(): array
    {
        return [
            'date'              => ['required', 'date'],
            'description'       => ['required', 'string'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'category_id'       => ['required', 'exists:categories,id'],
            'account_id'        => ['required', 'exists:accounts,id'],
            'mobile_money_type' => ['nullable', 'in:send_money,paybill,buy_goods,pochi_la_biashara'],
        ];
    }
}
