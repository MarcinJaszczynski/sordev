{{-- Portal klienta — paleta zgodna z mockup-reference-portal-pilota.html --}}
<style>
    .fi-panel-portal {
        --sor-portal-bg: #F5F4EF;
        --sor-portal-card: #FFFFFF;
        --sor-portal-card-border: #E5E3DA;
        --sor-portal-muted-bg: #F1EFE8;
        --sor-portal-ink: #2C2C2A;
        --sor-portal-secondary: #5F5E5A;
        --sor-portal-muted: #888780;
        --sor-portal-accent-bg: #E6F1FB;
        --sor-portal-accent: #0C447C;
        --sor-portal-success-bg: #EAF3DE;
        --sor-portal-success: #27500A;
        --sor-portal-control-border: #D3D1C7;
        --sor-portal-radius-card: 12px;
        --sor-portal-radius-hero: 14px;
        --sor-portal-radius-pill: 20px;
        --sor-portal-radius-control: 8px;
    }

    .fi-panel-portal .fi-main,
    .fi-panel-portal .fi-body {
        background-color: var(--sor-portal-bg);
        color: var(--sor-portal-ink);
    }

    .fi-panel-portal .fi-sidebar-header .fi-logo img,
    .fi-panel-portal .fi-topbar .fi-logo img {
        max-height: 2.25rem;
    }

    /* ===== Baner podglądu ===== */
    .fi-panel-portal .portal-preview-banner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        padding: 0.625rem 0.75rem;
        border-radius: 10px;
        border: none;
        background: var(--sor-portal-accent-bg);
        color: var(--sor-portal-accent);
    }

    .fi-panel-portal .portal-preview-banner__title {
        margin: 0;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--sor-portal-accent);
    }

    .fi-panel-portal .portal-preview-banner__desc {
        margin: 0.125rem 0 0;
        font-size: 0.6875rem;
        color: var(--sor-portal-accent);
        opacity: 0.95;
    }

    .fi-panel-portal .portal-preview-banner__exit {
        flex-shrink: 0;
        padding: 0.3125rem 0.625rem;
        border-radius: var(--sor-portal-radius-control);
        border: 1px solid var(--sor-portal-control-border);
        background: #FFFFFF;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--sor-portal-ink);
        text-decoration: none;
    }

    .fi-panel-portal .portal-preview-banner__exit:hover {
        background: var(--sor-portal-muted-bg);
    }

    /* ===== Nawigacja zakładek (pigułki) ===== */
    .fi-panel-portal .client-portal-trip-nav {
        margin-bottom: 0.875rem;
        border-bottom: none;
    }

    .fi-panel-portal .client-portal-trip-nav__list {
        display: flex;
        gap: 0.375rem;
        padding-bottom: 0.375rem;
        overflow-x: auto;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .fi-panel-portal .client-portal-trip-nav__list::-webkit-scrollbar {
        display: none;
    }

    .fi-panel-portal .client-portal-trip-nav__item {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex-shrink: 0;
        padding: 0.4375rem 0.875rem;
        border-radius: var(--sor-portal-radius-pill);
        border: none;
        background: var(--sor-portal-muted-bg);
        color: var(--sor-portal-secondary);
        font-size: 0.75rem;
        font-weight: 500;
        white-space: nowrap;
        text-decoration: none;
        transition: background-color .15s, color .15s;
    }

    .fi-panel-portal .client-portal-trip-nav__item:hover {
        background: #E8E6DE;
        color: var(--sor-portal-ink);
    }

    .fi-panel-portal .client-portal-trip-nav__item.is-active {
        background: var(--sor-portal-ink);
        color: #FFFFFF;
    }

    @media (min-width: 640px) {
        .fi-panel-portal .client-portal-trip-nav__list {
            overflow-x: visible;
            flex-wrap: wrap;
        }
    }

    /* ===== Karty listy wycieczek ===== */
    .fi-panel-portal .client-portal-card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        height: 100%;
        border-radius: var(--sor-portal-radius-hero);
        border: 1px solid var(--sor-portal-card-border);
        background: var(--sor-portal-card);
        box-shadow: none;
        text-decoration: none;
        color: inherit;
        transition: border-color .15s, box-shadow .15s;
    }

    .fi-panel-portal .client-portal-card:hover {
        border-color: #D3D1C7;
        box-shadow: 0 4px 16px rgb(44 44 42 / 0.08);
        transform: none;
    }

    .fi-panel-portal .client-portal-card__media {
        position: relative;
        aspect-ratio: 16 / 10;
        overflow: hidden;
        background: #D3D1C7;
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
        background: linear-gradient(135deg, #5F5E5A, #2C2C2A);
        color: #fff;
        font-weight: 600;
        letter-spacing: 0.04em;
    }

    .fi-panel-portal .client-portal-card__body {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem 1.1rem 1.15rem;
    }

    /* ===== Hero wycieczki ===== */
    .fi-panel-portal .client-portal-hero {
        position: relative;
        overflow: hidden;
        height: 150px;
        max-height: none;
        aspect-ratio: auto;
        margin-bottom: 0.75rem;
        border-radius: var(--sor-portal-radius-hero);
        background: #D3D1C7;
    }

    .fi-panel-portal .client-portal-hero img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        opacity: 1;
    }

    .fi-panel-portal .client-portal-hero__overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(0deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.05));
    }

    .fi-panel-portal .client-portal-hero__content {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        padding: 0.75rem 1rem;
        color: #fff;
    }

    .fi-panel-portal .client-portal-hero__content h2 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 500;
        letter-spacing: -0.01em;
    }

    .fi-panel-portal .client-portal-hero__content .client-portal-kicker {
        display: none;
    }

    /* ===== Sekcje / karty treści ===== */
    .fi-panel-portal .client-portal-section,
    .fi-panel-portal .portal-card {
        margin-bottom: 0.75rem;
        padding: 0.875rem 1rem;
        border: 1px solid var(--sor-portal-card-border);
        border-radius: var(--sor-portal-radius-card);
        background: var(--sor-portal-card);
        box-shadow: none;
    }

    .fi-panel-portal .client-portal-section + .client-portal-section {
        margin-top: 0;
    }

    .fi-panel-portal .portal-card-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.625rem;
    }

    .fi-panel-portal .portal-card-title > p,
    .fi-panel-portal .portal-card-title > h3 {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--sor-portal-ink);
    }

    .fi-panel-portal .portal-status-pill {
        font-size: 0.6875rem;
        padding: 0.125rem 0.5rem;
        border-radius: var(--sor-portal-radius-control);
        background: var(--sor-portal-accent-bg);
        color: var(--sor-portal-accent);
        font-weight: 500;
        white-space: nowrap;
    }

    .fi-panel-portal .portal-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.625rem;
        font-size: 0.8125rem;
    }

    .fi-panel-portal .portal-info-grid .label {
        margin: 0 0 0.125rem;
        font-size: 0.6875rem;
        color: var(--sor-portal-muted);
    }

    .fi-panel-portal .portal-info-grid .value {
        margin: 0;
        color: var(--sor-portal-ink);
        font-weight: 500;
    }

    .fi-panel-portal .portal-desktop-grid {
        display: block;
    }

    @media (min-width: 640px) {
        .fi-panel-portal .portal-desktop-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.875rem;
            align-items: start;
        }
    }

    .fi-panel-portal .portal-route-row {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.25rem 0;
        font-size: 0.8125rem;
    }

    .fi-panel-portal .portal-route-row .day {
        color: var(--sor-portal-secondary);
    }

    .fi-panel-portal .portal-route-row .empty {
        color: var(--sor-portal-muted);
    }

    .fi-panel-portal .portal-contact-row {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        margin-bottom: 0.625rem;
    }

    .fi-panel-portal .portal-contact-row:last-child {
        margin-bottom: 0;
    }

    .fi-panel-portal .portal-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-size: 0.8125rem;
        font-weight: 500;
    }

    .fi-panel-portal .portal-avatar.client {
        background: var(--sor-portal-accent-bg);
        color: var(--sor-portal-accent);
    }

    .fi-panel-portal .portal-avatar.pilot {
        background: var(--sor-portal-success-bg);
        color: var(--sor-portal-success);
    }

    .fi-panel-portal .portal-contact-row p:first-child {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--sor-portal-ink);
    }

    .fi-panel-portal .portal-contact-row p:last-child {
        margin: 0;
        font-size: 0.75rem;
        color: var(--sor-portal-secondary);
    }

    /* ===== Program: akordeon ===== */
    .fi-panel-portal .portal-day-accordion {
        border-top: 1px solid var(--sor-portal-card-border);
        padding-top: 0.625rem;
        margin-top: 0.625rem;
    }

    .fi-panel-portal .portal-day-accordion:first-of-type {
        border-top: none;
        margin-top: 0;
        padding-top: 0;
    }

    .fi-panel-portal .portal-day-accordion > summary {
        cursor: pointer;
        list-style: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
    }

    .fi-panel-portal .portal-day-accordion > summary::-webkit-details-marker {
        display: none;
    }

    .fi-panel-portal .portal-day-accordion > summary p:first-child {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--sor-portal-ink);
    }

    .fi-panel-portal .portal-day-accordion > summary p:last-child {
        margin: 0;
        font-size: 0.75rem;
        color: var(--sor-portal-muted);
        white-space: nowrap;
    }

    .fi-panel-portal .portal-day-item {
        padding: 0.625rem 0 0;
    }

    .fi-panel-portal .portal-day-item > p:first-child {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--sor-portal-ink);
    }

    .fi-panel-portal .portal-day-item > .portal-day-time {
        margin: 0.125rem 0 0;
        font-size: 0.75rem;
        color: var(--sor-portal-secondary);
    }

    /* ===== Hotele: siatka pokoi ===== */
    .fi-panel-portal .portal-room-group-header {
        margin-bottom: 0.75rem;
    }

    .fi-panel-portal .portal-room-group-header p:first-child {
        margin: 0 0 0.125rem;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .fi-panel-portal .portal-room-group-header p:last-child {
        margin: 0;
        font-size: 0.75rem;
        color: var(--sor-portal-secondary);
    }

    .fi-panel-portal .portal-room-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.375rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    @media (min-width: 640px) {
        .fi-panel-portal .portal-room-grid {
            grid-template-columns: repeat(8, minmax(0, 1fr));
        }
    }

    .fi-panel-portal .portal-room-slot {
        box-sizing: border-box;
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        background: var(--sor-portal-muted-bg);
        border-radius: var(--sor-portal-radius-control);
        padding: 0.375rem 0.25rem;
        text-align: center;
    }

    .fi-panel-portal .portal-room-slot .idx {
        margin: 0;
        font-size: 0.625rem;
        color: var(--sor-portal-muted);
        line-height: 1.2;
    }

    .fi-panel-portal .portal-room-slot input {
        box-sizing: border-box;
        display: block;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        text-align: center;
        font-size: 0.75rem;
        padding: 0.25rem 0;
        border: none;
        background: transparent;
        color: var(--sor-portal-ink);
        border-radius: 4px;
    }

    .fi-panel-portal .portal-room-slot input:focus {
        outline: 1px solid var(--sor-portal-accent);
        background: #fff;
    }

    .fi-panel-portal .portal-room-slot .room-readonly {
        display: block;
        margin-top: 0.125rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--sor-portal-ink);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .fi-panel-portal .portal-card {
        overflow: hidden;
        min-width: 0;
    }

    /* ===== Checklista / listy ===== */
    .fi-panel-portal .portal-check-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8125rem;
        padding: 0.375rem 0;
        border-top: 1px solid var(--sor-portal-card-border);
    }

    .fi-panel-portal .portal-check-row:first-of-type {
        border-top: none;
    }

    .fi-panel-portal .client-portal-kicker {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--sor-portal-muted);
    }

    .fi-panel-portal .portal-link {
        color: var(--sor-portal-accent);
        font-weight: 600;
        text-decoration: none;
    }

    .fi-panel-portal .portal-link:hover {
        text-decoration: underline;
    }

    .fi-panel-portal .portal-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: var(--sor-portal-radius-control);
        background: var(--sor-portal-ink);
        color: #fff;
        font-size: 0.8125rem;
        font-weight: 600;
        text-decoration: none;
        border: none;
    }

    .fi-panel-portal .portal-btn-primary:hover {
        background: #1f1f1d;
        color: #fff;
    }

    .fi-panel-portal .portal-notice {
        margin-bottom: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: var(--sor-portal-radius-card);
        font-size: 0.8125rem;
    }

    .fi-panel-portal .portal-notice--amber {
        border: 1px solid #E8D5A8;
        background: #FBF6EB;
        color: #6B5420;
    }

    .fi-panel-portal .portal-notice--accent {
        border: none;
        background: var(--sor-portal-accent-bg);
        color: var(--sor-portal-accent);
    }

    .fi-panel-portal .portal-muted {
        color: var(--sor-portal-secondary);
        font-size: 0.8125rem;
    }

    /* Form controls inside portal cards */
    .fi-panel-portal .portal-card input:not([type="checkbox"]):not([type="radio"]),
    .fi-panel-portal .portal-card select,
    .fi-panel-portal .portal-card textarea,
    .fi-panel-portal .client-portal-section input:not([type="checkbox"]):not([type="radio"]),
    .fi-panel-portal .client-portal-section select,
    .fi-panel-portal .client-portal-section textarea {
        border-color: var(--sor-portal-control-border);
        border-radius: var(--sor-portal-radius-control);
    }

    /* Livewire / Filament sections inside pilot pages */
    .fi-panel-portal .sor-lw-card {
        border: 1px solid var(--sor-portal-card-border) !important;
        border-radius: var(--sor-portal-radius-card) !important;
        background: var(--sor-portal-card) !important;
        box-shadow: none !important;
    }

    .fi-panel-portal .fi-section {
        border-radius: var(--sor-portal-radius-card);
        border-color: var(--sor-portal-card-border);
        background: var(--sor-portal-card);
        box-shadow: none;
    }

    .fi-panel-portal .fi-section-content-ctn,
    .fi-panel-portal .fi-section-header {
        background: transparent;
    }

    /* ===== Layout jak mockup: wąska kolumna → max 900px na desktopie ===== */
    .fi-panel-portal .fi-main-ctn,
    .fi-panel-portal .fi-main > .fi-width-constrained,
    .fi-panel-portal .fi-page {
        max-width: 900px !important;
        margin-left: auto;
        margin-right: auto;
        width: 100%;
    }

    .fi-panel-portal .fi-page-content {
        gap: 0;
    }

    .fi-panel-portal .portal-preview-banner,
    .fi-panel-portal .client-portal-trip-nav,
    .fi-panel-portal .client-portal-hero {
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Ukryj zbędny chrome Filament gdy jest hero / nawigacja wycieczki */
    .fi-panel-portal:has(.client-portal-trip-nav) .fi-header,
    .fi-panel-portal:has(.client-portal-hero) .fi-header,
    .fi-panel-portal:has(.client-portal-trip-nav) .fi-page-header,
    .fi-panel-portal:has(.client-portal-hero) .fi-page-header,
    .fi-panel-portal:has(.client-portal-trip-nav) .fi-header-heading,
    .fi-panel-portal:has(.client-portal-hero) .fi-header-heading {
        display: none !important;
    }

    .fi-panel-portal:has(.client-portal-trip-nav) .fi-page-main,
    .fi-panel-portal:has(.client-portal-hero) .fi-page-main {
        padding-top: 0.25rem;
    }

    /* Filament buttons w portalu — mniej „adminowy” primary */
    .fi-panel-portal .fi-btn-color-primary {
        --bg: var(--sor-portal-ink);
        --text: #fff;
    }

    @media (min-width: 640px) {
        .fi-panel-portal .client-portal-hero {
            height: 168px;
        }

        .fi-panel-portal .client-portal-hero__content {
            padding: 1rem 1.25rem;
        }

        .fi-panel-portal .client-portal-hero__content h2 {
            font-size: 1.25rem;
        }

        .fi-panel-portal .portal-card {
            padding: 1rem 1.125rem;
            margin-bottom: 0.875rem;
        }

        .fi-panel-portal .portal-desktop-grid {
            gap: 0.875rem;
        }

        .fi-panel-portal .portal-desktop-grid > div > .portal-card:last-child {
            margin-bottom: 0;
        }
    }

    /* Lista wycieczek: czytelniejsze karty na desktopie */
    @media (min-width: 640px) {
        .fi-panel-portal .client-portal-card__media {
            aspect-ratio: 16 / 9;
            max-height: 160px;
        }
    }

    /* Wycisz sidebar / topbar, żeby treść portalu dominowała */
    .fi-panel-portal .fi-sidebar {
        background: #FAFAF7;
        border-color: var(--sor-portal-card-border);
    }

    .fi-panel-portal .fi-topbar {
        background: var(--sor-portal-bg);
        border-color: var(--sor-portal-card-border);
    }

    /* Program klienta — akordeon / lista punktów */
    .fi-panel-portal .client-portal-program {
        margin-bottom: 0.75rem;
        padding: 0.875rem 1rem;
        border: 1px solid var(--sor-portal-card-border);
        border-radius: var(--sor-portal-radius-card);
        background: var(--sor-portal-card);
    }

    .fi-panel-portal .client-portal-program__item {
        margin: 0;
        padding: 0.55rem 0;
        background: transparent;
        border-radius: 0;
        border-top: 1px solid var(--sor-portal-card-border);
        color: var(--sor-portal-ink);
        line-height: 1.45;
        font-size: 0.8125rem;
    }

    .fi-panel-portal .client-portal-program__list > .client-portal-program__item:first-child {
        border-top: none;
        padding-top: 0.35rem;
    }

    .fi-panel-portal .client-portal-program__time {
        color: var(--sor-portal-accent);
        font-weight: 600;
        font-size: 0.75rem;
        margin-right: 0.4rem;
    }

    .fi-panel-portal .client-portal-program__bullet {
        color: var(--sor-portal-muted);
        margin-right: 0.2rem;
    }

    .fi-panel-portal .client-portal-program__desc {
        color: var(--sor-portal-secondary);
    }

    .fi-panel-portal .client-portal-program__children {
        margin: 0.35rem 0 0;
        padding: 0 0 0 0.85rem;
        list-style: none;
    }

    .fi-panel-portal .client-portal-program__item--child {
        border-top: none;
        padding: 0.2rem 0;
        font-size: 0.8125rem;
    }

    /* Filament infolist w kartach portalu */
    .fi-panel-portal .fi-infolist .fi-section {
        border: 1px solid var(--sor-portal-card-border);
        border-radius: var(--sor-portal-radius-card);
        background: var(--sor-portal-card);
        box-shadow: none;
    }
</style>
