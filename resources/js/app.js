import './bootstrap';
import Chart from 'chart.js/auto';
import jsQR from 'jsqr';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import esLocale from '@fullcalendar/core/locales/es';
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

/**
 * Confirmación sonora de asistencia (ver asistencia/show.blade.php y
 * asistencia-general/index.blade.php): un timbre de dos notas (Web Audio,
 * sin archivo de audio) más fuerte que el beep original, seguido de una
 * voz que dice "Registrado" (SpeechSynthesis) -- pedido explícito del
 * usuario para que se note incluso sin mirar la pantalla.
 */
window.sonidoConfirmacion = function () {
    try {
        const contexto = new (window.AudioContext || window.webkitAudioContext)();

        // Dos notas ascendentes en vez de un solo beep: más perceptible en
        // un ambiente con ruido (portería, pasillo) que un tono único.
        [880, 1318.5].forEach((frecuencia, indice) => {
            const oscilador = contexto.createOscillator();
            const ganancia = contexto.createGain();
            const inicio = contexto.currentTime + indice * 0.12;

            oscilador.type = 'sine';
            oscilador.frequency.value = frecuencia;
            ganancia.gain.setValueAtTime(0.5, inicio);
            ganancia.gain.exponentialRampToValueAtTime(0.001, inicio + 0.3);

            oscilador.connect(ganancia);
            ganancia.connect(contexto.destination);

            oscilador.start(inicio);
            oscilador.stop(inicio + 0.3);
        });
    } catch (e) {
        // Sin Web Audio disponible: sigue la voz (y la confirmación visual).
    }

    try {
        if ('speechSynthesis' in window) {
            const mensaje = new SpeechSynthesisUtterance('Registrado');
            mensaje.lang = 'es-PE';
            mensaje.volume = 1;
            mensaje.rate = 1;
            // cancel() evita que se amontonen varias voces en cola si el
            // docente/portero escanea varios carnets seguidos.
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(mensaje);
        }
    } catch (e) {
        // Sin SpeechSynthesis disponible: el timbre ya sonó igual.
    }
};

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
     * horariosCalendario: una grilla semanal de FullCalendar para la
     * pestaña "Calendario" de Horarios (ver academico/horarios/index.blade.php),
     * una instancia por tipo (Instituto / Curso corto). "eventos" ya viene
     * armado desde el servidor en el formato de evento recurrente de
     * FullCalendar (daysOfWeek/startTime/endTime, ver Horario::paraCalendario()).
     */
    Alpine.data('horariosCalendario', (eventos) => ({
        calendario: null,
        init() {
            this.calendario = new Calendar(this.$el, {
                plugins: [dayGridPlugin, timeGridPlugin],
                initialView: 'timeGridWeek',
                headerToolbar: { start: 'title', center: '', end: 'today prev,next' },
                locale: esLocale,
                allDaySlot: true,
                allDayText: 'Sin hora fija',
                slotMinTime: '07:00:00',
                slotMaxTime: '23:00:00',
                height: 'auto',
                nowIndicator: true,
                events: eventos,
                eventClick: (info) => {
                    const horarioId = info.event.extendedProps.horarioId;
                    if (horarioId) {
                        this.$wire.call('abrirModalEditar', horarioId);
                    }
                },
            });
            this.calendario.render();
        },
        destroy() {
            this.calendario?.destroy();
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
     * lectorQr: lee un QR con la cámara del dispositivo (jsQR decodifica
     * cuadro por cuadro sobre un <canvas> oculto) y llama al método
     * Livewire indicado con el texto decodificado. Usado por
     * x-qr-scanner para asistencia de estudiantes y de docentes -- ambos
     * comparten la misma cámara/decodificación, solo cambia a qué método
     * del componente Livewire padre se le pasa el resultado.
     */
    Alpine.data('lectorQr', (metodo) => ({
        activo: false,
        error: null,
        stream: null,
        cuadroPendiente: null,
        ultimoCodigo: null,
        ultimoEscaneoEn: 0,
        ultimoIntentoEn: 0,

        async iniciar() {
            this.error = null;

            try {
                // Topar la resolución pedida a la cámara: decodificar un QR
                // no necesita más que esto, y pedir la resolución nativa
                // (a veces 1080p+) hace que cada cuadro tarde mucho más en
                // procesarse -- ver leerCuadro().
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } },
                });
            } catch (e) {
                this.error = 'No se pudo acceder a la cámara. Revisa los permisos del navegador.';
                return;
            }

            this.activo = true;
            this.$refs.video.srcObject = this.stream;
            await this.$refs.video.play();
            this.leerCuadro();
        },

        detener() {
            this.activo = false;
            if (this.cuadroPendiente) cancelAnimationFrame(this.cuadroPendiente);
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
        },

        leerCuadro() {
            if (! this.activo) return;

            // requestAnimationFrame por sí solo intenta hasta 60 veces por
            // segundo -- de sobra para un QR estático en un carnet. Se
            // limita el trabajo pesado (drawImage + getImageData + jsQR) a
            // como mucho uno cada 120ms; rAF sigue programando el siguiente
            // chequeo liviano sin bloquear el hilo de más de lo necesario.
            const ahora = performance.now();
            if (ahora - this.ultimoIntentoEn >= 120) {
                this.ultimoIntentoEn = ahora;

                const video = this.$refs.video;

                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    const canvas = this.$refs.canvas;
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;

                    const contexto = canvas.getContext('2d');
                    contexto.drawImage(video, 0, 0, canvas.width, canvas.height);

                    const imagen = contexto.getImageData(0, 0, canvas.width, canvas.height);
                    // dontInvert: el carnet impreso nunca trae el QR en
                    // negativo, así que no hace falta que jsQR decodifique
                    // cada cuadro dos veces (su default es intentar ambas).
                    const resultado = jsQR(imagen.data, imagen.width, imagen.height, { inversionAttempts: 'dontInvert' });

                    if (resultado?.data) {
                        this.alDetectar(resultado.data);
                    }
                }
            }

            this.cuadroPendiente = requestAnimationFrame(() => this.leerCuadro());
        },

        alDetectar(codigo) {
            // Evita reenviar el mismo código en cuadros consecutivos mientras
            // el carnet sigue frente a la cámara -- el docente recién lo
            // aparta cuando ve la confirmación.
            const ahora = Date.now();
            if (codigo === this.ultimoCodigo && (ahora - this.ultimoEscaneoEn) < 3000) return;

            this.ultimoCodigo = codigo;
            this.ultimoEscaneoEn = ahora;
            this.$wire.call(metodo, codigo);
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
