<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <!-- Flash Messages -->
                    @if(session('success'))
                        <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
                            <p class="text-sm text-red-700">{{ session('error') }}</p>
                        </div>
                    @endif

                    <!-- Header -->
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold text-gray-800">Loans Given</h1>
                        <a href="{{ route('loans-given.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 4v16m8-8H4"></path>
                            </svg>
                            New Loan
                        </a>
                    </div>

                    <!-- Statistics Dashboard -->
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                        <!-- Total Outstanding (NEW) -->
                        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 overflow-hidden shadow-sm rounded-lg border border-indigo-200">
                            <div class="p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-indigo-500 rounded-lg p-3">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Total Outstanding</p>
                                        <p class="text-lg font-semibold text-gray-900">
                                            KES {{ number_format($totalOutstanding ?? 0, 0) }}</p>
                                        <p class="text-xs text-gray-500">Across active loans</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Total Principal -->
                        <div
                            class="bg-gradient-to-br from-blue-50 to-blue-100 overflow-hidden shadow-sm rounded-lg border border-blue-200">
                            <div class="p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-blue-500 rounded-lg p-3">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v1m0 1v1m0 1v1m0 1v1"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Total Principal</p>
                                        <p class="text-lg font-semibold text-gray-900">
                                            KES {{ number_format($totalPrincipal ?? 0, 0) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Repaid -->
                        <div
                            class="bg-gradient-to-br from-green-50 to-green-100 overflow-hidden shadow-sm rounded-lg border border-green-200">
                            <div class="p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-green-500 rounded-lg p-3">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Total Repaid</p>
                                        <p class="text-lg font-semibold text-gray-900">
                                            KES {{ number_format($totalRepaid ?? 0, 0) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Interest -->
                        <div
                            class="bg-gradient-to-br from-purple-50 to-purple-100 overflow-hidden shadow-sm rounded-lg border border-purple-200">
                            <div class="p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-purple-500 rounded-lg p-3">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Interest Earned</p>
                                        <p class="text-lg font-semibold text-gray-900">
                                            KES {{ number_format($totalInterest ?? 0, 0) }}</p>
                                        <p class="text-xs text-gray-500">From closed loans</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Average Interest -->
                        <div
                            class="bg-gradient-to-br from-pink-50 to-pink-100 overflow-hidden shadow-sm rounded-lg border border-pink-200">
                            <div class="p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-pink-500 rounded-lg p-3">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Avg Interest Rate</p>
                                        <p class="text-lg font-semibold text-gray-900">{{ number_format($avgInterestRate ?? 0, 1) }}
                                            %</p>
                                        <p class="text-xs text-gray-500">Across closed loans</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Repayment Rate -->
                        <div
                            class="bg-gradient-to-br from-yellow-50 to-yellow-100 overflow-hidden shadow-sm rounded-lg border border-yellow-200">
                            <div class="p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-yellow-500 rounded-lg p-3">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500">Repayment Rate</p>
                                        <p class="text-lg font-semibold text-gray-900">{{ number_format($repaymentRate ?? 0, 1) }}
                                            %</p>
                                        <p class="text-xs text-gray-500">{{ $paidLoans->total() }}
                                            of {{ $activeLoans->count() + $paidLoans->total() }} loans</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex space-x-8">
                            <a href="{{ route('loans-given.index', ['filter' => 'active']) }}"
                               class="{{ $filter === 'active' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Active Loans
                                <span
                                    class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    {{ $activeLoans->count() }}
                                </span>
                            </a>
                            <a href="{{ route('loans-given.index', ['filter' => 'paid']) }}"
                               class="{{ $filter === 'paid' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Paid Loans
                                <span
                                    class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $paidLoans->total() }}
                                </span>
                            </a>
                        </nav>
                    </div>

                    <!-- Active Loans Section -->
                    @if($filter === 'active')
                        <div class="flex justify-end gap-3 mb-4">
                            <!-- Referrer filter -->
                            <div x-data="{
                                     open: false,
                                     referrerId: '{{ $referrerId ?? '' }}',
                                     labels: {
                                         '': 'All Referrers',
                                         @foreach($referrers as $referrer)
                                             '{{ $referrer->id }}': '{{ $referrer->name }}',
                                         @endforeach
                                     },
                                     select(value) {
                                         this.referrerId = value;
                                         this.open = false;
                                         window.location = '{{ route('loans-given.index') }}?filter=active&sort={{ $sort ?? 'date_desc' }}&referrer_id=' + value;
                                     }
                                 }"
                                 @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false"
                                 class="relative w-52">
                                <button type="button" @click="open = !open"
                                        class="w-full rounded-md border border-gray-300 shadow-sm text-sm px-3 py-2 text-left flex justify-between items-center gap-2 bg-white focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span x-text="labels[referrerId]"></span>
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <ul x-show="open" x-transition x-cloak
                                    class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg text-sm overflow-hidden">
                                    <template x-for="(label, value) in labels" :key="value">
                                        <li @click="select(value)"
                                            :class="{ 'bg-indigo-50': referrerId === value }"
                                            class="px-3 py-2 cursor-pointer hover:bg-indigo-100"
                                            x-text="label"></li>
                                    </template>
                                </ul>
                            </div>

                            <!-- Sort -->
                            <div x-data="{
                                     open: false,
                                     sort: '{{ $sort ?? 'date_desc' }}',
                                     labels: { date_desc: 'Date Given (Newest)', referrer: 'Referrer (A-Z)' },
                                     select(value) {
                                         this.sort = value;
                                         this.open = false;
                                         window.location = '{{ route('loans-given.index') }}?filter=active&sort=' + value + '&referrer_id={{ $referrerId ?? '' }}';
                                     }
                                 }"
                                 @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false"
                                 class="relative w-52">
                                <button type="button" @click="open = !open"
                                        class="w-full rounded-md border border-gray-300 shadow-sm text-sm px-3 py-2 text-left flex justify-between items-center gap-2 bg-white focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span>Sort: <span x-text="labels[sort]"></span></span>
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <ul x-show="open" x-transition x-cloak
                                    class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg text-sm overflow-hidden">
                                    <template x-for="(label, value) in labels" :key="value">
                                        <li @click="select(value)"
                                            :class="{ 'bg-indigo-50': sort === value }"
                                            class="px-3 py-2 cursor-pointer hover:bg-indigo-100"
                                            x-text="label"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        @if($activeLoans->isEmpty())
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">No active loans found.</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach($activeLoans as $loan)
                                    <div
                                        class="bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200 flex flex-col h-full">
                                        <div class="p-5 flex-1">
                                            <div class="flex justify-between items-start mb-3">
                                                <h3 class="text-lg font-medium text-gray-900">{{ $loan->borrower_name }}</h3>
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            </div>
                                            @if($loan->referrer)
                                                <div class="mb-3">
                                                    <a href="{{ route('referrers.show', $loan->referrer->id) }}"
                                                       class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 hover:bg-purple-200 transition">
                                                        Referred by {{ $loan->referrer->name }}
                                                        @if($loan->referrer_share_percentage !== null)
                                                            &middot; {{ number_format($loan->referrer_share_percentage, 1) }}
                                                        %
                                                        @endif
                                                    </a>
                                                </div>
                                            @endif
                                            <div class="space-y-2 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Principal:</span>
                                                    <span
                                                        class="font-medium">KES {{ number_format($loan->principal_amount, 0) }}</span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Outstanding:</span>
                                                    <span
                                                        class="font-bold text-indigo-600">KES {{ number_format($loan->balance, 0) }}</span>
                                                </div>
                                                @if($loan->amount_paid > 0)
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-500">Received:</span>
                                                        <span
                                                            class="text-green-600">KES {{ number_format($loan->amount_paid, 0) }}</span>
                                                    </div>
                                                @endif
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Due Date:</span>
                                                    <span>
                                                        @if($loan->due_date)
                                                            {{ $loan->due_date->format('M d, Y') }}
                                                            @if($loan->isOverdue())
                                                                <span
                                                                    class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                                    Overdue
                                                                </span>
                                                            @endif
                                                        @else
                                                            Not set
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Disbursed:</span>
                                                    <span>{{ $loan->disbursed_date->format('M d, Y') }}</span>
                                                </div>
                                            </div>
                                            @if($loan->notes)
                                                <div class="mt-3 text-xs text-gray-500">
                                                    <svg class="inline w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    {{ Str::limit($loan->notes, 50) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="bg-gray-50 px-5 py-3 rounded-b-lg border-t border-gray-200 mt-auto">
                                            <div class="flex space-x-2">
                                                <a href="{{ route('loans-given.show', $loan->id) }}"
                                                   class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    View
                                                </a>
                                                <a href="{{ route('loans-given.payment', $loan->id) }}"
                                                   class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent shadow-sm text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition ease-in-out duration-150">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v1m0 1v1m0 1v1m0 1v1"></path>
                                                    </svg>
                                                    Payment
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Paid Loans Section -->
                    @else
                        <div class="bg-white rounded-lg border border-gray-200">
                            <div class="p-4 border-b border-gray-200">
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <h2 class="text-lg font-medium text-gray-900">Paid Loans</h2>

                                    <form method="GET"
                                          action="{{ route('loans-given.index') }}"
                                          x-data="{
                                              open: false,
                                              period: '{{ $period ?: '' }}',
                                              labels: {
                                                  '':           'All Time',
                                                  this_month:   'This Month',
                                                  last_month:   'Last Month',
                                                  this_year:    'This Year',
                                                  last_year:    'Last Year',
                                                  custom:       'Custom Range',
                                              },
                                              select(value) {
                                                  this.period = value;
                                                  this.open = false;
                                                  if (value !== 'custom') {
                                                      window.location = '{{ route('loans-given.index') }}?filter=paid&period=' + value;
                                                  }
                                              }
                                          }"
                                          class="flex flex-wrap items-center gap-2">

                                        <input type="hidden" name="filter" value="paid">
                                        <input type="hidden" name="period" :value="period">

                                        <div @mouseenter="open = true"
                                             @mouseleave="open = false"
                                             @click.outside="open = false"
                                             class="relative w-44">
                                            <button type="button"
                                                    @click="open = !open"
                                                    class="w-full rounded-md border border-gray-300 shadow-sm text-sm px-3 py-2 text-left flex justify-between items-center gap-2 bg-white focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <span x-text="labels[period]"></span>
                                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>

                                            <ul x-show="open"
                                                x-transition
                                                x-cloak
                                                class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg text-sm overflow-hidden">
                                                <template x-for="(label, value) in labels" :key="value">
                                                    <li @click="select(value)"
                                                        :class="{ 'bg-indigo-50': period === value }"
                                                        class="px-3 py-2 cursor-pointer hover:bg-indigo-100"
                                                        x-text="label">
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>

                                        <template x-if="period === 'custom'">
                                            <div class="flex items-center gap-2">
                                                <input type="date" name="start_date"
                                                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm"
                                                       value="{{ $startDate }}">
                                                <input type="date" name="end_date"
                                                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm"
                                                       value="{{ $endDate }}">
                                                <button type="submit"
                                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                    Apply
                                                </button>
                                            </div>
                                        </template>
                                    </form>
                                </div>
                            </div>

                            @if($paidLoans->isEmpty())
                                <div class="p-6">
                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-blue-700">No paid loans found.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Borrower
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Principal
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Interest
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Referrer
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Total Received
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Repaid Date
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($paidLoans as $loan)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $loan->borrower_name }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    KES {{ number_format($loan->principal_amount, 0) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    @if($loan->interest_amount > 0)
                                                        KES {{ number_format($loan->interest_amount, 0) }}
                                                        ({{ number_format($loan->interest_rate, 1) }}%)
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    @if($loan->referrer)
                                                        <a href="{{ route('referrers.show', $loan->referrer->id) }}"
                                                           class="text-indigo-600 hover:text-indigo-900">
                                                            {{ $loan->referrer->name }}
                                                        </a>
                                                        @if($loan->referrer_deducted_before_deposit)
                                                            <span class="block text-xs text-gray-500">
            KES {{ number_format($loan->referrer_retained_amount, 0) }} retain</span>
                                                        @elseif($loan->interest_amount > 0 && $loan->referrer_share_percentage !== null)
                                                            <span class="block text-xs text-purple-600">
                KES {{ number_format($loan->interest_amount * ($loan->referrer_share_percentage / 100), 0) }}
                ({{ number_format($loan->referrer_share_percentage, 1) }}%)
            </span>
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                                    KES {{ number_format($loan->amount_paid, 0) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $loan->repaid_date ? $loan->repaid_date->format('M d, Y') : '-' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <a href="{{ route('loans-given.show', $loan->id) }}"
                                                       class="text-indigo-600 hover:text-indigo-900">
                                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  stroke-width="2"
                                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  stroke-width="2"
                                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-6 py-4 border-t border-gray-200">
                                    {{ $paidLoans->links() }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
