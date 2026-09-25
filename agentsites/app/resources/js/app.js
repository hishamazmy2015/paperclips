// Platform JS (app host). Livewire ships Alpine; this adds the one effect S5 asks for (spec §13):
// a short confetti burst on the success screen, no dependency.
window.agentsitesConfetti = function (canvas) {
    if (!canvas || !canvas.getContext || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const ctx = canvas.getContext('2d');
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    const resize = () => { canvas.width = innerWidth * dpr; canvas.height = innerHeight * dpr; };
    resize();
    const colors = ['#9a6b2f', '#c8963e', '#1e3a8a', '#0f766e', '#9f1239', '#d4af37', '#25d366'];
    const pieces = Array.from({ length: 140 }, () => ({
        x: Math.random() * canvas.width,
        y: -Math.random() * canvas.height * 0.5,
        w: (6 + Math.random() * 6) * dpr,
        h: (8 + Math.random() * 8) * dpr,
        vx: (Math.random() - 0.5) * 2 * dpr,
        vy: (2 + Math.random() * 3) * dpr,
        r: Math.random() * Math.PI,
        vr: (Math.random() - 0.5) * 0.2,
        c: colors[Math.floor(Math.random() * colors.length)],
    }));
    const start = performance.now();
    const tick = (now) => {
        const t = (now - start) / 1000;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (const p of pieces) {
            p.x += p.vx; p.y += p.vy; p.r += p.vr; p.vy += 0.02 * dpr;
            ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
            ctx.globalAlpha = Math.max(0, Math.min(1, 3.2 - t));
            ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore();
        }
        if (t < 3.3) requestAnimationFrame(tick); else ctx.clearRect(0, 0, canvas.width, canvas.height);
    };
    requestAnimationFrame(tick);
};
