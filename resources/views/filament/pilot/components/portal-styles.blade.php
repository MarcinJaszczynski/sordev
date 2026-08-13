{{-- Style portalu pilota — ten sam język wizualny co klient (RAFA #0663fc). --}}
<style>
    .fi-panel-pilot {
        --sor-portal-primary: #0663fc;
        --sor-portal-primary-hover: #055bda;
        --sor-portal-ink: #0d3b66;
        --sor-portal-surface: #f5f5f5;
        --sor-portal-muted: #64748b;
    }

    .fi-panel-pilot .fi-main,
    .fi-panel-pilot .fi-body {
        background-color: var(--sor-portal-surface);
    }

    .fi-panel-pilot .fi-sidebar-header .fi-logo img,
    .fi-panel-pilot .fi-topbar .fi-logo img {
        max-height: 2.25rem;
    }

    .fi-panel-pilot .client-portal-trip-nav {
        margin-bottom: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .fi-panel-pilot .client-portal-trip-nav__list {
        display: flex;
        gap: 0.35rem;
        min-width: max-content;
        padding-bottom: 0.35rem;
        overflow-x: auto;
    }

    .fi-panel-pilot .client-portal-trip-nav__item {
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

    .fi-panel-pilot .client-portal-trip-nav__item:hover {
        border-color: #bfdbfe;
        color: var(--sor-portal-ink);
        background: #eff6ff;
    }

    .fi-panel-pilot .client-portal-trip-nav__item.is-active {
        color: #fff;
        background: var(--sor-portal-primary);
        border-color: var(--sor-portal-primary);
    }

    .fi-panel-pilot .client-portal-card {
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

    .fi-panel-pilot .client-portal-card:hover {
        border-color: #bfdbfe;
        box-shadow: 0 8px 24px rgb(6 99 252 / 0.12);
        transform: translateY(-1px);
    }

    .fi-panel-pilot .client-portal-card__media {
        position: relative;
        aspect-ratio: 16 / 10;
        background: #0f172a;
        overflow: hidden;
    }

    .fi-panel-pilot .client-portal-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }

    .fi-panel-pilot .client-portal-card__media-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        background: linear-gradient(135deg, #0d3b66, #0663fc);
        color: #fff;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .fi-panel-pilot .client-portal-card__body {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem 1.1rem 1.15rem;
    }

    .fi-panel-pilot .client-portal-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        background: #0f172a;
        margin-bottom: 1.25rem;
        aspect-ratio: 16 / 10;
        max-height: 16rem;
    }

    .fi-panel-pilot .client-portal-hero img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        opacity: 0.92;
    }

    .fi-panel-pilot .client-portal-hero__overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgb(15 23 42 / 0.88), rgb(15 23 42 / 0.25) 55%, transparent);
    }

    .fi-panel-pilot .client-portal-hero__content {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        padding: 1.15rem 1.25rem;
        color: #fff;
    }

    .fi-panel-pilot .client-portal-section {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #fff;
        padding: 1.15rem 1.25rem;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .fi-panel-pilot .client-portal-section + .client-portal-section {
        margin-top: 0.85rem;
    }

    .fi-panel-pilot .client-portal-kicker {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--sor-portal-muted);
    }

    @media (min-width: 768px) {
        .fi-panel-pilot .client-portal-hero {
            max-height: 18rem;
        }
    }
</style>
