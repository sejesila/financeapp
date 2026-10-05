<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Borrower extends Model
{
    protected $fillable = ['user_id', 'name', 'normalized_name', 'contact', 'notes'];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where('borrowers.user_id', Auth::id());
            }
        });
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    /** Find-or-create by name for the current user (use in LoanGivenController@store). */
    public static function resolve(string $name, ?string $contact = null): self
    {
        $b = self::firstOrCreate(
            ['user_id' => Auth::id(), 'normalized_name' => self::normalize($name)],
            ['name' => trim($name)]
        );

        if ($contact && !$b->contact) {
            $b->update(['contact' => $contact]);
        }

        return $b;
    }

    public function loans()
    {
        return $this->hasMany(LoanGiven::class, 'borrower_id');
    }
}
