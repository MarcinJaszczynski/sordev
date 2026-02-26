// Defensive front-end fixes (mobile)

function enforceCookieManage() {
    const el = document.getElementById('cookie-manage');
    if (!el) return;

    // Reparent to body to avoid ancestor overflow/clipping
    if (el.parentElement !== document.body) {
        try {
            document.body.appendChild(el);
        } catch (e) {
            // ignore
        }
    }

    // Apply inline styles with high specificity
    el.style.setProperty('position', 'fixed', 'important');
    el.style.setProperty('right', '16px', 'important');
    el.style.setProperty('bottom', '84px', 'important');
    el.style.setProperty('z-index', '2147483647', 'important');
    el.style.setProperty('white-space', 'normal', 'important');
    el.style.setProperty('min-width', '0', 'important');
}

function dedupeScrollTop() {
    const els = document.querySelectorAll('.scroll-top');
    if (els.length <= 1) return;
    // keep the last one and remove others
    for (let i = 0; i < els.length - 1; i++) {
        const e = els[i];
        if (e && e.parentNode) e.parentNode.removeChild(e);
    }
}

function debounce(fn, wait = 150) {
    let t;
    return function (...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
}

document.addEventListener('DOMContentLoaded', () => {
    enforceCookieManage();
    dedupeScrollTop();
});

window.addEventListener('load', enforceCookieManage);
window.addEventListener('resize', debounce(enforceCookieManage, 150));

export default {};
