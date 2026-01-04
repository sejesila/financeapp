{{-- Floating Action Button Component --}}
<div x-data="{ open: false }" class="fixed bottom-6 right-6 z-50">
    {{-- Action Menu (appears when open) --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.away="open = false"
         class="absolute bottom-16 right-0 mb-2 space-y-3">

        {{-- New Transaction --}}
        <a href="{{ route('transactions.create') }}"
           class="flex items-center gap-3 bg-white dark:bg-gray-800 shadow-lg rounded-full px-4 py-3 hover:shadow-xl transition-all group">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">New Transaction</span>
            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center group-hover:bg-blue-600 transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
        </a>

{{--        --}}{{-- Transfer --}}
{{--        <a href="{{ route('accounts.transfer') }}"--}}
{{--           class="flex items-center gap-3 bg-white dark:bg-gray-800 shadow-lg rounded-full px-4 py-3 hover:shadow-xl transition-all group">--}}
{{--            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Transfer</span>--}}
{{--            <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center group-hover:bg-purple-600 transition-colors">--}}
{{--                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
{{--                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>--}}
{{--                </svg>--}}
{{--            </div>--}}
{{--        </a>--}}

        {{-- Quick Top Up --}}
{{--        @if(isset($quickAccount))--}}
{{--            <a href="{{ route('accounts.topup', $quickAccount) }}"--}}
{{--               class="flex items-center gap-3 bg-white dark:bg-gray-800 shadow-lg rounded-full px-4 py-3 hover:shadow-xl transition-all group">--}}
{{--                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Top Up {{ $quickAccount->name }}</span>--}}
{{--                <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center group-hover:bg-green-600 transition-colors">--}}
{{--                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
{{--                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>--}}
{{--                    </svg>--}}
{{--                </div>--}}
{{--            </a>--}}
{{--        @endif--}}
    </div>

    {{-- Main FAB Button --}}
    <button @click="open = !open"
            class="w-14 h-14 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full shadow-lg hover:shadow-xl transform hover:scale-110 transition-all duration-200 flex items-center justify-center group">
        <svg x-show="!open" class="w-6 h-6 text-white transition-transform group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <svg x-show="open" class="w-6 h-6 text-white transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>

    {{-- Backdrop overlay for mobile --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 bg-black bg-opacity-25 -z-10 sm:hidden">
    </div>
</div>
