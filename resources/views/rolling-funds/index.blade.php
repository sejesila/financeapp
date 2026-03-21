<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                        <span class="text-2xl">🎲</span>
                    </div>
                    <div>
                        <h2 class="font-bold text-xl sm:text-2xl text-gray-900 dark:text-gray-100">
                            Rolling Funds Tracker
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Monitor your investment performance
                        </p>
                    </div>
                </div>
            </div>
            <a href="{{ route('rolling-funds.create') }}"
               class="group w-full sm:w-auto bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-semibold text-sm text-center shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center justify-center gap-2">
                <svg class="w-5 h-5 transition-transform group-hover:rotate-90 duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Session
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-6">

            {{-- ── Alert Messages ─────────────────────────────────────────── --}}
            @if(session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-l-4 border-green-500 dark:border-green-400 p-4 rounded-xl shadow-md animate-slide-in">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-green-500 dark:bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border-l-4 border-red-500 dark:border-red-400 p-4 rounded-xl shadow-md animate-slide-in">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-red-500 dark:bg-red-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            {{-- Bankroll warning (80% threshold hit on last store) --}}
            @if(session('bankroll_warning'))
                <div class="bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 border-l-4 border-orange-500 dark:border-orange-400 p-4 rounded-xl shadow-md animate-slide-in">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-orange-500 dark:bg-orange-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-orange-800 dark:text-orange-300">⚠️ Bankroll Warning</p>
                            <p class="text-sm text-orange-700 dark:text-orange-400 mt-0.5">{{ session('bankroll_warning') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════════
                 BANKROLL MANAGEMENT PANEL
            ═══════════════════════════════════════════════════════════════ --}}
            @php
                $usagePercent    = $limit->exists ? $limit->monthlyUsagePercent() : null;
                $stakedSoFar     = $limit->exists ? $limit->monthlyStakedSoFar()  : 0;
                $monthlyRemain   = $limit->exists ? $limit->monthlyRemaining()     : null;
                $limitActive     = $limit->exists && $limit->is_active;

                // Determine progress-bar colour
                $barColor = 'bg-green-500';
                if ($usagePercent !== null) {
                    if ($usagePercent >= 100)      $barColor = 'bg-red-500';
                    elseif ($usagePercent >= 80)   $barColor = 'bg-orange-500';
                    elseif ($usagePercent >= 60)   $barColor = 'bg-yellow-500';
                }
            @endphp

            <div x-data="{ open: {{ $errors->has('monthly_stake_limit') || $errors->has('single_stake_limit') ? 'true' : 'false' }} }"
                 class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">

                {{-- Panel header / toggle --}}
                <button @click="open = !open"
                        type="button"
                        class="w-full flex items-center justify-between p-4 sm:p-5 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors duration-200 text-left">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-fuchsia-600 rounded-xl flex items-center justify-center shadow-md flex-shrink-0">
                            <span class="text-xl">🛡️</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm sm:text-base">Bankroll Management</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if($limitActive && $limit->monthly_stake_limit)
                                    This month: KES {{ number_format($stakedSoFar, 0) }}
                                    / {{ number_format($limit->monthly_stake_limit, 0) }}
                                    @if($usagePercent !== null)
                                        &nbsp;·&nbsp;
                                        <span class="{{ $usagePercent >= 100 ? 'text-red-600 dark:text-red-400 font-bold' : ($usagePercent >= 80 ? 'text-orange-600 dark:text-orange-400 font-bold' : 'text-gray-500 dark:text-gray-400') }}">
                                            {{ $usagePercent }}% used
                                        </span>
                                    @endif
                                @elseif($limitActive)
                                    Per-session cap active
                                @else
                                    No limits set — click to configure
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        {{-- Status badge --}}
                        @if($limitActive)
                            <span class="hidden sm:inline text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30 px-2.5 py-1 rounded-full">
                                Active
                            </span>
                        @else
                            <span class="hidden sm:inline text-xs font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-full">
                                Inactive
                            </span>
                        @endif
                        <svg class="w-5 h-5 text-gray-400 transition-transform duration-300"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </button>

                {{-- Progress bar (always visible when limit set & active) --}}
                @if($limitActive && $limit->monthly_stake_limit && $usagePercent !== null)
                    <div class="px-4 sm:px-5 -mt-1">
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                            <div class="{{ $barColor }} h-2 rounded-full transition-all duration-500"
                                 style="width: {{ min(100, $usagePercent) }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Expandable body --}}
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="border-t border-gray-200 dark:border-gray-700">

                    {{-- Current-month stats strip --}}
                    @if($limitActive && $limit->monthly_stake_limit)
                        <div class="grid grid-cols-3 divide-x divide-gray-200 dark:divide-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <div class="p-3 sm:p-4 text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide mb-1">Staked</p>
                                <p class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">
                                    KES {{ number_format($stakedSoFar, 0) }}
                                </p>
                            </div>
                            <div class="p-3 sm:p-4 text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide mb-1">Remaining</p>
                                <p class="text-base sm:text-lg font-bold {{ ($monthlyRemain !== null && $monthlyRemain <= 0) ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    KES {{ $monthlyRemain !== null ? number_format($monthlyRemain, 0) : '∞' }}
                                </p>
                            </div>
                            <div class="p-3 sm:p-4 text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide mb-1">Budget</p>
                                <p class="text-base sm:text-lg font-bold text-indigo-600 dark:text-indigo-400">
                                    KES {{ number_format($limit->monthly_stake_limit, 0) }}
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- ── Limit settings form ──────────────────────────────── --}}
                    <form method="POST" action="{{ route('rolling-funds.save-limits') }}"
                          class="p-4 sm:p-6 space-y-5">
                        @csrf

                        {{-- Enable / disable toggle --}}
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/40 rounded-xl">
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Enable bankroll limits</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Turn off to bypass all limits temporarily</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="sr-only peer"
                                    {{ ($limit->exists && $limit->is_active) || !$limit->exists ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-300 dark:bg-gray-600 peer-checked:bg-indigo-600 rounded-full peer
                                            after:content-[''] after:absolute after:top-0.5 after:left-0.5
                                            after:bg-white after:rounded-full after:h-5 after:w-5
                                            after:transition-all peer-checked:after:translate-x-5
                                            transition-colors duration-200"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- Monthly stake limit --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                                    Monthly Stake Limit
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(KES)</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 dark:text-gray-400 font-semibold text-sm pointer-events-none">
                                        KES
                                    </span>
                                    <input type="number"
                                           name="monthly_stake_limit"
                                           min="0"
                                           step="100"
                                           placeholder="e.g. 5000"
                                           value="{{ old('monthly_stake_limit', $limit->monthly_stake_limit ?? '') }}"
                                           class="w-full pl-12 pr-4 py-2.5 bg-white dark:bg-gray-700 border @error('monthly_stake_limit') border-red-500 @else border-gray-300 dark:border-gray-600 @enderror rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                                </div>
                                @error('monthly_stake_limit')
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Max total you can stake in one calendar month. Leave blank for no limit.
                                </p>
                            </div>

                            {{-- Single stake limit --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                                    Max Per Session
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(KES)</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 dark:text-gray-400 font-semibold text-sm pointer-events-none">
                                        KES
                                    </span>
                                    <input type="number"
                                           name="single_stake_limit"
                                           min="0"
                                           step="100"
                                           placeholder="e.g. 1000"
                                           value="{{ old('single_stake_limit', $limit->single_stake_limit ?? '') }}"
                                           class="w-full pl-12 pr-4 py-2.5 bg-white dark:bg-gray-700 border @error('single_stake_limit') border-red-500 @else border-gray-300 dark:border-gray-600 @enderror rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                                </div>
                                @error('single_stake_limit')
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Max you can stake in a single session. Leave blank for no limit.
                                </p>
                            </div>
                        </div>

                        {{-- Behaviour info pills --}}
                        <div class="flex flex-wrap gap-2">
                            <div class="flex items-center gap-1.5 text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 px-3 py-1.5 rounded-full font-semibold">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Warning at 80%
                            </div>
                            <div class="flex items-center gap-1.5 text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 px-3 py-1.5 rounded-full font-semibold">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524L13.477 14.89zm1.414-1.414L6.524 5.11A6 6 0 0114.89 13.476zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                                </svg>
                                Blocked at 100%
                            </div>
                            <div class="flex items-center gap-1.5 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 px-3 py-1.5 rounded-full font-semibold">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                </svg>
                                Resets each month
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="px-6 py-2.5 bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-700 hover:to-fuchsia-700 text-white rounded-xl text-sm font-bold shadow-md hover:shadow-lg transition-all duration-300">
                                Save Limits
                            </button>
                        </div>
                    </form>

                </div>{{-- /expandable body --}}
            </div>{{-- /bankroll panel --}}

            {{-- ── Key Metrics ──────────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                <!-- Total Invested Card -->
                <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-indigo-500/10 dark:from-blue-500/5 dark:to-indigo-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <span class="text-2xl">💼</span>
                            </div>
                            <div class="text-xs font-semibold text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 px-2 py-1 rounded-lg">TOTAL</div>
                        </div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Total Invested</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_staked'], 0) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">KES</p>
                    </div>
                </div>

                <!-- Total Returns Card -->
                <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 to-green-500/10 dark:from-emerald-500/5 dark:to-green-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <span class="text-2xl">💰</span>
                            </div>
                            <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30 px-2 py-1 rounded-lg">RETURNS</div>
                        </div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Total Returns</p>
                        <p class="text-2xl sm:text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($stats['total_winnings'], 0) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">KES</p>
                    </div>
                </div>

                <!-- Net P/L Card -->
                <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute inset-0 bg-gradient-to-br {{ $stats['net_profit_loss'] >= 0 ? 'from-green-500/10 to-emerald-500/10 dark:from-green-500/5 dark:to-emerald-500/5' : 'from-red-500/10 to-rose-500/10 dark:from-red-500/5 dark:to-rose-500/5' }} opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br {{ $stats['net_profit_loss'] >= 0 ? 'from-green-500 to-emerald-600' : 'from-red-500 to-rose-600' }} rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <span class="text-2xl">{{ $stats['net_profit_loss'] >= 0 ? '📈' : '📉' }}</span>
                            </div>
                            <div class="text-xs font-semibold {{ $stats['net_profit_loss'] >= 0 ? 'text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900/30' : 'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30' }} px-2 py-1 rounded-lg">
                                {{ $stats['net_profit_loss'] >= 0 ? 'PROFIT' : 'LOSS' }}
                            </div>
                        </div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Net P/L</p>
                        <p class="text-2xl sm:text-3xl font-bold {{ $stats['net_profit_loss'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $stats['net_profit_loss'] >= 0 ? '+' : '' }}{{ number_format($stats['net_profit_loss'], 0) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">KES</p>
                    </div>
                </div>

                <!-- Success Rate Card -->
                <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/10 to-indigo-500/10 dark:from-purple-500/5 dark:to-indigo-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <span class="text-2xl">🎯</span>
                            </div>
                            <div class="text-xs font-semibold text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-900/30 px-2 py-1 rounded-lg">RATE</div>
                        </div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Success Rate</p>
                        <p class="text-2xl sm:text-3xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['win_rate'] }}%</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">{{ $stats['wins'] }}W / {{ $stats['losses'] }}L</p>
                    </div>
                </div>
            </div>

            {{-- ── Pending Sessions Alert ───────────────────────────────────── --}}
            @if($stats['pending_count'] > 0)
                <div class="relative bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-2xl shadow-md overflow-hidden border-2 border-amber-300 dark:border-amber-700">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-400/10 rounded-full -mr-32 -mt-32"></div>
                    <div class="relative p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-start gap-3 sm:gap-4 flex-1 w-full">
                                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
                                    <span class="text-2xl sm:text-3xl">⏳</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-base sm:text-lg font-bold text-amber-900 dark:text-amber-300 mb-1">
                                        {{ $stats['pending_count'] }} Pending Session{{ $stats['pending_count'] > 1 ? 's' : '' }}
                                    </p>
                                    <p class="text-sm text-amber-700 dark:text-amber-400">
                                        Total pending: <span class="font-bold">KES {{ number_format($stats['pending_amount'], 0) }}</span>
                                    </p>
                                    <p class="text-xs text-amber-600 dark:text-amber-500 mt-2">
                                        Update outcomes to track your complete performance
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'pending']) }}"
                               class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-xl text-sm font-bold shadow-lg hover:shadow-xl transition-all duration-300 text-center flex-shrink-0">
                                View All
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Filters Section ─────────────────────────────────────────── --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700">
                <div class="p-4 sm:p-5 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-gray-100">Filter Sessions</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Refine your view</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5 space-y-4 sm:space-y-5">
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Status</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('rolling-funds.index') }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 {{ $filter === 'all' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                All Sessions
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'pending']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'pending' ? 'bg-amber-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>⏳</span> Pending
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'completed']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'completed' ? 'bg-blue-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>✓</span> Completed
                            </a>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Time Period</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('rolling-funds.index', ['filter' => 'last_7_days']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'last_7_days' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📅</span> <span class="hidden xs:inline">Last</span> 7d
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'last_30_days']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'last_30_days' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📅</span> <span class="hidden xs:inline">Last</span> 30d
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'last_90_days']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'last_90_days' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📅</span> <span class="hidden xs:inline">Last</span> 90d
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'this_month']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'this_month' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📅</span> This Month
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'this_year']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'this_year' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📆</span> This Year
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'last_year']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'last_year' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📆</span> Last Year
                            </a>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Performance</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('rolling-funds.index', ['filter' => 'wins']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'wins' ? 'bg-green-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📈</span> Positive
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'losses']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'losses' ? 'bg-red-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📉</span> Negative
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'break_even']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'break_even' ? 'bg-gray-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>➖</span> Break Even
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'high_wins']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'high_wins' ? 'bg-emerald-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>🚀</span> High Wins
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'significant_losses']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'significant_losses' ? 'bg-rose-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>⚠️</span> Big Losses
                            </a>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Custom Range</p>
                        <form method="GET" action="{{ route('rolling-funds.index') }}" class="flex flex-col sm:flex-row flex-wrap gap-2 items-stretch sm:items-end">
                            <input type="hidden" name="filter" value="custom_range">
                            <div class="flex-1 min-w-[140px]">
                                <label class="text-xs text-gray-600 dark:text-gray-400 font-medium block mb-1">From</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}"
                                       class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            <div class="flex-1 min-w-[140px]">
                                <label class="text-xs text-gray-600 dark:text-gray-400 font-medium block mb-1">To</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}"
                                       class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        class="flex-1 sm:flex-none px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg text-sm font-semibold shadow-md hover:shadow-lg transition-all duration-300">
                                    Apply
                                </button>
                                @if($filter === 'custom_range')
                                    <a href="{{ route('rolling-funds.index') }}"
                                       class="flex-1 sm:flex-none px-4 py-2 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-semibold transition-all duration-300 text-center">
                                        Clear
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ── Sessions List ───────────────────────────────────────────── --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md overflow-hidden border border-gray-100 dark:border-gray-700">
                <div class="p-4 sm:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800 dark:to-gray-800/50">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
                                <span class="text-xl sm:text-2xl">📊</span>
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">Session History</h3>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                    {{ $wagers->total() }} session{{ $wagers->total() !== 1 ? 's' : '' }} recorded
                                </p>
                            </div>
                        </div>
                        @if($wagers->hasPages())
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                Page {{ $wagers->currentPage() }} of {{ $wagers->lastPage() }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($wagers as $wager)
                        <div class="group p-4 sm:p-6 hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent dark:hover:from-gray-700/20 dark:hover:to-transparent transition-all duration-200">
                            <div class="flex flex-col lg:flex-row items-start lg:items-start justify-between gap-4 lg:gap-6">
                                <div class="flex-1 min-w-0 w-full">
                                    <div class="flex items-start gap-3 mb-3 flex-wrap">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-md flex-shrink-0 {{ $wager->status === 'pending' ? 'bg-gradient-to-br from-amber-400 to-orange-500' : ($wager->isWin() ? 'bg-gradient-to-br from-green-500 to-emerald-600' : ($wager->outcome === 'break_even' ? 'bg-gradient-to-br from-gray-400 to-gray-500' : 'bg-gradient-to-br from-red-500 to-rose-600')) }}">
                                            <span class="text-xl">
                                                @if($wager->status === 'pending') ⏳
                                                @elseif($wager->isWin()) 📈
                                                @elseif($wager->outcome === 'break_even') ➖
                                                @else 📉
                                                @endif
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('rolling-funds.show', $wager) }}"
                                                   class="font-bold text-sm sm:text-base text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors break-words">
                                                    {{ $wager->platform ?? 'Rolling Funds' }}
                                                </a>
                                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-lg whitespace-nowrap">
                                                    {{ $wager->date->format('M d, Y') }}
                                                </span>
                                                @if($wager->status === 'pending')
                                                    <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-3 py-1 rounded-full font-bold">Pending</span>
                                                @endif
                                                @if($wager->account)
                                                    <span class="text-xs bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 px-3 py-1 rounded-full font-semibold">
                                                        {{ $wager->account->name }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-2 ml-0 sm:ml-13">
                                        <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-xs sm:text-sm">
                                            <div class="flex items-center gap-2">
                                                <span class="text-gray-500 dark:text-gray-400 font-medium">Invested:</span>
                                                <span class="font-bold text-gray-900 dark:text-gray-100">KES {{ number_format($wager->stake_amount, 0) }}</span>
                                            </div>
                                            <span class="text-gray-400 hidden sm:inline">•</span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-gray-500 dark:text-gray-400 font-medium">Returns:</span>
                                                @if($wager->status === 'completed')
                                                    <span class="font-bold text-gray-900 dark:text-gray-100">KES {{ number_format($wager->winnings, 0) }}</span>
                                                @else
                                                    <span class="text-amber-600 dark:text-amber-400 font-bold">Pending</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-full lg:w-auto lg:text-right flex-shrink-0">
                                    @if($wager->status === 'completed')
                                        <div class="bg-gradient-to-br {{ $wager->net_result >= 0 ? 'from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20' : 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20' }} rounded-xl p-4 shadow-sm">
                                            <p class="text-xl sm:text-2xl font-bold {{ $wager->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $wager->net_result >= 0 ? '+' : '' }}{{ number_format($wager->net_result, 0) }}
                                            </p>
                                            <p class="text-xs font-bold {{ $wager->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} mt-1">
                                                {{ $wager->profit_percentage >= 0 ? '+' : '' }}{{ $wager->profit_percentage }}%
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">KES</p>
                                        </div>
                                    @else
                                        <div class="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-xl p-4 shadow-sm">
                                            <p class="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400">Pending</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Awaiting outcome</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-3 ml-0 sm:ml-13">
                                <a href="{{ route('rolling-funds.show', $wager) }}"
                                   class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors group">
                                    View Details
                                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                                @if($wager->status === 'pending')
                                    <a href="{{ route('rolling-funds.show', $wager) }}"
                                       class="inline-flex items-center gap-1.5 text-xs font-bold text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Record Outcome
                                    </a>
                                @endif
                                <form action="{{ route('rolling-funds.destroy', $wager) }}" method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this session? This will also remove all related transactions.')"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 sm:p-16 text-center">
                            <div class="mb-6">
                                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-3xl flex items-center justify-center mx-auto shadow-lg">
                                    <span class="text-4xl sm:text-5xl">📊</span>
                                </div>
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">No Sessions Yet</h3>
                            <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
                                Start tracking your rolling funds investments to monitor your performance and returns.
                            </p>
                            <a href="{{ route('rolling-funds.create') }}"
                               class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 sm:px-8 py-3 sm:py-3.5 rounded-xl font-bold text-sm shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Record Your First Session
                            </a>
                        </div>
                    @endforelse
                </div>

                @if($wagers->hasPages())
                    <div class="p-4 sm:p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        {{ $wagers->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>

    <style>
        @keyframes slide-in {
            from { transform: translateX(-100%); opacity: 0; }
            to   { transform: translateX(0);     opacity: 1; }
        }
        .animate-slide-in { animation: slide-in 0.3s ease-out; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(156,163,175,0.5); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(107,114,128,0.7); }
        .dark ::-webkit-scrollbar-thumb { background: rgba(75,85,99,0.5); }
        .dark ::-webkit-scrollbar-thumb:hover { background: rgba(55,65,81,0.7); }
        @media (min-width: 475px) { .xs\:inline { display: inline; } }
    </style>
</x-app-layout>
