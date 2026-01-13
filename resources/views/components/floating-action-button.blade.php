{{-- Floating Action Button Component - Direct to Create Transaction --}}
<div class="fixed bottom-6 right-6 z-50">
    {{-- Main FAB Button - Direct Link --}}
    <a href="{{ route('transactions.create') }}"
       class="w-14 h-14 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full shadow-lg hover:shadow-xl transform hover:scale-110 transition-all duration-200 flex items-center justify-center group">
        <svg class="w-6 h-6 text-white transition-transform group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
    </a>
</div>
