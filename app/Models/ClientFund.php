<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientFund extends Model
{
    protected $fillable = [
        'user_id',
        'client_name',
        'type',
        'amount_received',
        'amount_spent',
        'profit_amount',
        'balance',
        'status',
        'account_id',
        'purpose',
        'received_date',
        'completed_date',
        'notes',
    ];

    protected $casts = [
        'amount_received' => 'decimal:2',
        'amount_spent' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'received_date' => 'date',
        'completed_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions()
    {
        return $this->hasMany(ClientFundTransaction::class);
    }

    // Calculate remaining balance
    public function updateBalance()
    {
        $this->balance = $this->amount_received - $this->amount_spent - $this->profit_amount;

        if ($this->balance <= 0) {
            $this->status = 'completed';
            $this->completed_date = now();
        } elseif ($this->amount_spent > 0) {
            $this->status = 'partial';
        }

        $this->save();
    }
}
