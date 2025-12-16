@props([
    'title' => '',
    'value' => '',
    'subtitle' => '',
    'icon' => null,
    'color' => 'gray',
    'border' => '',
    'textColor' => 'gray-900'
])

<div {{ $attributes->merge(['class' => "bg-white p-6 rounded-lg shadow border-l-4 border-$border"]) }}>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-{{ $color }}-700 mb-2 uppercase tracking-wide">{{ $title }}</p>
            <p class="text-4xl font-bold text-{{ $textColor }} mb-1">{{ $value }}</p>
            @if($subtitle)
                <p class="text-xs text-{{ $color }}-700">{{ $subtitle }}</p>
            @endif
        </div>
        @if($icon)
            <div class="text-{{ $color }}-500">
                {!! $icon !!}
            </div>
        @endif
    </div>
</div>
