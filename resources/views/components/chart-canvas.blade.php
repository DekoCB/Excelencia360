@props([
    'type' => 'line',
    'labels' => [],
    'data' => [],
    'label' => '',
    'color' => '#38BDF8',
    'height' => 220,
])

{{--
    wire:ignore evita que Livewire intente diffear/reemplazar el <canvas>
    en morphs futuros del componente: una vez montado, Chart.js es dueño
    exclusivo de ese nodo. Alpine reinicializa el gráfico solo cuando el
    <canvas> es un nodo nuevo en el DOM (p. ej. al entrar a la página vía
    wire:navigate), y lo destruye cuando Alpine detecta que el nodo se
    removió (navegación a otra página).
--}}
<div wire:ignore style="height: {{ $height }}px">
    <canvas
        x-data="chartCanvas({
            type: @js($type),
            data: {
                labels: @js($labels),
                datasets: [{
                    label: @js($label),
                    data: @js($data),
                    borderColor: @js($color),
                    backgroundColor: @js($color.'4D'),
                    borderWidth: 3,
                    pointBackgroundColor: @js($color),
                    pointBorderColor: @js($color),
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0.3,
                    fill: @js($type === 'line'),
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grace: '10%', grid: { display: false } },
                },
            },
        })"
        {{ $attributes->merge(['class' => 'w-full h-full']) }}
    ></canvas>
</div>
