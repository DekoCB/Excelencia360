@props(['curso'])

{{--
    Portada del curso: la imagen subida desde Aula Virtual (coordinador o
    dirección, ver aula-virtual/show.blade.php) si existe, o si no un ícono
    representativo (según el nombre del curso, ver Curso::iconoRepresentativo())
    sobre un bloque de color.
--}}
<div {{ $attributes->merge(['class' => 'flex h-28 items-center justify-center overflow-hidden rounded-md bg-accent-soft']) }}>
    @if ($curso->getFirstMediaUrl('portada'))
        <img src="{{ $curso->getFirstMediaUrl('portada') }}" alt="" class="h-full w-full object-cover">
    @else
        <x-dynamic-component :component="'heroicon-o-'.$curso->iconoRepresentativo()" class="h-10 w-10 text-accent" />
    @endif
</div>
