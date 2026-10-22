@props([
    'steps' => [],
    'marker' => 'flow',
])

@php
    $normalized = [];
    foreach ($steps as $step) {
        if (is_array($step)) {
            $normalized[] = [
                'label' => (string) ($step['label'] ?? ''),
                'detail' => (string) ($step['detail'] ?? ''),
            ];
        } else {
            $normalized[] = ['label' => (string) $step, 'detail' => ''];
        }
    }
    $count = count($normalized);
    $boxWidth = 132;
    $gap = 28;
    $height = 96;
    $width = max($boxWidth, ($count * $boxWidth) + (max(0, $count - 1) * $gap));
    $markerId = 'gs-arrow-'.preg_replace('/[^a-z0-9_-]/i', '', (string) $marker);
@endphp

@if ($count > 0)
    <div class="overflow-x-auto" data-getting-started-flow="{{ $marker }}">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-24 min-w-full" role="img" aria-label="{{ collect($normalized)->pluck('label')->implode(' → ') }}">
            <defs>
                <marker id="{{ $markerId }}" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                    <path d="M0,0 L8,4 L0,8 Z" fill="#2563eb"></path>
                </marker>
            </defs>
            @foreach ($normalized as $index => $step)
                @php($x = $index * ($boxWidth + $gap))
                <rect x="{{ $x }}" y="16" width="{{ $boxWidth }}" height="64" rx="14" fill="#eff6ff" stroke="#2563eb"></rect>
                <text x="{{ $x + ($boxWidth / 2) }}" y="{{ $step['detail'] !== '' ? 46 : 54 }}" text-anchor="middle" fill="#1e3a8a" font-size="14" font-weight="700">{{ $step['label'] }}</text>
                @if ($step['detail'] !== '')
                    <text x="{{ $x + ($boxWidth / 2) }}" y="66" text-anchor="middle" fill="#475569" font-size="11">{{ $step['detail'] }}</text>
                @endif
                @if ($index < $count - 1)
                    <line x1="{{ $x + $boxWidth + 2 }}" y1="48" x2="{{ $x + $boxWidth + $gap - 8 }}" y2="48" stroke="#2563eb" stroke-width="2" marker-end="url(#{{ $markerId }})"></line>
                @endif
            @endforeach
        </svg>
    </div>
@endif
