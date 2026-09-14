import './bootstrap';
import Chart from 'chart.js/auto';
import { VortexScene, VORTEX_LOGIN_CONFIG } from './vortex-dust';

/**
 * Los tokens de color de la app viven como custom properties "R G B"
 * (ver resources/css/app.css), pensadas para Tailwind (`rgb(var(--x) / <alpha>)`).
 * Chart.js no lee CSS de Tailwind, así que se las pasamos explícitamente aquí;
 * de lo contrario usa sus grises por defecto, invisibles sobre el tema oscuro.
 */
function colorToken(name, alpha = 1) {
    const rgb = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return `rgb(${rgb} / ${alpha})`;
}

function aplicarColoresDeTema(config) {
    const esOscuro = document.documentElement.classList.contains('dark');

    const textoTenue = colorToken('--color-text-dim');
    // En tema oscuro, --color-border ya resalta contra un fondo casi negro:
    // se atenúa bastante para que la grilla quede de fondo y no compita
    // visualmente con la línea/barras de datos.
    const grilla = colorToken('--color-border', esOscuro ? 0.14 : 1);

    config.options ??= {};
    config.options.color ??= textoTenue;

    config.options.scales ??= {};
    for (const eje of Object.values(config.options.scales)) {
        eje.ticks ??= {};
        eje.ticks.color ??= textoTenue;
        eje.grid ??= {};
        eje.grid.color ??= grilla;
    }

    return config;
}

document.addEventListener('alpine:init', () => {
    /**
     * Reemplaza el confirm() nativo del navegador (wire:confirm) por un
     * diálogo propio (ver x-confirm-dialog): un solo store global en vez de
     * un modal por cada botón, porque la pregunta/acción cambian en cada
     * clic pero el diálogo en sí es siempre el mismo.
     */
    Alpine.store('confirm', {
        open: false,
        mensaje: '',
        etiquetaConfirmar: 'Confirmar',
        peligro: false,
        _accion: null,

        preguntar(mensaje, accion, { peligro = false, etiquetaConfirmar = 'Confirmar' } = {}) {
            this.mensaje = mensaje;
            this.etiquetaConfirmar = etiquetaConfirmar;
            this.peligro = peligro;
            this._accion = accion;
            this.open = true;
        },

        confirmar() {
            const accion = this._accion;
            this.open = false;
            this._accion = null;
            accion?.();
        },

        cancelar() {
            this.open = false;
            this._accion = null;
        },
    });

    Alpine.data('chartCanvas', (config) => ({
        chart: null,
        init() {
            this.chart = new Chart(this.$el, aplicarColoresDeTema(config));
        },
        destroy() {
            this.chart?.destroy();
        },
    }));

    /**
     * Fondo del panel de la mascota en el login (ver layouts/guest.blade.php
     * y vortex-dust.js). Se omite con "menos movimiento" del sistema, igual
     * criterio que el resto de animaciones de la app.
     */
    Alpine.data('vortexDust', () => ({
        scene: null,
        ro: null,
        init() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            this.scene = new VortexScene(this.$el, VORTEX_LOGIN_CONFIG);
            this.scene.setSize(this.$el.clientWidth, this.$el.clientHeight);
            this.scene.start();

            this.ro = new ResizeObserver(() => {
                this.scene.setSize(this.$el.clientWidth, this.$el.clientHeight);
            });
            this.ro.observe(this.$el);
        },
        destroy() {
            this.ro?.disconnect();
            this.scene?.dispose();
        },
    }));

    /**
     * x-reveal: fade-in + slide-up la primera vez que el elemento entra en
     * viewport (landing pública). Un solo IntersectionObserver compartido
     * en vez de uno por elemento, para no crear decenas en una página larga.
     */
    let observadorReveal = null;

    Alpine.directive('reveal', (el, { modifiers }) => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        // x-reveal.150 retrasa 150ms el inicio de la transición, para poder
        // escalonar la entrada de varios elementos hermanos (p. ej. el hero).
        const retraso = Number(modifiers[0]);
        if (retraso > 0) {
            el.style.transitionDelay = `${retraso}ms`;
        }

        el.classList.add('opacity-0', 'translate-y-4', 'transition', 'duration-700', 'ease-out');

        observadorReveal ??= new IntersectionObserver(
            (entradas) => {
                for (const entrada of entradas) {
                    if (entrada.isIntersecting) {
                        entrada.target.classList.remove('opacity-0', 'translate-y-4');
                        observadorReveal.unobserve(entrada.target);
                    }
                }
            },
            { threshold: 0.15 },
        );

        observadorReveal.observe(el);
    });
});
