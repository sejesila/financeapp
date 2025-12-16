@props([
    'title',
    'value',
    'color' => 'blue',
    'subtitle' => null,
])

@php
    $colors = [
        'green'  => ['border' => 'border-green-500',  'text' => 'text-green-600'],
        'red'    => ['border' => 'border-red-500',    'text' => 'text-red-600'],
        'blue'   => ['border' => 'border-blue-500',   'text' => 'text-blue-600'],
        'orange' => ['border' => 'border-orange-500', 'text' => 'text-orange-600'],
    ];
@endphp

<div class="rounded-lg border bg-white p-6 shadow-sm border-l-4 {{ $colors[$color]['border'] }}">
    <p class="text-sm font-medium text-gray-600 mb-1">
        {{ $title }}
    </p>

    <p class="text-3xl font-bold {{ $colors[$color]['text'] }}">
        {{ number_format($value) }}
    </p>

    @if($subtitle)
        <p class="text-xs text-gray-500 mt-1">
            {{ $subtitle }}
        </p>
    @endif

    {{ $slot }}
</div>
