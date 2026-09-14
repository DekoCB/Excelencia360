@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-ink-dim']) }}>
    {{ $value ?? $slot }}
</label>
