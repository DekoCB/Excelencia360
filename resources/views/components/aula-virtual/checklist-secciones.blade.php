@props(['secciones', 'campo'])

{{--
    Checklist "subir también a" compartido entre material, clase grabada,
    tarea y foro: otros cursos virtuales del mismo curso académico, grado y
    ciclo -- para replicar de una sola vez en vez de repetir la carga uno
    por uno. Como curso/grado/ciclo ya son iguales para todas las opciones
    (eso es justo lo que las agrupa), lo único que distingue cada fila es
    el aula y quién la dicta.
--}}
@if ($secciones->count() > 1)
    <div>
        <x-input-label value="Subir también a" />
        <div class="mt-1 max-h-40 space-y-1 overflow-y-auto rounded-md border border-border bg-surface p-2">
            @foreach ($secciones as $cursoOpcion)
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input
                        type="checkbox"
                        value="{{ $cursoOpcion->id }}"
                        wire:model="{{ $campo }}"
                        class="rounded border-border text-accent focus:ring-accent"
                    >
                    Aula {{ $cursoOpcion->horario->grado->letraAula() }} · {{ $cursoOpcion->horario->docente->name }}
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get($campo)" class="mt-1" />
    </div>
@endif
