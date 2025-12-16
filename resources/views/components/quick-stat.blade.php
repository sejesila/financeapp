@props([
    'label' => '',
    'value' => '',
    'subtext' => '',
    'color' => 'blue',
    'positive' => true
])

<div class="bg-white p-6 rounded-lg shadow border-l-4 border-{{ $color }}-500">
    <p class="text-xs font-bold text-gray-500 mb-3 uppercase tracking-wide">{{ $label }}</p>
    <p class="text-3xl font-bold {{ $positive ? "text-$color-600" : "text-red-600" }} mb-1">{{ $value }}</p>
    @if($subtext)
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
            <span class="text-xs text-gray-500">{{ $subtext }}</span>
            <div class="bg-{{ $color }}-100 p-2 rounded-full">
                <svg class="w-5 h-5 text-{{ $color }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {{ $slot }}
                </svg>
            </div>
        </div>
    @endif
</div>
