<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TransactionSplit extends Model
{
    protected $fillable = [
        'transaction_id',
        'account_id',
        'amount',
        'payment_method',
        'mobile_money_type',
        'related_fee_transaction_id',
    ];

    public function transaction()   { return $this->belongsTo(Transaction::class); }
    public function account()       { return $this->belongsTo(Account::class); }
    public function feeTransaction() { return $this->belongsTo(Transaction::class, 'related_fee_transaction_id'); }
}
