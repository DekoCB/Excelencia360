/**
 * Vortex Dust — motas de polvo cayendo en espiral hacia un centro que puede
 * seguir al cursor. Adaptado del componente original de Originkit (Canvas 2D
 * puro, sin nada específico de React) para usarlo con Alpine en vez de un
 * proyecto Next.js -- ver el componente vortexDust en app.js.
 *
 * Cada mota vive en coordenadas polares y se mueve con dos números: una
 * velocidad angular que sube mientras se acerca al centro, y una deriva
 * lenta hacia adentro. Esa velocidad creciente es todo el efecto -- es lo
 * que curva las trayectorias en espirales en vez de líneas rectas al
 * centro, la misma razón por la que el agua acelera al bajar por un
 * desagüe. Las motas que llegan al centro renacen en el borde con un
 * ángulo nuevo, así la población es constante y la espiral nunca se vacía.
 */

const DEFAULTS = {
    colorA: '#FFFFFF',
    colorB: '#7900FF',
    count: 20,
    size: 8,
    pull: 20,
    speed: 10,
    followPointer: true,
    strength: 10,
};

function clamp(v, lo, hi, fallback) {
    const n = typeof v === 'number' && isFinite(v) ? v : fallback;

    return Math.max(lo, Math.min(hi, n));
}

/** Los valores del panel son enteros cómodos de mover; el vórtice quiere los reales. */
function settingsFor(cfg) {
    return {
        count: Math.round(50 + clamp(cfg.count, 1, 20, DEFAULTS.count) * 40),
        size: 0.4 + clamp(cfg.size, 1, 20, DEFAULTS.size) * 0.13,
        pull: 0.02 + clamp(cfg.pull, 0, 20, DEFAULTS.pull) * 0.018,
        speed: 8 + clamp(cfg.speed, 0, 20, DEFAULTS.speed) * 6,
        follow: clamp(cfg.strength, 1, 20, DEFAULTS.strength) * 0.05,
    };
}

export class VortexScene {
    constructor(container, cfg) {
        this.container = container;
        this.cfg = cfg;

        this.motes = [];
        this.width = 0;
        this.height = 0;
        this.dpr = 1;
        this.time = 0;
        this.frameId = 0;
        this.lastT = 0;
        this.disposed = false;

        this.px = -1;
        this.py = -1;
        this.tx = -1;
        this.ty = -1;
        this.grip = 0;
        this.gripTarget = 0;

        this.canvas = document.createElement('canvas');
        this.canvas.style.position = 'absolute';
        this.canvas.style.inset = '0';
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        container.appendChild(this.canvas);

        const ctx = this.canvas.getContext('2d');
        if (!ctx) {
            throw new Error('no 2d context');
        }
        this.ctx = ctx;

        this.onEnter = () => {
            this.gripTarget = 1;
        };
        this.onLeave = () => {
            this.gripTarget = 0;
        };
        this.onMove = (e) => {
            const rect = this.container.getBoundingClientRect();
            if (!rect.width || !rect.height) return;
            this.tx = e.clientX - rect.left;
            this.ty = e.clientY - rect.top;
            if (this.px < 0) {
                this.px = this.tx;
                this.py = this.ty;
            }
        };

        container.addEventListener('pointerenter', this.onEnter);
        container.addEventListener('pointerleave', this.onLeave);
        container.addEventListener('pointercancel', this.onLeave);
        container.addEventListener('pointermove', this.onMove);
    }

    rim() {
        // Más allá de la esquina, para que las motas entren desde fuera del
        // cuadro en vez de aparecer sobre un círculo visible.
        return Math.max(this.width, this.height) * 0.78;
    }

    spawn(m, scatter) {
        m.a = Math.random() * Math.PI * 2;
        // En el primer llenado los radios se reparten por todo el disco, o
        // los primeros cuadros mostrarían un solo anillo apretado avanzando.
        m.r = scatter ? Math.random() * this.rim() : this.rim() * (0.9 + Math.random() * 0.2);
        m.tint = Math.random();
        m.wob = Math.random() * Math.PI * 2;
    }

    build() {
        const S = settingsFor(this.cfg);
        this.motes = [];
        for (let i = 0; i < S.count; i++) {
            const m = { a: 0, r: 0, tint: 0, wob: 0 };
            this.spawn(m, true);
            this.motes.push(m);
        }
    }

    start() {
        this.lastT = performance.now();
        const loop = () => {
            this.frameId = requestAnimationFrame(loop);
            this.step();
        };
        loop();
    }

    setSize(width, height) {
        if (this.disposed || width <= 0 || height <= 0) return;
        this.dpr = Math.min(window.devicePixelRatio || 1, 2);
        const first = this.width === 0;
        this.width = width;
        this.height = height;
        this.canvas.width = Math.round(width * this.dpr);
        this.canvas.height = Math.round(height * this.dpr);
        this.ctx.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
        if (first || this.motes.length === 0) this.build();
    }

    step() {
        if (this.disposed || this.width <= 0 || this.motes.length === 0) return;
        const now = performance.now();
        let dt = (now - this.lastT) / 1000;
        this.lastT = now;
        if (!isFinite(dt) || dt < 0) dt = 0;
        if (dt > 0.05) dt = 0.05;

        const S = settingsFor(this.cfg);
        const ctx = this.ctx;
        this.time += dt;

        if (this.px >= 0) {
            const k = 1 - Math.exp(-dt * 6);
            this.px += (this.tx - this.px) * k;
            this.py += (this.ty - this.py) * k;
        }
        const want = this.cfg.followPointer && this.px >= 0 ? this.gripTarget : 0;
        this.grip += (want - this.grip) * (1 - Math.exp(-dt * 2.5));

        const homeX = this.width * 0.5;
        const homeY = this.height * 0.5;
        const cx = homeX + (this.px - homeX) * this.grip * S.follow * 1.6;
        const cy = homeY + (this.py - homeY) * this.grip * S.follow * 1.6;

        // El rastro es un fundido "destination-out" en vez de una ruta
        // guardada, así el canvas se mantiene transparente y queda encima
        // del degradado del panel en vez de taparlo.
        ctx.globalCompositeOperation = 'destination-out';
        ctx.fillStyle = 'rgba(0,0,0,' + Math.min(0.6, 0.29 * dt * 60) + ')';
        ctx.fillRect(0, 0, this.width, this.height);
        ctx.globalCompositeOperation = 'source-over';

        ctx.lineCap = 'round';
        const a = this.cfg.colorA || DEFAULTS.colorA;
        const b = this.cfg.colorB || DEFAULTS.colorB;
        const rim = this.rim();

        for (let i = 0; i < this.motes.length; i++) {
            const m = this.motes[i];

            const px = cx + Math.cos(m.a) * m.r;
            const py = cy + Math.sin(m.a) * m.r;

            // Velocidad angular escalada por el inverso del radio: una tasa
            // constante convertiría el campo en un disco rígido; esto es lo
            // que hace que las trayectorias espiralen y el centro se acelere.
            m.a += (S.speed / (m.r * 0.35 + 24)) * dt * 6;
            m.r -= m.r * S.pull * dt * 1.6;
            m.r += Math.sin(this.time * 1.4 + m.wob) * 6 * dt;

            if (m.r < 6) {
                this.spawn(m, false);
                continue;
            }

            const x = cx + Math.cos(m.a) * m.r;
            const y = cy + Math.sin(m.a) * m.r;
            const near = 1 - Math.min(1, m.r / rim);

            ctx.strokeStyle = m.tint < 0.5 ? a : b;
            ctx.globalAlpha = 0.15 + near * 0.7;
            ctx.lineWidth = S.size * (0.6 + near * 1.6);
            ctx.beginPath();
            ctx.moveTo(px, py);
            ctx.lineTo(x, y);
            ctx.stroke();
        }
        ctx.globalAlpha = 1;
    }

    dispose() {
        this.disposed = true;
        cancelAnimationFrame(this.frameId);
        this.container.removeEventListener('pointerenter', this.onEnter);
        this.container.removeEventListener('pointerleave', this.onLeave);
        this.container.removeEventListener('pointercancel', this.onLeave);
        this.container.removeEventListener('pointermove', this.onMove);
        if (this.canvas.parentNode === this.container) {
            this.container.removeChild(this.canvas);
        }
    }
}

/**
 * Preset para el panel del login: más calmo que los defaults del componente
 * original (pensados para un hero grande de landing) -- menos motas, deriva
 * y giro más lentos, para que acompañe al saludo en vez de competir con él.
 * colorB usa el lila ya presente en la insignia de la mascota, en vez del
 * morado genérico del componente original.
 */
export const VORTEX_LOGIN_CONFIG = {
    colorA: '#FFFFFF',
    colorB: '#C4B5FD',
    count: 8,
    size: 8,
    pull: 6,
    speed: 6,
    followPointer: true,
    strength: 8,
};
