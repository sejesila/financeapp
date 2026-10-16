{{-- resources/views/loans-given/partials/yield-card.blade.php
     Include as the last child of the stats grid:  @include('loans-given.partials.yield-card')
     Expects $loanYield (array|null). --}}
<div class="col-span-2 lg:col-span-3 bg-gradient-to-br from-cyan-50 to-cyan-100 overflow-hidden shadow-sm rounded-lg border border-cyan-200">
    <div class="p-3 sm:p-4">
        <div class="flex items-center">
            <div class="hidden sm:block flex-shrink-0 bg-cyan-600 rounded-lg p-3">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <div class="sm:ml-4 min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 break-words">Return on Capital</p>

                @if(!empty($loanYield))
                    <p class="text-base sm:text-lg font-semibold text-gray-900">
                        {{ number_format($loanYield['period_return_pct'], 1) }}%
                    </p>
                    <p class="text-[10px] sm:text-xs leading-tight text-gray-500">
                        Net interest KES {{ number_format($loanYield['net_interest'], 0) }}
                        on avg KES {{ number_format($loanYield['avg_capital'], 0) }} out
                        · {{ $loanYield['days'] }} days
                    </p>
                    @if(($loanYield['write_offs'] ?? 0) > 0)
                        <p class="text-[10px] sm:text-xs leading-tight text-red-600 mt-0.5">
                            Includes KES {{ number_format($loanYield['write_offs'], 0) }} written off.
                        </p>
                    @endif
                    @if(($loanYield['at_risk'] ?? 0) > 0)
                        <p class="text-[10px] sm:text-xs leading-tight text-amber-600 mt-0.5">
                            KES {{ number_format($loanYield['at_risk'], 0) }} in defaulted loans not deducted.
                        </p>
                    @endif
                @else
                    <p class="text-base sm:text-lg font-semibold text-gray-900">-</p>
                    <p class="text-[10px] sm:text-xs leading-tight text-gray-500">No capital deployed in this period</p>
                @endif
            </div>
        </div>
    </div>
</div>
