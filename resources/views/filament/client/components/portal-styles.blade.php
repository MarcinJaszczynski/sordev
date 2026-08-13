{{-- Style portalu klienta — zbliżone do frontu RAFA (#0663fc), bez amber admina. --}}
<style>
    .fi-panel-portal {
        --sor-portal-primary: #0663fc;
        --sor-portal-primary-hover: #055bda;
        --sor-portal-ink: #0d3b66;
        --sor-portal-surface: #f5f5f5;
        --sor-portal-muted: #64748b;
    }

    .fi-panel-portal .fi-main,
    .fi-panel-portal .fi-body {
        background-color: var(--sor-portal-surface);
    }

    .fi-panel-portal .fi-sidebar-header .fi-logo img,
    .fi-panel-portal .fi-topbar .fi-logo img {
        max-height: 2.25rem;
    }

    .fi-panel-portal .client-portal-trip-nav {
        margin-bottom: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .fi-panel-portal .client-portal-trip-nav__list {
        display: flex;
        gap: 0.35rem;
        min-width: max-content;
        padding-bottom: 0.35rem;
        overflow-x: auto;
    }

    .fi-panel-portal .client-portal-trip-nav__item {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.85rem;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #475569;
        background: #fff;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
        transition: background-color .15s, border-color .15s, color .15s;
    }

    .fi-panel-portal .client-portal-trip-nav__item:hover {
        border-color: #bfdbfe;
        color: var(--sor-portal-ink);
        background: #eff6ff;
    }

    .fi-panel-portal .client-portal-trip-nav__item.is-active {
        color: #fff;
        background: var(--sor-portal-primary);
        border-color: var(--sor-portal-primary);
    }

    .fi-panel-portal .client-portal-card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        transition: box-shadow .15s, border-color .15s, transform .15s;
        text-decoration: none;
        color: inherit;
        height: 100%;
    }

    .fi-panel-portal .client-portal-card:hover {
        border-color: #bfdbfe;
        box-shadow: 0 8px 24px rgb(6 99 252 / 0.12);
        transform: translateY(-1px);
    }

    .fi-panel-portal .client-portal-card__media {
        position: relative;
        aspect-ratio: 16 / 10;
        background: #0f172a;
        overflow: hidden;
    }

    .fi-panel-portal .client-portal-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }

    .fi-panel-portal .client-portal-card__media-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        background: linear-gradient(135deg, #0d3b66, #0663fc);
        color: #fff;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .fi-panel-portal .client-portal-card__body {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem 1.1rem 1.15rem;
    }

    .fi-panel-portal .client-portal-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        background: #0f172a;
        margin-bottom: 1.25rem;
        aspect-ratio: 16 / 10;
        max-height: 16rem;
    }

    .fi-panel-portal .client-portal-hero img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        opacity: 0.92;
    }

    .fi-panel-portal .client-portal-hero__overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgb(15 23 42 / 0.88), rgb(15 23 42 / 0.25) 55%, transparent);
    }

    .fi-panel-portal .client-portal-hero__content {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        padding: 1.15rem 1.25rem;
        color: #fff;
    }

    .fi-panel-portal .client-portal-section {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #fff;
        padding: 1.15rem 1.25rem;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .fi-panel-portal .client-portal-section + .client-portal-section {
        margin-top: 0.85rem;
    }

    .fi-panel-portal .client-portal-kicker {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--sor-portal-muted);
    }

    /* Program wycieczki — styl jak na stronie oferty (day-itinerary) */
    .fi-panel-portal .client-portal-program {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #fff;
        padding: 1.15rem 1.25rem 1.35rem;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .fi-panel-portal .client-portal-program__day + .client-portal-program__hr {
        margin: 0.85rem 0;
        border: 0;
        border-top: 1px solid #e2e8f0;
    }

    .fi-panel-portal .client-portal-program__day-header {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.5rem 0.85rem;
        margin-bottom: 0.65rem;
    }

    .fi-panel-portal .client-portal-program__day-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--sor-portal-ink);
    }

    .fi-panel-portal .client-portal-program__day-date {
        margin: 0;
        font-size: 0.875rem;
        color: var(--sor-portal-muted);
    }

    .fi-panel-portal .client-portal-program__list {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }

    .fi-panel-portal .client-portal-program__item {
        margin: 0;
        background: rgba(245, 245, 245, 0.7);
        border-radius: 12px;
        padding: 0.5rem 0.9rem;
        color: #222;
        line-height: 1.45;
        font-size: 0.9375rem;
    }

    .fi-panel-portal .client-portal-program__item-main {
        display: block;
    }

    .fi-panel-portal .client-portal-program__bullet {
        margin-right: 0.2rem;
        color: #64748b;
    }

    .fi-panel-portal .client-portal-program__item strong {
        color: #222;
        font-weight: 700;
    }

    .fi-panel-portal .client-portal-program__desc {
        color: #334155;
    }

    .fi-panel-portal .client-portal-program__time {
        display: inline-block;
        margin-right: 0.45rem;
        font-size: 0.8125rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: var(--sor-portal-primary);
    }

    .fi-panel-portal .client-portal-program__children {
        margin: 0.4rem 0 0;
        padding: 0 0 0 0.85rem;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .fi-panel-portal .client-portal-program__item--child {
        background: transparent;
        padding: 0.15rem 0;
        font-size: 0.9rem;
    }

    @media (min-width: 768px) {
        .fi-panel-portal .client-portal-hero {
            max-height: 18rem;
        }

        .fi-panel-portal .client-portal-program {
            padding: 1.35rem 1.5rem 1.5rem;
        }
    }
</style>
