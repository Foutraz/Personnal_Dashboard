@props([
    'hover' => false,
    'padding' => 'p-6',
])

<div {{ $attributes->class(['glass', 'glass-hover' => $hover, $padding]) }}>
    {{ $slot }}
</div>
