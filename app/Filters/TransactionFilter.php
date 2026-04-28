<?php

namespace App\Filters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TransactionFilter
{
    public static function applyDateFilter(
        Builder $query,
        string  $filter,
        ?string $startDate = null,
        ?string $endDate   = null,
    ): Builder {
        if ($filter === 'custom' && $startDate && $endDate) {
            return $query->whereBetween('date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        return match ($filter) {
            'today'      => $query->whereDate('date', today()),
            'yesterday'  => $query->whereDate('date', today()->subDay()),
            'this_week'  => $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]),
            'last_week'  => $query->whereBetween('date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
            'this_month' => $query->whereMonth('date', now()->month)->whereYear('date', now()->year),
            'last_month' => $query->whereMonth('date', now()->subMonth()->month)->whereYear('date', now()->subMonth()->year),
            'this_year'  => $query->whereYear('date', now()->year),
            default      => $query,
        };
    }
}
