<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ClientFundTransaction extends Model
{
    protected $fillable = [
        'client_fund_id',
        'transaction_id',
        'transfer_id',
        'type',
        'is_borrowed',
        'amount',
        'date',
        'description',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_borrowed' => 'boolean',
    ];
    public function clientFund()
    {
        return $this->belongsTo(ClientFund::class);
    }
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}
