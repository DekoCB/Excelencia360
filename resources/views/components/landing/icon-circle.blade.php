@props(['icon', 'tone' => 'primary', 'size' => 'md', 'interactive' => false])

{{--
    Ícono sobre un disco de color suave. Con "interactive", el disco se
    rellena con el color pleno cuando el padre (.group) recibe hover: es la
    microinteracción de las tarjetas de valores y servicios.
--}}
@php
    $tonos = [
        'primary' => 'bg-e360-primary-tint text-e360-primary-deep',
        'secondary' => 'bg-e360-secondary-tint text-e360-secondary-dark',
        'accent' => 'bg-e360-accent-tint text-e360-accent-deep',
    ];
    $hover = [
        'primary' => 'group-hover:bg-e360-primary-deep group-hover:text-white',
        'secondary' => 'group-hover:bg-e360-secondary-dark group-hover:text-white',
        'accent' => 'group-hover:bg-e360-accent-deep group-hover:text-white',
    ];
    $tamanos = ['sm' => 'h-10 w-10', 'md' => 'h-12 w-12', 'lg' => 'h-14 w-14'];
    $tamanosIcono = ['sm' => 'h-5 w-5', 'md' => 'h-6 w-6', 'lg' => 'h-7 w-7'];
@endphp

<div {{ $attributes->class([
    'flex shrink-0 items-center justify-center rounded-xl transition-colors duration-300',
    $tamanos[$size] ?? $tamanos['md'],
    $tonos[$tone] ?? $tonos['primary'],
    ($hover[$tone] ?? $hover['primary']) => $interactive,
]) }}>
    <x-dynamic-component :component="'heroicon-o-'.$icon" @class([$tamanosIcono[$size] ?? $tamanosIcono['md']]) aria-hidden="true" />
</div>
