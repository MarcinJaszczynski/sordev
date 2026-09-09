<style>
    /*
     * Typografia zaplecza — wzorzec: Operacje (Transport/Hotele/Pilot), wartości w px.
     * Wcześniej rem × rosnący --admin-root-font dawało podwójne skalowanie
     * (np. body ~16–19px przy root 16–19px zamiast faktycznych 13px z mockupu).
     * Tokeny są w px (jak Operacje); root zostaje lekko skalowany tylko pod Tailwind rem.
     *
     * Referencja desktop (≥768): body/input/cell 13px, label 12px, helper/th 11px, heading 15px.
     */
    :root {
        --admin-root-font: 14px;
        --admin-line-height: 1.5;
        --admin-body-size: 12.5px;
        --admin-input-size: 12.5px;
        --admin-label-size: 11.5px;
        --admin-helper-size: 11px;
        --admin-heading-size: 14px;
        --admin-table-cell-size: 12.5px;
        --admin-table-header-size: 10.5px;
        --admin-sidebar-label-size: 12.5px;
        --admin-topbar-size: 12.5px;
        --admin-table-min-width: 36rem;
        --admin-column-min-width: 18rem;
        --admin-touch-min: 38px;

        /* SOR41 design tokens — docs/DESIGN_SYSTEM.md */
        --sor-brand-primary: #B45309;
        --sor-brand-primary-hover: #92400E;
        --sor-brand-primary-soft: #FFFBEB;
        --sor-brand-secondary: #1E3A5F;
        --sor-surface: #F8FAFC;
        --sor-surface-elevated: #FFFFFF;
        --sor-border: #E2E8F0;
        --sor-border-strong: #CBD5E1;
        --sor-text: #0F172A;
        --sor-text-muted: #64748B;
        --sor-success: #059669;
        --sor-warning: #D97706;
        --sor-danger: #DC2626;
        --sor-info: #0284C7;
        --sor-module-events: #2563EB;
        --sor-module-finance: #059669;
        --sor-module-executive: #7C3AED;
        --sor-module-contacts: #0891B2;
        --sor-module-people: #C2410C;
        --sor-module-dictionaries: #64748B;
        --sor-module-system: #475569;
        --sor-module-pilot-trips: #0D9488;
        --sor-module-pilot-settlements: #059669;
        --sor-radius-sm: 0.375rem;
        --sor-radius-md: 0.5rem;
        --sor-radius-lg: 0.75rem;
        --sor-radius-xl: 1rem;
        --sor-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
        --sor-shadow-md: 0 4px 14px rgba(15, 23, 42, 0.08);
        --sor-shadow-lg: 0 12px 32px rgba(15, 23, 42, 0.12);
        --sor-transition: 180ms cubic-bezier(0.4, 0, 0.2, 1);

        /* Pola formularzy (Filament + Livewire) */
        --sor-field-bg: #e5e7eb;
        --sor-field-border: #6b7280;
        --sor-field-text: #111827;
        --sor-field-placeholder: #4b5563;

        /* Alias legacy (kanban, starsze widoki) → jedna paleta SOR */
        --f-bg: var(--sor-surface);
        --f-surface: var(--sor-surface-elevated);
        --f-text: var(--sor-text);
        --f-muted: var(--sor-text-muted);
        --f-border: var(--sor-border);
        --f-primary: var(--sor-module-pilot-trips);
    }

    :is(.dark, html[data-theme="dark"]) {
        --sor-surface: #18181b;
        --sor-surface-elevated: #27272a;
        --sor-border: #3f3f46;
        --sor-border-strong: #52525b;
        --sor-text: #f4f4f5;
        --sor-text-muted: #a1a1aa;
        --sor-brand-primary-soft: rgb(180 83 9 / 0.2);
        --sor-field-bg: #18181b;
        --sor-field-border: #a1a1aa;
        --sor-field-text: #f4f4f5;
        --sor-field-placeholder: #a1a1aa;
    }

    /* ≥640: lekko bliżej skali Operacji */
    @media (min-width: 640px) {
        :root {
            --admin-root-font: 14px;
            --admin-body-size: 12.5px;
            --admin-input-size: 12.5px;
            --admin-label-size: 12px;
            --admin-helper-size: 11px;
            --admin-heading-size: 14.5px;
            --admin-table-cell-size: 12.5px;
            --admin-table-header-size: 11px;
            --admin-sidebar-label-size: 12.5px;
            --admin-topbar-size: 12.5px;
            --admin-table-min-width: 40rem;
        }
    }

    /* ≥768: referencja Operacji (faktyczne px z mockupu / transport-page-styles) */
    @media (min-width: 768px) {
        :root {
            --admin-root-font: 14px;
            --admin-body-size: 13px;
            --admin-input-size: 13px;
            --admin-label-size: 12px;
            --admin-helper-size: 11px;
            --admin-heading-size: 15px;
            --admin-table-cell-size: 13px;
            --admin-table-header-size: 11px;
            --admin-sidebar-label-size: 13px;
            --admin-topbar-size: 13px;
            --admin-table-min-width: 44rem;
            --admin-column-min-width: 19rem;
        }
    }

    /* ≥1280: ta sama skala typografii, szerszy layout */
    @media (min-width: 1280px) {
        :root {
            --admin-root-font: 14px;
            --admin-body-size: 13px;
            --admin-input-size: 13px;
            --admin-label-size: 12px;
            --admin-helper-size: 11.5px;
            --admin-heading-size: 15px;
            --admin-table-cell-size: 13px;
            --admin-table-header-size: 11px;
            --admin-sidebar-label-size: 13px;
            --admin-topbar-size: 13px;
            --admin-table-min-width: 48rem;
            --admin-column-min-width: 20rem;
        }
    }

    /* ≥1536: +0.5px względem referencji */
    @media (min-width: 1536px) {
        :root {
            --admin-root-font: 15px;
            --admin-body-size: 13.5px;
            --admin-input-size: 13.5px;
            --admin-label-size: 12.5px;
            --admin-helper-size: 12px;
            --admin-heading-size: 15.5px;
            --admin-table-cell-size: 13.5px;
            --admin-table-header-size: 11.5px;
            --admin-sidebar-label-size: 13.5px;
            --admin-topbar-size: 13.5px;
            --admin-table-min-width: 52rem;
        }
    }

    /* ≥2560: +1px względem referencji (czytelność na dużym monitorze, bez puchnięcia do ~19px) */
    @media (min-width: 2560px) {
        :root {
            --admin-root-font: 15px;
            --admin-body-size: 14px;
            --admin-input-size: 14px;
            --admin-label-size: 13px;
            --admin-helper-size: 12px;
            --admin-heading-size: 16px;
            --admin-table-cell-size: 14px;
            --admin-table-header-size: 12px;
            --admin-sidebar-label-size: 14px;
            --admin-topbar-size: 14px;
            --admin-table-min-width: 56rem;
            --admin-column-min-width: 22rem;
        }
    }

    /* ≥3840: +1.5px względem referencji */
    @media (min-width: 3840px) {
        :root {
            --admin-root-font: 16px;
            --admin-body-size: 14.5px;
            --admin-input-size: 14.5px;
            --admin-label-size: 13.5px;
            --admin-helper-size: 12.5px;
            --admin-heading-size: 17px;
            --admin-table-cell-size: 14.5px;
            --admin-table-header-size: 12.5px;
            --admin-sidebar-label-size: 14.5px;
            --admin-topbar-size: 14.5px;
        }
    }

    html {
        font-size: var(--admin-root-font);
    }

    body {
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }

    .fi-body,
    .fi-main,
    .fi-page,
    .fi-page > section,
    .fi-section,
    .fi-section-content,
    .fi-section-content-ctn,
    .fi-fo-component-ctn,
    .fi-wi,
    .fi-tabs,
    .fi-ta,
    .fi-ta-content,
    .fi-ta-ctn,
    .fi-ta-table-ctn {
        min-width: 0;
        max-width: 100%;
    }

    /* fi-layout — min-width dla overflow w tabelach */
    .fi-layout {
        min-width: 0;
    }

    /* Modal window must NOT get max-width:100% — Filament controls its width via props (max-w-2xl etc.) */
    .fi-modal-window {
        min-width: 0;
    }

    .fi-main {
        font-size: var(--admin-body-size);
        line-height: var(--admin-line-height);
        /* overflow-x intentionally omitted: hiding it blocks child table scrollbars */
    }

    /*
     * Utility Tailwind w treści zaplecza → ta sama skala co tokeny.
     * (text-sm/xs to rem od html; przy starym root 16–19px puchły vs Operacje 13/12px)
     */
    .fi-main .text-xs,
    .fi-modal-window .text-xs,
    .fi-main .text-\[11px\],
    .fi-main .text-\[12px\] {
        font-size: var(--admin-helper-size) !important;
    }

    .fi-main .text-sm,
    .fi-modal-window .text-sm,
    .fi-main .text-\[13px\],
    .fi-main .text-base,
    .fi-modal-window .text-base {
        font-size: var(--admin-body-size) !important;
    }

    .fi-main .text-lg,
    .fi-modal-window .text-lg {
        font-size: var(--admin-heading-size) !important;
    }

    .fi-page,
    .fi-page-content {
        /* keep child horizontal scrollers and popovers visible */
        overflow-x: visible;
    }

    .fi-page-content,
    .fi-simple-main-ctn {
        padding-inline: clamp(0.5rem, 1vw, 0.85rem);
    }

    .fi-section-header-heading,
    .fi-modal-heading,
    .fi-header-heading {
        font-size: var(--admin-heading-size);
        font-weight: 700;
        line-height: 1.3;
        letter-spacing: 0.01em;
        overflow-wrap: anywhere;
    }

    .fi-fo-field-wrp-label > span {
        font-size: var(--admin-label-size);
        font-weight: 700;
        color: rgb(17 24 39);
        letter-spacing: 0.01em;
    }

    .dark .fi-fo-field-wrp-label > span {
        color: rgb(243 244 246);
    }

    .fi-fo-field-wrp-helper-text {
        font-size: var(--admin-helper-size);
        line-height: 1.45;
    }

    .fi-input,
    .fi-select-input,
    .fi-textarea,
    .fi-fo-date-time-picker input,
    .ts-control,
    .choices__inner,
    .fi-main input:not([type="checkbox"]):not([type="radio"]):not([type="range"]),
    .fi-main select,
    .fi-main textarea,
    .sor-lw-field,
    .pilot-field {
        background-color: var(--sor-field-bg);
        border: 1px solid var(--sor-field-border);
        color: var(--sor-field-text);
        font-size: var(--admin-input-size);
        color-scheme: light;
    }

    .fi-input,
    .fi-select-input,
    .fi-textarea,
    .fi-fo-date-time-picker input,
    .fi-input-wrp,
    .ts-control,
    .choices__inner {
        font-size: var(--admin-input-size);
    }

    .fi-main input:not([type="checkbox"]):not([type="radio"]):not([type="range"]),
    .fi-main select,
    .fi-main textarea,
    .sor-lw-field,
    .pilot-field {
        border-radius: var(--sor-radius-md);
        padding: 0.5rem 0.65rem;
        line-height: 1.35;
    }

    .sor-lw-field,
    .pilot-field {
        min-height: var(--admin-touch-min);
        width: 100%;
    }

    :is(.dark, html[data-theme="dark"]) .fi-input,
    :is(.dark, html[data-theme="dark"]) .fi-select-input,
    :is(.dark, html[data-theme="dark"]) .fi-textarea,
    :is(.dark, html[data-theme="dark"]) .fi-fo-date-time-picker input,
    :is(.dark, html[data-theme="dark"]) .ts-control,
    :is(.dark, html[data-theme="dark"]) .choices__inner,
    :is(.dark, html[data-theme="dark"]) .fi-main input:not([type="checkbox"]):not([type="radio"]):not([type="range"]),
    :is(.dark, html[data-theme="dark"]) .fi-main select,
    :is(.dark, html[data-theme="dark"]) .fi-main textarea,
    :is(.dark, html[data-theme="dark"]) .sor-lw-field,
    :is(.dark, html[data-theme="dark"]) .pilot-field {
        color-scheme: dark;
    }

    .fi-main input::placeholder,
    .fi-main textarea::placeholder {
        color: var(--sor-field-placeholder);
        opacity: 1;
    }

    .fi-main select option {
        background-color: var(--sor-field-bg);
        color: var(--sor-field-text);
    }

    .fi-input:focus,
    .fi-select-input:focus,
    .fi-textarea:focus,
    .fi-fo-date-time-picker input:focus,
    .ts-control:focus-within,
    .choices__inner:focus-within,
    .fi-main input:focus,
    .fi-main select:focus,
    .fi-main textarea:focus,
    .sor-lw-field:focus,
    .pilot-field:focus {
        outline: none;
        border-color: var(--sor-brand-primary);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--sor-brand-primary) 28%, transparent);
    }

    /*
     * Pola wpisywania — zawsze od lewej.
     * Kolumny tabeli z alignCenter() ustawiają text-center na wrapperze i inputach.
     */
    .fi-input,
    .fi-select-input,
    .fi-textarea,
    .fi-input-wrp-input .fi-input,
    .fi-main input:not([type="checkbox"]):not([type="radio"]):not([type="range"]),
    .fi-main select,
    .fi-main textarea,
    .sor-lw-field,
    .pilot-field,
    .fi-ta-text-input .fi-input,
    .fi-fo-rich-editor .ProseMirror,
    .tiptap-editor .ProseMirror,
    .fi-global-search-field input {
        text-align: left !important;
        direction: ltr;
    }

    .fi-input[type="number"],
    .fi-main input[type="number"],
    .fi-ta-text-input input[type="number"] {
        text-align: right !important;
    }

    .fi-ta-col-wrp:has(.fi-input, .fi-textarea, .fi-select-input, .fi-ta-text-input) {
        justify-content: flex-start !important;
        text-align: left !important;
    }

    /* File inputs are particularly low-contrast by default. */
    .fi-main input[type="file"] {
        padding: 0.45rem 0.65rem;
        cursor: pointer;
    }

    .fi-main input[type="file"]::file-selector-button {
        margin-right: 0.75rem;
        padding: 0.45rem 0.7rem;
        border-radius: 0.5rem;
        border: 1px solid rgb(107 114 128);
        background: rgb(243 244 246);
        color: rgb(17 24 39);
        font-weight: 700;
    }

    .dark .fi-main input[type="file"]::file-selector-button {
        border-color: rgb(161 161 170);
        background: rgb(39 39 42);
        color: rgb(244 244 245);
    }

    .fi-fo-actions,
    .fi-form-actions,
    .fi-ac,
    .fi-ta-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .fi-sidebar-header,
    .fi-sidebar-nav,
    .fi-sidebar-item-label,
    .fi-sidebar-group-label {
        font-size: var(--admin-sidebar-label-size);
    }

    .fi-sidebar-item-icon,
    .fi-sidebar-group-icon {
        width: 1.15rem;
        height: 1.15rem;
    }

    .fi-topbar .fi-topbar-item-label,
    .fi-topbar .fi-global-search-input,
    .fi-topbar .custom-topbar-notifications button,
    .fi-topbar .custom-topbar-notifications .text-xs,
    .fi-topbar .custom-topbar-notifications .text-sm {
        font-size: var(--admin-topbar-size) !important;
    }

    @media (min-width: 1280px) {
        .fi-topbar {
            position: sticky;
            top: 0;
            z-index: 60;
        }

        .fi-topbar .custom-topbar-notifications .h-8.w-8 {
            width: 2rem;
            height: 2rem;
        }

        .fi-topbar .custom-topbar-notifications .h-4.w-4 {
            width: 1rem;
            height: 1rem;
        }
    }

    .fi-topbar,
    .fi-topbar nav,
    .fi-topbar nav > div,
    .fi-topbar .fi-topbar-end,
    .fi-topbar .fi-topbar-item-ctn {
        min-width: 0;
        overflow: visible !important;
    }

    .custom-topbar-notifications {
        position: relative;
        z-index: 160 !important;
        overflow: visible !important;
        flex: 0 1 auto;
        min-width: 0;
        max-width: 100%;
    }

    .fi-topbar .fi-topbar-end,
    .fi-topbar .custom-topbar-notifications .relative {
        overflow: visible !important;
    }

    .fi-topbar .custom-topbar-notifications .topbar-notifications-items {
        min-width: 0;
        overflow: visible;
    }

    .fi-topbar .custom-topbar-notifications,
    .fi-topbar .custom-topbar-notifications .text-gray-900,
    .fi-topbar .custom-topbar-notifications .font-medium {
        color: rgb(17 24 39) !important;
    }

    .fi-topbar .custom-topbar-notifications .text-gray-600,
    .fi-topbar .custom-topbar-notifications .text-gray-500 {
        color: rgb(75 85 99) !important;
    }

    .topbar-notify-trigger {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0.5rem;
        border-radius: 0.65rem;
        border: 1px solid transparent;
        background: transparent;
        transition: background-color 0.15s ease, border-color 0.15s ease;
        cursor: pointer;
    }

    .topbar-notify-trigger:hover,
    .topbar-notify-trigger--active {
        background: rgb(243 244 246);
        border-color: rgb(229 231 235);
    }

    .topbar-notify-icon {
        display: inline-flex;
        height: 2rem;
        width: 2rem;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        flex-shrink: 0;
    }

    .topbar-notify-icon--orange {
        background: rgb(255 237 213);
        color: rgb(234 88 12);
    }

    .topbar-notify-icon--violet {
        background: rgb(237 233 254);
        color: rgb(124 58 237);
    }

    .topbar-notify-icon--blue {
        background: rgb(219 234 254);
        color: rgb(37 99 235);
    }

    .topbar-notify-label {
        display: none;
        text-align: left;
        line-height: 1.15;
    }

    @media (min-width: 1280px) {
        .topbar-notify-label {
            display: block;
        }
    }

    .topbar-notify-title {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .topbar-notify-sub {
        display: block;
        font-size: 0.7rem;
        color: rgb(75 85 99);
    }

    .topbar-notify-badge {
        display: inline-flex;
        min-width: 1.65rem;
        height: 1.35rem;
        align-items: center;
        justify-content: center;
        padding: 0 0.4rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1;
    }

    .topbar-notify-badge--muted {
        background: rgb(243 244 246);
        color: rgb(107 114 128);
    }

    .topbar-notify-badge--hot {
        background: rgb(220 38 38);
        color: #fff;
    }

    .topbar-notify-badge--violet {
        background: rgb(124 58 237);
        color: #fff;
    }

    .topbar-notify-badge--blue {
        background: rgb(37 99 235);
        color: #fff;
    }

    .topbar-notify-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        padding-top: 0.5rem;
        width: 28rem;
        max-width: calc(100vw - 1.5rem);
        z-index: 9999;
    }

    @media (min-width: 1024px) {
        .topbar-notify-dropdown {
            left: auto;
            right: 0;
            max-width: 90vw;
        }
    }

    .topbar-notification-scroll {
        overflow-y: auto !important;
        overflow-x: hidden !important;
        max-height: 18rem;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        touch-action: pan-y;
    }

    .topbar-notification-panel {
        z-index: 9999 !important;
        width: 100%;
        border-radius: 0.75rem;
        border: 1px solid rgb(229 231 235);
        background-color: var(--sor-surface-elevated, #fff) !important;
        color: var(--sor-text, #111827) !important;
        border-color: var(--sor-border, #e5e7eb) !important;
        box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    }

    .topbar-notify-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgb(229 231 235);
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .topbar-notify-panel-link {
        font-size: 0.75rem;
        font-weight: 500;
        color: rgb(79 70 229);
        text-decoration: none;
    }

    .topbar-notify-panel-link:hover {
        color: rgb(67 56 202);
        text-decoration: underline;
    }

    .topbar-notify-section {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.45rem 1rem;
        background: rgb(249 250 251);
        border-bottom: 1px solid rgb(243 244 246);
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: rgb(107 114 128);
    }

    .topbar-notify-section-count {
        display: inline-flex;
        min-width: 1.25rem;
        align-items: center;
        justify-content: center;
        padding: 0.05rem 0.35rem;
        border-radius: 9999px;
        background: rgb(229 231 235);
        color: rgb(55 65 81);
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: none;
        letter-spacing: 0;
    }

    .topbar-notify-empty {
        padding: 1.5rem 1rem;
        text-align: center;
        font-size: 0.875rem;
        color: rgb(75 85 99);
    }

    .topbar-notify-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        text-decoration: none;
        transition: background-color 0.12s ease;
    }

    .topbar-notify-item:hover {
        background: rgb(249 250 251);
    }

    .topbar-notify-item--unread-orange { background: rgb(255 247 237 / 0.55); }
    .topbar-notify-item--unread-emerald { background: rgb(236 253 245 / 0.55); }
    .topbar-notify-item--unread-sky { background: rgb(240 249 255 / 0.55); }
    .topbar-notify-item--unread-violet { background: rgb(245 243 255 / 0.55); }
    .topbar-notify-item--unread-rose { background: rgb(255 241 242 / 0.55); }
    .topbar-notify-item--unread-indigo { background: rgb(238 242 255 / 0.55); }

    .topbar-notify-item-main {
        min-width: 0;
        flex: 1 1 auto;
    }

    .topbar-notify-item-title {
        font-size: 0.875rem;
        font-weight: 500;
        color: rgb(17 24 39) !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .topbar-notify-item-meta {
        margin-top: 0.15rem;
        font-size: 0.75rem;
        color: rgb(75 85 99) !important;
    }

    .topbar-notify-item-time {
        flex-shrink: 0;
        font-size: 0.7rem;
        color: rgb(107 114 128) !important;
        white-space: nowrap;
    }

    .topbar-notification-panel .text-sm,
    .topbar-notification-panel .font-medium,
    .topbar-notification-panel a:not(.topbar-notify-panel-link) {
        color: rgb(17 24 39) !important;
    }

    .topbar-notification-panel .topbar-notify-panel-link {
        color: rgb(79 70 229) !important;
    }

    .topbar-notification-panel .text-xs,
    .topbar-notification-panel .text-gray-500,
    .topbar-notification-panel .text-gray-600 {
        color: rgb(75 85 99) !important;
    }

    .fi-topbar .custom-topbar-notifications .topbar-notifications-items > .relative,
    .fi-topbar .custom-topbar-notifications .topbar-notifications-items > .flex {
        flex: 0 0 auto;
    }

    .fi-topbar .fi-topbar-end,
    .fi-topbar .custom-topbar-notifications,
    .fi-topbar .custom-topbar-notifications .relative {
        overflow: visible !important;
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-trigger:hover,
    :is(.dark, html[data-theme="dark"]) .topbar-notify-trigger--active {
        background: rgb(31 41 55);
        border-color: rgb(55 65 81);
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-title {
        color: rgb(243 244 246);
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-sub {
        color: rgb(156 163 175);
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-badge--muted {
        background: rgb(31 41 55);
        color: rgb(156 163 175);
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-section {
        background: rgb(17 24 39);
        border-bottom-color: rgb(31 41 55);
        color: rgb(156 163 175);
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-panel-head {
        border-bottom-color: rgb(55 65 81);
        color: rgb(243 244 246);
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-item {
        border-bottom-color: rgb(31 41 55);
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-item:hover {
        background: rgb(31 41 55);
    }

    :is(.dark, html[data-theme="dark"]) .topbar-notify-item-title {
        color: rgb(243 244 246) !important;
    }

    .fi-tabs {
        margin-inline: 0 !important;
        width: 100%;
        justify-content: flex-start;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .fi-tabs .fi-tabs-item {
        flex: 0 0 auto;
    }

    @media (max-width: 1024px) {
        .fi-topbar .custom-topbar-notifications .topbar-notifications-items {
            overflow-x: auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            max-height: min(68vh, 34rem);
            padding-bottom: 0.25rem;
        }

        .fi-tabs {
            padding-inline: 0.35rem;
            gap: 0.15rem;
        }

        .fi-tabs .fi-tabs-item {
            padding-inline: 0.65rem;
            padding-block: 0.45rem;
        }

        .fi-tabs .fi-tabs-item-label {
            font-size: 0.84rem;
        }
    }

    .fi-ta-content,
    .fi-ta-table-ctn,
    .fi-ta-ctn,
    .event-program-planner-scroll,
    .kanban-board {
        overflow-x: auto;
        overflow-y: visible; /* hidden blocked dropdowns/tooltips inside table cells */
        -webkit-overflow-scrolling: touch;
    }

    .fi-ta-table {
        width: 100%;
        min-width: var(--admin-table-min-width);
        table-layout: auto;
    }

    /* Zebra — co drugi wiersz ciemniejszy */
    .fi-ta-content table.fi-ta-table > tbody > tr:nth-child(even) > td,
    .fi-ta-content table.fi-ta-table > tbody > tr:nth-child(even) > th {
        background-color: #eef2f7 !important;
    }

    .fi-ta-content table.fi-ta-table > tbody > tr:nth-child(odd) > td,
    .fi-ta-content table.fi-ta-table > tbody > tr:nth-child(odd) > th {
        background-color: #ffffff;
    }

    .fi-ta-content table.fi-ta-table > tbody > tr:hover > td,
    .fi-ta-content table.fi-ta-table > tbody > tr:hover > th {
        background-color: #e2e8f0 !important;
    }

    /* Zebra dla prostych tabel w widokach Blade (raporty) */
    .admin-zebra-table tbody tr:nth-child(even) {
        background-color: #eef2f7;
    }

    .admin-zebra-table tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }

    .admin-zebra-table tbody tr:hover {
        background-color: #e2e8f0;
    }

    .fi-ta-cell,
    .fi-ta-header-cell {
        vertical-align: top;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
        padding-top: 0.65rem;
        padding-bottom: 0.65rem;
    }

    .fi-ta-cell,
    .fi-ta-cell * {
        font-size: var(--admin-table-cell-size) !important;
        line-height: 1.45 !important;
    }

    .fi-ta-cell .program-point-description-preview,
    .fi-ta-cell .program-point-description-full {
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .admin-program-toolbar {
        position: sticky;
        top: 3.25rem;
        z-index: 20;
        background: rgb(var(--gray-50));
        padding-bottom: 0.25rem;
        margin-bottom: 0.5rem;
    }

    /* Dropdown filtrów tabeli programu — toolbar nad ciałem; scroll poziomy zostaje na content */
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-ctn {
        overflow: visible;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-header-ctn {
        position: relative;
        z-index: 35;
        overflow: visible !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-header-toolbar {
        position: relative;
        z-index: 36;
        overflow: visible !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-content,
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-table-ctn {
        position: relative;
        z-index: 1;
        overflow-x: auto !important;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        max-width: 100%;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-table {
        width: 100%;
        min-width: 0;
        table-layout: auto;
    }

    /* ===== Program point rows — soft card look (table, pełne dane) ===== */
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-table {
        border-collapse: separate;
        border-spacing: 0 0.5rem;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-row.epp-program-row > td {
        background: #fff !important;
        border-top: 1px solid #e5e3da;
        border-bottom: 1px solid #e5e3da;
        transition: background 0.15s, border-color 0.15s;
        overflow: hidden;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-cell.epp-name-col {
        overflow: hidden !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-cell.epp-name-col:has(.epp-name-cell--has-set-preview) {
        overflow: visible !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-row.epp-program-row > td:first-child {
        border-left: 1px solid #e5e3da;
        border-radius: 0.75rem 0 0 0.75rem;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-row.epp-program-row > td:last-child {
        border-right: 1px solid #e5e3da;
        border-radius: 0 0.75rem 0.75rem 0;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-row.epp-program-row:hover > td {
        background: #fafaf8 !important;
        border-color: #d3d1c7;
    }

    .dark .fi-page:has(.admin-program-toolbar) .fi-ta-row.epp-program-row > td {
        background: rgb(var(--gray-900)) !important;
        border-color: rgb(var(--gray-700));
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-reorder-handle {
        min-width: 2.75rem;
        min-height: 2.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .fi-page:has(.admin-program-toolbar) .sortable-ghost,
    .fi-page:has(.admin-program-toolbar) .fi-ta-row.sortable-ghost {
        opacity: 0.55 !important;
    }

    .fi-page:has(.admin-program-toolbar) .sortable-drag,
    .fi-page:has(.admin-program-toolbar) .fi-ta-row.sortable-drag {
        opacity: 0.95 !important;
    }

    /* Program: ta sama skala co reszta zaplecza (wzorzec Operacje) */
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell,
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell * {
        font-size: var(--admin-table-cell-size) !important;
        line-height: 1.35 !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-table th,
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-table td {
        vertical-align: top;
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-header-cell,
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-header-cell * {
        font-size: var(--admin-table-header-size) !important;
        font-weight: 700 !important;
    }

    /* Nazwa + kontrahent / rez / płatność — ciasny layout listy programu */
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-name-col,
    .fi-ta-cell.epp-name-col {
        width: 12rem !important;
        min-width: 10rem !important;
        max-width: 14rem !important;
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: anywhere;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-contractor-col,
    .fi-ta-cell.epp-contractor-col {
        width: 7.5rem !important;
        min-width: 6.5rem !important;
        max-width: 9rem !important;
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: anywhere;
        vertical-align: top;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-rez-col,
    .fi-ta-cell.epp-rez-col {
        width: 5.5rem !important;
        min-width: 4.75rem !important;
        max-width: 6.5rem !important;
        white-space: normal !important;
        overflow: hidden !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-pay-col,
    .fi-ta-cell.epp-pay-col {
        width: 9.5rem !important;
        min-width: 8rem !important;
        max-width: 11rem !important;
        white-space: normal !important;
        overflow: hidden !important;
        vertical-align: top;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-finance-col,
    .fi-ta-cell.epp-finance-col {
        width: 7.25rem !important;
        min-width: 6.75rem !important;
        max-width: 8.25rem !important;
        white-space: nowrap !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
        overflow: hidden !important;
        vertical-align: top;
    }

    .epp-ops--finance {
        margin-top: 0;
        font-size: 0.65rem !important;
        line-height: 1.3 !important;
        min-width: 0;
        width: 100%;
    }

    .epp-ops--finance .epp-ops__row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.3rem;
        min-width: 0;
    }

    .epp-ops--finance .epp-ops__label {
        min-width: 0.7rem;
        width: 0.7rem;
        flex-shrink: 0;
        color: #888780;
        font-weight: 700;
        letter-spacing: 0;
        text-transform: none;
    }

    .epp-ops--finance .tabular-nums {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-money-col,
    .fi-ta-cell.epp-money-col {
        white-space: nowrap !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
        width: 1%;
        min-width: 5.5rem;
        vertical-align: top;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-doc-col,
    .fi-ta-cell.epp-doc-col {
        white-space: nowrap !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
        width: 4.5rem;
        min-width: 4.25rem;
        max-width: 5rem;
        vertical-align: top;
        text-align: center;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-payer-col,
    .fi-ta-cell.epp-payer-col {
        width: 5rem !important;
        min-width: 4.75rem !important;
        max-width: 5.5rem !important;
        vertical-align: top;
        padding-top: 0.35rem !important;
        padding-bottom: 0.35rem !important;
        overflow: hidden !important;
    }

    /* Filament SelectColumn dokłada min-w-48 — bez tego kolumna rozpycha całą tabelę. */
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-payer-col .fi-ta-select,
    .fi-ta-cell.epp-payer-col .fi-ta-select,
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-select.epp-payer-col,
    .fi-ta-select.epp-payer-col {
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        padding-inline: 0.35rem !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-payer-col .fi-select-input,
    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-payer-col select,
    .fi-ta-cell.epp-payer-col .fi-select-input,
    .fi-ta-cell.epp-payer-col select {
        max-width: 100%;
        min-width: 0 !important;
        min-height: 1.65rem !important;
        height: 1.65rem !important;
        padding: 0.1rem 0.35rem !important;
        font-size: var(--admin-helper-size) !important;
        line-height: 1.25 !important;
        border-radius: 6px !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-ta-cell.epp-payer-col .fi-input-wrp,
    .fi-ta-cell.epp-payer-col .fi-input-wrp {
        min-width: 0 !important;
        width: 100% !important;
        min-height: 1.65rem !important;
        height: 1.65rem !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers .fi-dropdown-panel {
        z-index: 50 !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-filters-dropdown .fi-fo-component-ctn {
        gap: 0.45rem;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-filters-dropdown .p-6 {
        padding: 0.75rem !important;
    }

    .dark .admin-program-toolbar {
        background: rgb(var(--gray-950));
    }

    .admin-program-toolbar__row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.35rem 0.65rem;
    }

    .admin-program-scope-filters {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.25rem;
    }

    .admin-program-scope-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        border: 1px solid rgb(var(--gray-300));
        background: #fff;
        color: rgb(var(--gray-700));
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1.2;
        padding: 0.22rem 0.55rem;
        cursor: pointer;
        transition: border-color 0.15s, background 0.15s, color 0.15s;
    }

    .admin-program-scope-btn:hover {
        border-color: rgb(var(--primary-400, 45 212 191));
        background: rgb(var(--gray-50));
    }

    .admin-program-scope-btn--active {
        border-color: rgb(var(--primary-500, 20 184 166));
        background: rgb(var(--primary-50, 240 253 250));
        color: rgb(var(--primary-700, 15 118 110));
    }

    .admin-program-scope-btn__count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.15rem;
        padding: 0.05rem 0.3rem;
        border-radius: 999px;
        background: rgb(var(--gray-200));
        color: rgb(var(--gray-700));
        font-size: 0.65rem;
        font-weight: 700;
    }

    .admin-program-scope-btn--active .admin-program-scope-btn__count {
        background: rgb(var(--primary-200, 153 246 228));
        color: rgb(var(--primary-800, 17 94 89));
    }

    .dark .admin-program-scope-btn {
        border-color: rgb(var(--gray-600));
        background: rgb(var(--gray-900));
        color: rgb(var(--gray-200));
    }

    .dark .admin-program-scope-btn--active {
        border-color: rgb(var(--primary-500, 20 184 166));
        background: rgba(20, 184, 166, 0.12);
        color: rgb(var(--primary-300, 94 234 212));
    }

    .dark .admin-program-scope-btn__count {
        background: rgb(var(--gray-700));
        color: rgb(var(--gray-200));
    }

    .admin-program-view-tabs {
        position: static;
        z-index: auto;
        background: transparent;
        padding-block: 0.15rem;
    }

    .fi-ta-header-cell,
    .fi-ta-header-cell * {
        font-size: var(--admin-table-header-size) !important;
        font-weight: 700 !important;
        line-height: 1.35 !important;
        text-transform: none;
        letter-spacing: 0.01em;
    }

    .fi-ta-pagination,
    .fi-ta-pagination *,
    .fi-ta-filters .fi-input,
    .fi-ta-filters .fi-select-input,
    .fi-ta-filters .fi-textarea,
    .fi-ta-actions button,
    .fi-ta-actions a {
        font-size: var(--admin-input-size) !important;
    }

    .fi-ta-badge,
    .fi-badge {
        font-size: clamp(0.72rem, 0.7rem + 0.15vw, 0.84rem) !important;
    }

    .hotel-plan-table-scroll {
        -webkit-overflow-scrolling: touch;
    }

    .event-program-planner-scroll {
        display: block;
        width: 100%;
        max-width: 100%;
        padding-bottom: 0.25rem;
    }

    .event-program-planner-surface {
        min-width: 100%;
    }

    .event-program-planner-scroll .fc {
        min-width: 44rem;
    }

    /* Lista punktów programu imprezy — sety, dni, hierarchia */
    .fi-ta-row.epp-table-row > td {
        border-top: 1px solid #e2e8f0;
        vertical-align: top;
    }

    /* Lista programu: nagłówek dnia na pełną szerokość (Filament Group), potem wiersze punktów */
    .fi-page:has(.admin-program-toolbar) .fi-ta-group-header {
        background: #e8eef9 !important;
        border-block: 1px solid #c7d2fe;
        padding: 0.55rem 0.9rem;
        gap: 0.75rem;
    }

    .fi-page:has(.admin-program-toolbar) tr:has(.fi-ta-group-header) > td {
        padding: 0 !important;
        border-top: 2px solid #94a3b8;
        background: #e8eef9 !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-group-header h4 {
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #1e3a8a;
    }

    .fi-page:has(.admin-program-toolbar) .fi-ta-group-header p {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.1rem;
    }

    .dark .fi-page:has(.admin-program-toolbar) .fi-ta-group-header {
        background: rgba(30, 58, 138, 0.28) !important;
        border-block-color: rgba(147, 197, 253, 0.35);
    }

    .dark .fi-page:has(.admin-program-toolbar) tr:has(.fi-ta-group-header) > td {
        border-top-color: #64748b;
        background: rgba(30, 58, 138, 0.28) !important;
    }

    .dark .fi-page:has(.admin-program-toolbar) .fi-ta-group-header h4 {
        color: #bfdbfe;
    }

    .fi-ta-row.epp-table-row--set-parent > td {
        background: #f8fafc !important;
        border-top: 2px solid #cbd5e1;
        padding-top: 0.55rem;
    }

    .fi-ta-row.epp-table-row--set-expanded > td {
        background: #f1f5f9 !important;
        border-bottom: none;
    }

    .fi-ta-row.epp-table-row--set-parent .epp-title {
        font-size: 0.8125rem;
        color: #0f172a;
    }

    .fi-ta-row.epp-table-row--set-child > td {
        background: #ffffff !important;
        border-top-color: #f1f5f9;
    }

    .fi-ta-row.epp-table-row--set-child-first > td {
        padding-top: 0.35rem;
    }

    .fi-ta-row.epp-table-row--set-child-last > td {
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 0.55rem;
    }

    .fi-ta-row.epp-table-row--set-parent + .fi-ta-row.epp-table-row--set-child > td {
        border-top: none;
    }

    .fi-ta-row.epp-table-row--single > td {
        background: #ffffff !important;
    }

    .fi-ta-row.epp-table-row--inactive > td {
        opacity: 0.72;
    }

    .fi-ta-row.epp-table-row--trashed > td {
        opacity: 0.55;
        background: #fef2f2 !important;
    }

    .fi-ta-row.epp-table-row--trashed .epp-title {
        text-decoration: line-through;
        color: #991b1b;
    }

    .fi-ta-row.epp-table-row--set-parent:hover > td,
    .fi-ta-row.epp-table-row--set-child:hover > td {
        background-color: #f8fafc !important;
    }

    .fi-ta-row.epp-table-row:hover > td {
        background-color: #f1f5f9 !important;
    }

    .epp-name-cell {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 0;
        max-width: 100%;
        position: relative;
    }

    .epp-name-time {
        display: inline-flex;
        align-items: baseline;
        gap: 0.15rem;
        font-size: var(--admin-helper-size);
        line-height: 1.25;
        color: #5f5e5a;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .epp-name-time__end,
    .epp-name-time__sep {
        color: #888780;
    }

    .dark .epp-name-time {
        color: #a1a1aa;
    }

    .dark .epp-name-time__end,
    .dark .epp-name-time__sep {
        color: #71717a;
    }

    .epp-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.3rem 0.4rem;
        min-width: 0;
    }

    .epp-title {
        font-size: 0.78rem !important;
        font-weight: 650 !important;
        line-height: 1.3 !important;
        color: #2c2c2a;
        word-break: break-word;
    }

    .dark .epp-title {
        color: #f5f5f4;
    }

    .epp-scope-pills {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.15rem;
        flex-shrink: 0;
    }

    .epp-mini-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        border: 1px solid transparent;
        font-size: 0.55rem !important;
        font-weight: 600 !important;
        line-height: 1.15 !important;
        padding: 0.06rem 0.32rem;
        white-space: nowrap;
        text-transform: lowercase;
    }

    .epp-mini-pill--program.epp-mini-pill--on {
        border-color: #93c5fd;
        background: #dbeafe;
        color: #1d4ed8;
    }

    .epp-mini-pill--program.epp-mini-pill--off {
        border-color: #dbeafe;
        background: #f8fafc;
        color: #93c5fd;
        opacity: 0.9;
    }

    .epp-mini-pill--cost.epp-mini-pill--on {
        border-color: #fdba74;
        background: #ffedd5;
        color: #c2410c;
    }

    .epp-mini-pill--cost.epp-mini-pill--off {
        border-color: #ffedd5;
        background: #fffaf5;
        color: #fdba74;
        opacity: 0.9;
    }

    .dark .epp-mini-pill--program.epp-mini-pill--on {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.18);
        color: #93c5fd;
    }

    .dark .epp-mini-pill--cost.epp-mini-pill--on {
        border-color: #f97316;
        background: rgba(249, 115, 22, 0.18);
        color: #fdba74;
    }

    .epp-mini-pill--on {
        /* legacy no-op — kolory per program/cost */
    }

    .epp-mini-pill--off {
        /* legacy no-op — kolory per program/cost */
    }

    .epp-point-sub {
        margin: 0;
        font-size: 0.68rem !important;
        line-height: 1.35 !important;
        font-weight: 400 !important;
        color: #6b7280;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        overflow: hidden;
    }

    .epp-contractor-cell {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        font-size: 0.72rem !important;
        line-height: 1.35 !important;
        color: #2c2c2a;
        font-weight: 500 !important;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
        min-width: 0;
        max-width: 100%;
    }

    .dark .epp-contractor-cell {
        color: #f5f5f4;
    }

    .epp-contractor-cell--link {
        text-decoration: none;
        color: #1d4ed8;
        transition: color 0.12s;
    }

    .epp-contractor-cell--link:hover {
        color: #1e40af;
        text-decoration: underline;
    }

    .dark .epp-contractor-cell--link {
        color: #93c5fd;
    }

    .epp-contractor-cell__name {
        font-weight: 600 !important;
        font-size: 0.72rem !important;
    }

    .epp-contractor-cell__meta {
        display: flex;
        flex-direction: column;
        gap: 0.05rem;
        font-size: 0.65rem !important;
        font-weight: 400 !important;
        color: #6b7280;
        line-height: 1.3 !important;
    }

    .dark .epp-contractor-cell__meta {
        color: #9ca3af;
    }

    .epp-contractor-cell--muted {
        color: #b0aea5;
        font-weight: 400;
    }

    .epp-contractor-cell--missing {
        display: none;
    }

    .epp-meta--compact {
        font-size: 0.72rem;
        line-height: 1.3;
        color: #5f5e5a;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .epp-meta__label {
        font-weight: 700;
        color: #888780;
        margin-right: 0.15rem;
    }

    .epp-meta--missing {
        color: #a16207;
        font-weight: 600;
    }

    .epp-name-actions {
        margin-top: 0.15rem;
    }

    .epp-task-btn {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        border: 1px solid #d3d1c7;
        background: #fff;
        padding: 0.05rem 0.35rem;
        font-size: 0.55rem !important;
        font-weight: 600 !important;
        color: #5f5e5a;
        line-height: 1.15 !important;
        flex-shrink: 0;
    }

    .epp-task-btn:hover {
        background: #f1efe8;
    }

    .epp-scope--inline {
        margin-top: 0.05rem;
        gap: 0.25rem;
    }

    .epp-scope--inline .epp-scope-chip {
        font-size: 0.625rem;
        padding: 0.1rem 0.4rem;
        border-radius: 0.35rem;
    }

    .epp-time-cell {
        display: flex;
        flex-direction: column;
        gap: 0.05rem;
        font-size: 0.6875rem;
        line-height: 1.3;
        color: #5f5e5a;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .epp-time-cell--muted,
    .epp-time-cell__end {
        color: #888780;
    }

    .fi-ta-cell.epp-time-col {
        width: 3.75rem;
        min-width: 3.75rem;
        max-width: 4rem;
        vertical-align: top;
    }

    /* (duplikat szerokości — trzymamy spójnie z blokiem programu powyżej) */
    .fi-ta-cell.epp-status-col {
        vertical-align: top;
        white-space: normal !important;
    }

    .epp-pay-status,
    .epp-rez-status {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
        min-width: 0;
        max-width: 100%;
    }

    .epp-pay-status__meta,
    .epp-rez-status__deadline {
        font-size: 0.68rem !important;
        line-height: 1.35 !important;
        color: #5f5e5a;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
    }

    /* zielony = zapłacone / OK */
    .epp-pay-status__meta--paid {
        color: #166534;
        background: #dcfce7;
        font-weight: 700;
        padding: 0.05rem 0.3rem;
        border-radius: 0.3rem;
    }

    /* pomarańczowy = do zapłaty w terminie / częściowo */
    .epp-pay-status__meta--pending,
    .epp-pay-status__meta--warn {
        color: #c2410c;
        font-weight: 600;
    }

    /* niebieski = reszta / zobowiązanie pilota */
    .epp-pay-status__meta--pilot {
        color: #1d4ed8;
        font-weight: 700;
    }

    /* czerwony = zaległość / po terminie */
    .epp-pay-status__meta--due {
        color: #b91c1c;
        font-weight: 700;
    }

    .epp-card__meta-line--paid {
        color: #27500a;
        background: #eaf3de;
        font-weight: 700;
        padding: 0.05rem 0.3rem;
        border-radius: 0.3rem;
        display: inline-block;
    }

    .epp-card__meta-line--pilot {
        color: #1d4ed8;
        font-weight: 700;
    }

    .epp-card__meta-line--warn {
        color: #b45309;
        font-weight: 600;
    }

    .epp-card__meta-line--due {
        color: #791f1f;
        font-weight: 600;
    }

    .epp-card__meta-line--pending {
        color: #633806;
        font-weight: 600;
    }

    .epp-status-pill {
        display: inline-block;
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        line-height: 1.3 !important;
        padding: 0.1rem 0.38rem;
        border-radius: 0.4rem;
        white-space: nowrap;
    }

    .epp-status-pill--danger {
        background: #fcebeb;
        color: #791f1f;
    }

    .epp-status-pill--success {
        background: #eaf3de;
        color: #27500a;
    }

    .epp-status-pill--warning {
        background: #faeeda;
        color: #633806;
    }

    .epp-status-pill--neutral {
        background: #f1efe8;
        color: #5f5e5a;
    }

    .epp-ops__due {
        color: #888780;
        font-weight: 500;
    }

    .epp-rez-status__deadline {
        font-size: 0.625rem;
        line-height: 1.35;
        color: #5f5e5a;
        white-space: normal;
        word-break: break-word;
    }

    .epp-rez-status__deadline--due {
        color: #791f1f;
        font-weight: 600;
    }

    .epp-rez-status__deadline--ok {
        color: #27500a;
    }

    .fi-ta-cell.epp-rez-col,
    .fi-ta-cell.epp-due-col,
    .fi-ta-cell.epp-notes-col {
        vertical-align: top;
        white-space: normal !important;
    }

    .fi-ta-cell.epp-due-col {
        min-width: 9rem;
        max-width: 14rem;
    }

    .fi-ta-cell.epp-notes-col {
        min-width: 5rem !important;
        max-width: 7rem !important;
        width: 5.5rem !important;
    }

    .epp-notes-preview {
        max-width: 100%;
        min-width: 0;
    }

    .epp-notes-preview__list {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        min-width: 0;
    }

    .epp-notes-preview__item {
        min-width: 0;
    }

    .epp-notes-preview__label {
        font-size: 0.65rem;
        font-weight: 600;
        line-height: 1.2;
        color: #4b5563;
        margin-bottom: 0.05rem;
    }

    .dark .epp-notes-preview__label {
        color: #d1d5db;
    }

    .epp-doc-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 0.4rem;
        text-decoration: none;
    }

    .epp-doc-icon__svg {
        width: 1.05rem;
        height: 1.05rem;
    }

    .epp-doc-icon--has {
        color: #0c447c;
        background: #e6f1fb;
    }

    .epp-doc-icon--has:hover {
        background: #d6e8f8;
    }

    .epp-doc-icon--warn {
        color: #633806;
        background: #faeeda;
    }

    .epp-doc-icon--empty {
        color: #888780;
        background: #f1efe8;
    }

    .epp-doc-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        max-width: 4.5rem;
        padding: 0.15rem 0.4rem;
        border-radius: 0.35rem;
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: 0.01em;
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .epp-doc-badge--has {
        color: #0f5132;
        background: #d1e7dd;
    }

    .epp-doc-badge--has:hover {
        background: #badbcc;
    }

    .epp-doc-badge--warn {
        color: #633806;
        background: #faeeda;
    }

    .epp-doc-badge--empty {
        color: #888780;
        background: transparent;
        font-weight: 500;
    }

    .epp-program-desc {
        margin-top: 0.15rem;
        font-size: 0.72rem;
        line-height: 1.3;
        color: #64748b;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        max-width: 100%;
    }

    .epp-invoice-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        max-width: 9.5rem;
        padding: 0.28rem 0.55rem;
        border-radius: 0.4rem;
        background: #ecfdf5;
        color: #065f46;
        box-shadow: inset 0 0 0 1px #6ee7b7;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .epp-invoice-btn:hover {
        background: #d1fae5;
    }

    .epp-invoice-btn--warn {
        background: #fffbeb;
        color: #92400e;
        box-shadow: inset 0 0 0 1px #fcd34d;
    }

    .epp-invoice-btn--empty {
        background: #f8fafc;
        color: #94a3b8;
        box-shadow: inset 0 0 0 1px #e2e8f0;
        font-weight: 600;
    }

    .epp-name-cell--set-parent {
        padding-left: 0.15rem;
    }

    .epp-name-cell--child {
        padding-left: 0.85rem;
    }

    .epp-name-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.5rem;
    }

    .epp-order {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        padding: 0.1rem 0.45rem;
        border-radius: 9999px;
        background: #e2e8f0;
        color: #334155;
        font-size: 0.72rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .epp-order--child {
        background: transparent;
        color: #64748b;
        min-width: auto;
        padding: 0;
        font-size: 0.7rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .epp-set-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.15rem 0.55rem 0.15rem 0.4rem;
        border-radius: 0.45rem;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1e40af;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        white-space: nowrap;
        line-height: 1.2;
        cursor: pointer;
        font-family: inherit;
    }

    .epp-set-badge:hover {
        background: #dbeafe;
        border-color: #93c5fd;
    }

    .epp-set-badge--expanded {
        background: #dbeafe;
        border-color: #60a5fa;
    }

    .epp-set-badge__chevron {
        display: inline-block;
        width: 0.35rem;
        height: 0.35rem;
        border-right: 1.5px solid #3b82f6;
        border-bottom: 1.5px solid #3b82f6;
        transform: rotate(-45deg);
        margin-right: 0.05rem;
        transition: transform 0.15s ease;
        flex-shrink: 0;
    }

    .epp-set-badge--expanded .epp-set-badge__chevron {
        transform: rotate(45deg);
        margin-top: -0.1rem;
    }

    .epp-set-badge__icon {
        display: inline-block;
        width: 0.55rem;
        height: 0.55rem;
        border: 1.5px solid #3b82f6;
        border-radius: 0.12rem;
        box-shadow: 0.1rem 0.1rem 0 0 #93c5fd;
        flex-shrink: 0;
    }

    .epp-set-badge__label {
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .epp-set-badge__count {
        padding-left: 0.35rem;
        border-left: 1px solid #bfdbfe;
        color: #3b82f6;
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0;
    }

    .epp-set-badge-wrap {
        position: relative;
        display: inline-flex;
    }

    .epp-note-markers {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        margin-top: 0.2rem;
    }

    .epp-note-marker {
        display: inline-flex;
        align-items: center;
        border-radius: 0.375rem;
        padding: 0.05rem 0.4rem;
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .epp-note-marker--office {
        background: #dbeafe;
        color: #1e40af;
    }

    .epp-note-marker--pilot {
        background: #ccfbf1;
        color: #0f766e;
    }

    .epp-name-cell--has-set-preview {
        position: relative;
    }

    .epp-set-preview {
        position: absolute;
        left: 0;
        top: calc(100% + 0.35rem);
        z-index: 130;
        width: min(22rem, 78vw);
        padding: 0.55rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.65rem;
        background: #ffffff;
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.14);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: translateY(0.2rem);
        transition: opacity 0.15s ease, visibility 0.15s ease, transform 0.15s ease;
    }

    .epp-name-cell--has-set-preview:hover .epp-set-preview,
    .epp-name-cell--has-set-preview:focus-within .epp-set-preview {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translateY(0);
    }

    .fi-resource-relation-managers .fi-ta-content,
    .fi-resource-relation-managers .fi-ta-table tbody td:has(.epp-name-cell--has-set-preview),
    .fi-ta-row.epp-table-row--set-parent > td {
        overflow: visible !important;
    }

    .epp-set-preview__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.45rem;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #334155;
    }

    .epp-set-preview__count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.35rem;
        height: 1.35rem;
        padding: 0 0.35rem;
        border-radius: 9999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.68rem;
        font-weight: 700;
    }

    .epp-set-preview__list {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        max-height: 14rem;
        overflow: auto;
    }

    .epp-set-preview__item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.2rem 0.45rem;
        width: 100%;
        padding: 0.45rem 0.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.45rem;
        background: #f8fafc;
        text-align: left;
        cursor: pointer;
        transition: border-color 0.15s, background 0.15s;
    }

    .epp-set-preview__item:hover {
        border-color: #93c5fd;
        background: #eff6ff;
    }

    .epp-set-preview__item--hidden {
        border-style: dashed;
        background: #fffbeb;
        border-color: #fcd34d;
    }

    .epp-set-preview__item--hidden:hover {
        background: #fef3c7;
        border-color: #f59e0b;
    }

    .epp-set-preview__item-main {
        display: flex;
        align-items: baseline;
        gap: 0.4rem;
        min-width: 0;
        grid-column: 1 / -1;
    }

    .epp-set-preview__order {
        flex-shrink: 0;
        font-size: 0.68rem;
        font-weight: 700;
        color: #64748b;
        font-variant-numeric: tabular-nums;
    }

    .epp-set-preview__name {
        font-size: 0.8rem;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.3;
        word-break: break-word;
    }

    .epp-set-preview__flags {
        grid-column: 1;
        font-size: 0.65rem;
        color: #b45309;
        line-height: 1.3;
    }

    .epp-set-preview__flags--ok {
        color: #64748b;
    }

    .epp-set-preview__action {
        grid-column: 2;
        grid-row: 1 / span 2;
        align-self: center;
        font-size: 0.65rem;
        font-weight: 700;
        color: #2563eb;
        white-space: nowrap;
    }

    .epp-set-preview__foot {
        margin-top: 0.45rem;
        padding-top: 0.4rem;
        border-top: 1px solid #e2e8f0;
        font-size: 0.65rem;
        color: #64748b;
        line-height: 1.35;
    }

    .epp-child-badge {
        display: none;
    }

    .epp-time {
        font-size: 0.75rem;
        font-weight: 600;
        color: #0f766e;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .epp-time--muted {
        color: #94a3b8;
        font-weight: 500;
    }

    .epp-type-icons {
        display: inline-flex;
        gap: 0.15rem;
    }

    .epp-type-icon {
        font-size: 0.9rem;
        line-height: 1;
    }

    .epp-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.3rem 0.4rem;
    }

    .epp-title {
        font-size: 0.78rem !important;
        font-weight: 650 !important;
        color: #2c2c2a;
        line-height: 1.3 !important;
        word-break: break-word;
    }

    .epp-scope {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.22rem;
        align-items: center;
    }

    .epp-scope-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.12rem 0.45rem;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 800;
        line-height: 1.2;
        letter-spacing: 0.01em;
        text-transform: uppercase;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .epp-scope-chip--program-on {
        background: #dcfce7;
        color: #166534;
        border-color: #86efac;
    }

    .epp-scope-chip--program-off {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .epp-scope-chip--calc-on {
        background: #dbeafe;
        color: #1d4ed8;
        border-color: #93c5fd;
    }

    .epp-scope-chip--calc-off {
        background: #ffedd5;
        color: #9a3412;
        border-color: #fed7aa;
    }

    .epp-scope-chip--rez-ok {
        background: #d1fae5;
        color: #065f46;
        border-color: #6ee7b7;
    }

    .epp-scope-chip--rez-pending {
        background: #fef3c7;
        color: #92400e;
        border-color: #fcd34d;
    }

    .fi-ta-row.epp-table-row--out-of-scope > td {
        background: #fff7ed !important;
    }

    .epp-ops {
        display: flex;
        flex-direction: column;
        gap: 0.08rem;
        margin-top: 0.1rem;
        font-size: 0.72rem;
        line-height: 1.35;
        color: #475569;
    }

    .epp-ops__row {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.15rem 0.4rem;
    }

    .epp-ops__label {
        font-size: 0.62rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #94a3b8;
        min-width: 4.6rem;
    }

    .epp-ops__row--total {
        color: #0f172a;
        font-weight: 600;
    }

    /* zielony = pełna wpłata */
    .epp-ops__row--ok {
        color: #166534;
        font-weight: 600;
    }

    .epp-ops__row--rez {
        color: #0f766e;
        font-weight: 700;
    }

    /* pomarańczowy = plan ≠ szablon / częściowa wpłata */
    .epp-ops__row--warn {
        color: #c2410c;
        font-weight: 600;
    }

    /* czerwony = zaległość */
    .epp-ops__row--due {
        color: #b91c1c;
        font-weight: 600;
    }

    .epp-ops__row--muted {
        color: #94a3b8;
    }

    .epp-meta {
        font-size: 0.72rem;
        color: #64748b;
        line-height: 1.4;
    }

    .epp-meta-sep {
        margin-inline: 0.35rem;
        color: #cbd5e1;
    }

    .epp-reservation-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.05rem 0.45rem;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        line-height: 1.35;
        white-space: nowrap;
    }

    .epp-reservation-badge--pending { background: #ffedd5; color: #9a3412; }
    .epp-reservation-badge--confirmed,
    .epp-reservation-badge--completed { background: #dcfce7; color: #166534; }
    .epp-reservation-badge--partially_confirmed { background: #e0f2fe; color: #0369a1; }
    .epp-reservation-badge--cancelled { background: #fee2e2; color: #991b1b; }
    .epp-reservation-badge--not_required { background: #f3f4f6; color: #374151; }

    .epp-reservation-badge--deposit-paid { background: #dcfce7; color: #166534; }
    .epp-reservation-badge--deposit-pending { background: #fef3c7; color: #92400e; }
    .epp-reservation-badge--deposit-overdue { background: #fee2e2; color: #991b1b; }
    .epp-reservation-badge--deposit-not_set { background: #f3f4f6; color: #6b7280; }

    .epp-flags {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        align-items: stretch;
    }

    .epp-finance-action {
        min-width: 0;
        padding-inline: 0.45rem !important;
        justify-content: center;
    }

    .epp-pricing-preview {
        margin: 0;
        padding: 0.55rem 0.75rem;
        border-radius: 0.5rem;
        background: color-mix(in srgb, var(--f-gray-50, #f9fafb) 88%, var(--f-primary-50, #eff6ff));
        border: 1px solid color-mix(in srgb, var(--f-gray-200, #e5e7eb) 80%, transparent);
        font-size: 0.78rem;
        line-height: 1.45;
        color: var(--f-gray-700, #374151);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .fi-modal .fi-fo-section:has(.epp-pricing-preview) {
        margin-bottom: 0.35rem;
    }

    .fi-modal .fi-fo-section:has(.epp-pricing-preview) .fi-fo-section-header {
        padding-bottom: 0.25rem;
    }

    .epp-flag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.14rem 0.35rem;
        border-radius: 0.35rem;
        font-size: 0.68rem;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
    }

    .epp-flag--on {
        background: #dcfce7;
        color: #166534;
    }

    .epp-flag--off {
        background: #f1f5f9;
        color: #94a3b8;
    }

    .epp-prices-cell {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 9.5rem;
        text-align: right;
        font-size: 0.76rem;
        line-height: 1.35;
    }

    .epp-prices-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.45rem;
    }

    .epp-prices-label {
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .epp-prices-value {
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
    }

    .epp-money {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.02rem;
        line-height: 1.2;
    }

    .epp-money__main {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .epp-money__sub {
        white-space: nowrap;
        font-size: 0.62rem;
        font-weight: 500;
        color: #64748b;
        font-variant-numeric: tabular-nums;
    }

    .epp-prices-row--paid {
        margin-top: 0.08rem;
        padding: 0.18rem 0.35rem;
        border-radius: 0.35rem;
    }

    .epp-prices-paid {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
    }

    .epp-prices-paid-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 0.95rem;
        height: 0.95rem;
        border-radius: 999px;
        background: #16a34a;
        color: #fff;
        font-size: 0.62rem;
        font-weight: 800;
        line-height: 1;
    }

    .epp-prices-row--paid-full {
        background: #ecfdf5;
        box-shadow: inset 0 0 0 1px #bbf7d0;
    }

    .epp-prices-row--paid-full .epp-prices-paid {
        color: #15803d;
        font-weight: 800;
        font-size: 0.82rem;
    }

    .epp-prices-row--paid-partial {
        background: #fffbeb;
        box-shadow: inset 0 0 0 1px #fde68a;
    }

    .epp-prices-row--paid-partial .epp-prices-paid {
        color: #b45309;
        font-weight: 800;
    }

    .epp-prices-row--paid-none .epp-prices-paid {
        color: #64748b;
        font-weight: 600;
    }

    .epp-prices-advance {
        margin-top: 0.12rem;
        font-size: 0.68rem;
        line-height: 1.3;
        color: #b45309;
        font-weight: 700;
        max-width: 14rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .epp-prices-advance--empty {
        color: #cbd5e1;
        font-weight: 500;
    }

    .epp-prices-pilot-due {
        margin-top: 0.1rem;
        font-size: 0.65rem;
        line-height: 1.25;
        color: #1d4ed8;
        font-weight: 600;
        max-width: 14rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .epp-prices-row--remaining .epp-prices-remaining-inline {
        color: #be123c;
        font-weight: 700;
    }

    .epp-prices-row--plan-differs .epp-prices-value {
        color: #b45309;
    }

    .epp-prices-status {
        margin-top: 0.2rem;
        display: flex;
        justify-content: flex-end;
    }

    .epp-prices-doc {
        margin-top: 0.15rem;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.62rem;
        line-height: 1.25;
        font-weight: 600;
    }

    .epp-prices-doc--ok {
        color: #047857;
    }

    .epp-prices-doc--missing {
        color: #b45309;
    }

    .epp-prices-doc--chip {
        display: inline-flex;
        max-width: 100%;
        margin-top: 0.2rem;
        margin-left: auto;
        padding: 0.18rem 0.45rem;
        border-radius: 0.35rem;
        background: #ecfdf5;
        color: #065f46;
        box-shadow: inset 0 0 0 1px #a7f3d0;
        text-decoration: none;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .epp-prices-doc--chip:hover {
        background: #d1fae5;
    }

    .epp-prices-doc--chip-warn {
        display: inline-flex;
        max-width: 100%;
        margin-top: 0.2rem;
        margin-left: auto;
        padding: 0.18rem 0.45rem;
        border-radius: 0.35rem;
        background: #fffbeb;
        color: #92400e;
        box-shadow: inset 0 0 0 1px #fde68a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    @media (max-width: 1024px) {
        .epp-name-cell--child {
            padding-left: 0.65rem;
            margin-left: 0.15rem;
        }

        .epp-title {
            font-size: 0.78rem !important;
        }
    }

    .event-program-day-tree .epp-day-tabs,
    .admin-program-day-tabs.epp-day-tabs {
        display: flex;
        flex-wrap: nowrap;
        gap: 0.375rem;
        margin-bottom: 0.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e5e3da;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .event-program-day-tree .epp-day-tab,
    .admin-program-day-tabs .epp-day-tab {
        display: inline-flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.35rem;
        flex-shrink: 0;
        min-width: 0;
        padding: 0.375rem 0.75rem;
        border: none;
        border-radius: 9999px;
        background: #f1efe8;
        color: #5f5e5a;
        font-size: 0.75rem;
        line-height: 1.2;
        transition: background 0.15s, color 0.15s;
    }

    .event-program-day-tree .epp-day-tab:hover,
    .admin-program-day-tabs .epp-day-tab:hover {
        background: #e5e3da;
        color: #2c2c2a;
    }

    .event-program-day-tree .epp-day-tab--active,
    .admin-program-day-tabs .epp-day-tab--active {
        background: #2c2c2a;
        color: #ffffff;
        box-shadow: none;
        border-color: transparent;
    }

    .event-program-day-tree .epp-day-tab__label,
    .admin-program-day-tabs .epp-day-tab__label {
        font-weight: 600;
        color: inherit;
    }

    .event-program-day-tree .epp-day-tab__date,
    .admin-program-day-tabs .epp-day-tab__date {
        font-size: 0.72rem;
        color: inherit;
        opacity: 0.85;
    }

    .event-program-day-tree .epp-day-tab__count,
    .admin-program-day-tabs .epp-day-tab__count {
        align-self: auto;
        font-size: 0.6875rem;
        font-weight: 700;
        padding: 0.05rem 0.4rem;
        border-radius: 999px;
        background: rgba(44, 44, 42, 0.08);
        color: inherit;
    }

    .event-program-day-tree .epp-day-tab--active .epp-day-tab__count,
    .admin-program-day-tabs .epp-day-tab--active .epp-day-tab__count {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }

    .admin-program-day-nav__hint {
        margin-top: 0.35rem;
    }

    .event-program-day-tree .epp-bulk-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        margin-bottom: 0.65rem;
        padding: 0.45rem 0.65rem;
        border-radius: 0.5rem;
        background: #fffbeb;
        border: 1px solid #fcd34d;
    }

    .event-program-day-tree .epp-bulk-bar__count {
        font-size: 0.78rem;
        font-weight: 700;
        color: #92400e;
    }

    .event-program-day-tree .epp-bulk-bar__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem;
    }

    .event-program-day-tree .epp-bulk-btn {
        padding: 0.2rem 0.45rem;
        border-radius: 0.35rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        font-size: 0.68rem;
        font-weight: 600;
        color: #334155;
    }

    .event-program-day-tree .epp-bulk-btn:hover {
        background: #f8fafc;
    }

    .event-program-day-tree .epp-bulk-btn--muted {
        color: #64748b;
    }

    .event-program-day-tree .epp-day-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        margin-bottom: 0.65rem;
    }

    .event-program-day-tree .epp-select-all {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.78rem;
        color: #475569;
    }

    .event-program-day-tree .epp-day-toolbar__hint {
        font-size: 0.68rem;
        color: #94a3b8;
    }

    .event-program-day-tree .epp-btn {
        margin-left: auto;
        padding: 0.35rem 0.65rem;
        border-radius: 0.4rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .event-program-day-tree .epp-btn--primary {
        background: #2563eb;
        color: #fff;
    }

    .event-program-day-tree .epp-day-panel {
        border: 1px solid #e2e8f0;
        border-radius: 0.65rem;
        background: #fff;
        overflow: hidden;
    }

    .event-program-day-tree .epp-day-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.5rem 0.75rem;
        background: linear-gradient(90deg, #f1f5f9 0%, #fff 100%);
        border-bottom: 1px solid #e2e8f0;
    }

    .event-program-day-tree .epp-day-panel__title {
        margin: 0;
        font-size: 0.82rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #1e3a8a;
    }

    .event-program-day-tree .epp-day-panel__date {
        margin: 0.1rem 0 0;
        font-size: 0.72rem;
        color: #64748b;
    }

    .event-program-day-tree .epp-day-panel__meta {
        font-size: 0.72rem;
        color: #64748b;
        white-space: nowrap;
    }

    .event-program-day-tree .epp-day-empty {
        padding: 1.5rem;
        text-align: center;
        font-size: 0.85rem;
        color: #64748b;
    }

    .event-program-day-tree .epp-table-head {
        padding: 0 0.5rem;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .event-program-day-tree .epp-tr {
        display: table;
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .event-program-day-tree .epp-td {
        display: table-cell;
        vertical-align: middle;
        padding: 0.28rem 0.35rem;
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .event-program-day-tree .epp-tr--head .epp-td {
        padding: 0.35rem;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
    }

    .event-program-day-tree .epp-td--check { width: 2rem; text-align: center; }
    .event-program-day-tree .epp-td--drag { width: 1.6rem; text-align: center; }
    .event-program-day-tree .epp-td--order { width: 2.2rem; text-align: center; }
    .event-program-day-tree .epp-td--time { width: 6.5rem; white-space: nowrap; }
    .event-program-day-tree .epp-td--flags { width: 8.2rem; text-align: center; }
    .event-program-day-tree .epp-td--actions { width: 2.4rem; text-align: center; }

    .event-program-day-tree .epp-blocks {
        list-style: none;
        margin: 0;
        padding: 0.25rem 0.5rem 0.5rem;
    }

    .event-program-day-tree .epp-block {
        list-style: none;
        margin-bottom: 0.3rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.45rem;
        background: #fff;
        overflow: hidden;
    }

    .event-program-day-tree .epp-block--set {
        border-color: #dbeafe;
    }

    .event-program-day-tree .epp-block--selected {
        box-shadow: inset 0 0 0 1px #93c5fd;
        background: #f8fbff;
    }

    .event-program-day-tree .epp-set-children {
        list-style: none;
        margin: 0;
        padding: 0;
        border-top: 1px solid #e2e8f0;
        background: #fafbfc;
    }

    .event-program-day-tree .epp-set-child {
        list-style: none;
        border-top: 1px solid #f1f5f9;
    }

    .event-program-day-tree .epp-set-child:first-child {
        border-top: none;
    }

    .event-program-day-tree .epp-tr--child .epp-td--name {
        padding-left: 0.85rem;
    }

    .event-program-day-tree .epp-tr--child .epp-name {
        font-size: 0.78rem;
        color: #475569;
    }

    .event-program-day-tree .epp-tr--inactive .epp-name {
        color: #94a3b8;
        text-decoration: line-through;
    }

    .event-program-day-tree .epp-td--check input {
        width: 0.95rem;
        height: 0.95rem;
        margin: 0;
        vertical-align: middle;
    }

    .event-program-day-tree .epp-tree-drag {
        cursor: grab;
        user-select: none;
        color: #94a3b8;
        font-size: 0.72rem;
        line-height: 1;
    }

    .event-program-day-tree .epp-order {
        font-size: 0.68rem;
        font-weight: 700;
        color: #64748b;
        font-variant-numeric: tabular-nums;
    }

    .event-program-day-tree .epp-time {
        font-size: 0.72rem;
        font-weight: 600;
        color: #0f766e;
    }

    .event-program-day-tree .epp-time--empty,
    .event-program-day-tree .epp-time--muted {
        color: #cbd5e1;
        font-weight: 500;
    }

    .event-program-day-tree .epp-td--name {
        overflow: hidden;
    }

    .event-program-day-tree .epp-name {
        display: inline;
        color: #0f172a;
        font-weight: 600;
    }

    .event-program-day-tree .epp-set-chip {
        display: inline-block;
        margin-right: 0.35rem;
        padding: 0.05rem 0.35rem;
        border-radius: 0.2rem;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        vertical-align: middle;
    }

    .event-program-day-tree .epp-flags {
        display: inline-flex;
        gap: 0.12rem;
        justify-content: center;
        white-space: nowrap;
    }

    .event-program-day-tree .epp-flag {
        min-width: 2.35rem;
        height: 1.35rem;
        padding: 0 0.28rem;
        border-radius: 0.28rem;
        font-size: 0.62rem;
        font-weight: 800;
        line-height: 1.35rem;
        text-align: center;
        cursor: pointer;
        flex-shrink: 0;
        letter-spacing: 0.01em;
        text-transform: uppercase;
    }

    .event-program-day-tree .epp-flag--on {
        background: #2563eb;
        border: 1px solid #1d4ed8;
        color: #fff;
    }

    .event-program-day-tree .epp-flag--program.epp-flag--on {
        background: #16a34a;
        border-color: #15803d;
    }

    .event-program-day-tree .epp-flag--calc.epp-flag--on {
        background: #2563eb;
        border-color: #1d4ed8;
    }

    .event-program-day-tree .epp-flag--off {
        background: #fff7ed;
        border: 1px solid #fdba74;
        color: #9a3412;
    }

    .event-program-day-tree .epp-menu {
        position: relative;
        display: inline-block;
    }

    .event-program-day-tree .epp-menu__trigger {
        list-style: none;
        cursor: pointer;
        width: 1.6rem;
        height: 1.6rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.35rem;
        background: #fff;
        color: #475569;
        font-size: 1rem;
        line-height: 1.35rem;
        text-align: center;
        user-select: none;
    }

    .event-program-day-tree .epp-menu__trigger::-webkit-details-marker {
        display: none;
    }

    .event-program-day-tree .epp-menu[open] .epp-menu__trigger {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .event-program-day-tree .epp-menu__panel {
        position: absolute;
        right: 0;
        top: calc(100% + 2px);
        z-index: 40;
        min-width: 9.5rem;
        padding: 0.25rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.45rem;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
    }

    .event-program-day-tree .epp-menu__panel button,
    .event-program-day-tree .epp-menu__panel a {
        display: block;
        width: 100%;
        padding: 0.35rem 0.5rem;
        border: none;
        border-radius: 0.3rem;
        background: transparent;
        text-align: left;
        font-size: 0.78rem;
        color: #334155;
        text-decoration: none;
        cursor: pointer;
    }

    .event-program-day-tree .epp-menu__panel button:hover,
    .event-program-day-tree .epp-menu__panel a:hover {
        background: #f1f5f9;
    }

    .event-program-day-tree .epp-menu__danger {
        color: #dc2626 !important;
    }

    .event-program-day-tree .sortable-ghost {
        opacity: 0.5;
    }

    .event-program-day-tree .sortable-chosen {
        box-shadow: 0 4px 16px rgba(30, 58, 138, 0.15);
    }

    @media (max-width: 900px) {
        .event-program-day-tree .epp-td--time {
            width: 5rem;
            font-size: 0.65rem;
        }

        .event-program-day-tree .epp-table-head {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .admin-program-view-tabs .fi-tabs-item-label {
            font-size: 0.78rem;
        }

        .fi-ta-actions {
            flex-direction: column;
            align-items: stretch;
        }
    }

    .kanban-board {
        gap: 1rem;
        padding-bottom: 0.25rem;
    }

    .kanban-board .kanban-column {
        min-width: var(--admin-column-min-width);
        max-width: min(24rem, 92vw);
    }

    .admin-table-stack {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        padding: 0.1rem 0;
    }

    .admin-table-stack-compact {
        gap: 0.08rem;
    }

    .admin-table-title {
        font-size: calc(var(--admin-body-size) + 1px);
        font-weight: 800;
        line-height: 1.3;
        color: #111827;
    }

    .admin-table-meta,
    .admin-table-value,
    .admin-table-value-strong {
        font-size: var(--admin-body-size);
        line-height: 1.3;
        color: #374151;
    }

    .admin-table-value-strong {
        font-weight: 700;
    }

    .admin-table-muted-label {
        font-size: calc(var(--admin-helper-size) + 0.5px);
        line-height: 1.3;
        color: #9ca3af;
    }

    .admin-table-pill {
        display: inline-block;
        width: fit-content;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        font-size: calc(var(--admin-helper-size) + 1px);
        font-weight: 700;
        line-height: 1.3;
    }

    /* Kompaktowe wiersze tabel Filament — tylko komórki tabel, bez obramowań */
    .fi-ta-table td,
    .fi-ta-table th {
        padding-block: 0.45rem !important;
    }

    @media (max-width: 639.98px) {
        :root {
            --sidebar-width: min(15rem, 74vw);
        }

        .fi-page-content,
        .fi-simple-main-ctn {
            padding-inline: 0.65rem;
        }

        .fi-sidebar {
            width: min(15rem, 74vw) !important;
            max-width: min(15rem, 74vw) !important;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.16);
        }

        .fi-sidebar-close-overlay {
            background: rgba(15, 23, 42, 0.16) !important;
            backdrop-filter: blur(1px);
        }

        .fi-sidebar-header,
        .fi-sidebar-nav {
            padding-left: 0.85rem !important;
            padding-right: 0.85rem !important;
        }

        .fi-ta-table {
            min-width: 34rem;
        }

        .event-program-planner-scroll .fc {
            min-width: 38rem;
        }

        .kanban-board .kanban-column {
            min-width: min(17rem, 88vw);
        }
    }

    /* -----------------------------------------------------------------------
       Modal z-index: Filament uses z-40 (= 40). Our sidebar uses z-100.
       Raise modal backdrop and its sibling container above everything.
       Confirmed selectors from vendor/filament/support/.../modal/index.blade.php:
         .fi-modal-close-overlay  = semitransparent backdrop (z-40)
         .fi-modal-close-overlay + div = fixed container holding the modal window (z-40)
    ----------------------------------------------------------------------- */
    .fi-modal-close-overlay {
        z-index: 1500 !important;
    }

    .fi-modal-close-overlay + div {
        z-index: 1501 !important;
    }

    /* Toast powiadomień Filament (zapisano / usunięto) — nad topbarem i modalami */
    .fi-no {
        z-index: 2600 !important;
        pointer-events: none;
    }

    .fi-no .fi-no-notification {
        pointer-events: auto;
    }

    .pilot-funds-pill {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 0.1rem;
        min-width: 5.5rem;
        padding: 0.28rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: 0.01em;
        text-align: center;
    }

    .pilot-funds-pill--paid {
        background: #d1fae5;
        color: #047857;
        box-shadow: inset 0 0 0 1px #6ee7b7;
    }

    .pilot-funds-pill--unpaid {
        background: #fee2e2;
        color: #b91c1c;
        box-shadow: inset 0 0 0 1px #fca5a5;
    }

    .pilot-funds-pill--na {
        background: #f3f4f6;
        color: #9ca3af;
    }

    .pilot-funds-pill-meta {
        display: block;
        font-size: 0.62rem;
        font-weight: 600;
        color: inherit;
        opacity: 0.85;
    }

    .event-indicators {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.3rem;
        min-width: 6.5rem;
    }

    .event-indicator {
        display: block;
        padding: 0.22rem 0.45rem;
        border-radius: 0.45rem;
        font-size: 0.68rem;
        font-weight: 700;
        line-height: 1.25;
        text-align: center;
        white-space: nowrap;
    }

    .event-indicator--ok {
        background: #d1fae5;
        color: #047857;
        box-shadow: inset 0 0 0 1px #6ee7b7;
    }

    .event-indicator--warn {
        background: #ffedd5;
        color: #c2410c;
        box-shadow: inset 0 0 0 1px #fdba74;
    }

    .event-indicator--danger {
        background: #fee2e2;
        color: #b91c1c;
        box-shadow: inset 0 0 0 1px #fca5a5;
    }

    .event-indicator--muted {
        background: #f3f4f6;
        color: #9ca3af;
        box-shadow: inset 0 0 0 1px #e5e7eb;
    }

    .event-readiness-cell {
        cursor: pointer;
        vertical-align: middle;
        min-width: 7.5rem;
    }

    .event-readiness-summary {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 0.35rem;
        max-width: 9rem;
    }

    .event-readiness-summary__score {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.75rem;
        padding: 0.15rem 0.5rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        line-height: 1.2;
        border: 1px solid transparent;
    }

    .event-readiness-summary--ok .event-readiness-summary__score {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }

    .event-readiness-summary--warn .event-readiness-summary__score {
        background: #fffbeb;
        color: #b45309;
        border-color: #fde68a;
    }

    .event-readiness-summary--danger .event-readiness-summary__score {
        background: #fef2f2;
        color: #b91c1c;
        border-color: #fecaca;
    }

    .event-readiness-summary--muted .event-readiness-summary__score {
        background: #f3f4f6;
        color: #6b7280;
        border-color: #e5e7eb;
    }

    .event-readiness-summary__blockers {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.2rem;
    }

    .event-readiness-summary__blockers .event-indicator {
        font-size: 0.65rem;
        padding: 0.05rem 0.35rem;
    }

    .event-readiness-overview__group-label {
        margin: 0 0 0.5rem;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .event-readiness-cell:hover .event-indicators--clickable .event-indicator {
        filter: brightness(0.97);
    }

    .event-indicators--clickable {
        pointer-events: none;
    }

    /* Gotowość operacyjna — karty w jednym rzędzie (telefon: kolumna).
       !important: przebija utility Tailwind (grid-cols-1 / sm:grid-cols-2). */
    .event-readiness-overview {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(9.25rem, 1fr)) !important;
        gap: 0.55rem;
        width: 100%;
        align-items: stretch;
    }

    .event-readiness-overview-card {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.45rem;
        min-width: 0;
        height: 100%;
        padding: 0.7rem 0.75rem;
        border-radius: 0.75rem;
        border: 1px solid var(--sor-border, #e5e7eb);
        background: var(--sor-surface-elevated, #fff);
        box-shadow: var(--sor-shadow-sm, 0 1px 2px rgb(0 0 0 / 0.04));
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    @media (max-width: 639.98px) {
        .event-readiness-overview {
            grid-template-columns: 1fr !important;
        }

        .event-readiness-overview-card {
            flex-direction: row;
            gap: 0.85rem;
            padding: 1rem;
        }

        .event-readiness-overview-card__icon-wrap {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.65rem;
        }

        .event-readiness-overview-card__icon {
            width: 1.25rem;
            height: 1.25rem;
        }

        .event-readiness-overview-card__label {
            font-size: 0.72rem;
        }

        .event-readiness-overview-card__status {
            margin: 0.2rem 0 0;
            font-size: 0.98rem;
        }

        .event-readiness-overview-card__hint {
            margin: 0.35rem 0 0;
            font-size: 0.78rem;
        }
    }

    .event-readiness-overview-card:hover {
        box-shadow: var(--sor-shadow-md, 0 4px 12px rgb(0 0 0 / 0.06));
    }

    a.event-readiness-overview-card--link {
        text-decoration: none;
        color: inherit;
        cursor: pointer;
    }

    a.event-readiness-overview-card--link:hover {
        border-color: var(--sor-primary, #2563eb);
    }

    .event-readiness-overview-card__icon-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        border-radius: 0.5rem;
    }

    .event-readiness-overview-card__icon {
        width: 1.1rem;
        height: 1.1rem;
    }

    .event-readiness-overview-card__label {
        margin: 0;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--sor-text-muted, #6b7280);
    }

    .event-readiness-overview-card__status {
        margin: 0.1rem 0 0;
        font-size: 0.82rem;
        font-weight: 700;
        line-height: 1.25;
        color: var(--sor-text, #111827);
    }

    .event-readiness-overview-card__hint {
        margin: 0.2rem 0 0;
        font-size: 0.7rem;
        line-height: 1.35;
        color: var(--sor-text-muted, #6b7280);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .event-readiness-overview-card--ok {
        border-color: #a7f3d0;
        background: linear-gradient(180deg, #f0fdf4 0%, #fff 100%);
    }

    .event-readiness-overview-card--ok .event-readiness-overview-card__icon-wrap {
        background: #d1fae5;
        color: #047857;
    }

    .event-readiness-overview-card--ok .event-readiness-overview-card__status {
        color: #047857;
    }

    .event-readiness-overview-card--warn {
        border-color: #fed7aa;
        background: linear-gradient(180deg, #fff7ed 0%, #fff 100%);
    }

    .event-readiness-overview-card--warn .event-readiness-overview-card__icon-wrap {
        background: #ffedd5;
        color: #c2410c;
    }

    .event-readiness-overview-card--warn .event-readiness-overview-card__status {
        color: #c2410c;
    }

    .event-readiness-overview-card--danger {
        border-color: #fecaca;
        background: linear-gradient(180deg, #fef2f2 0%, #fff 100%);
    }

    .event-readiness-overview-card--danger .event-readiness-overview-card__icon-wrap {
        background: #fee2e2;
        color: #b91c1c;
    }

    .event-readiness-overview-card--danger .event-readiness-overview-card__status {
        color: #b91c1c;
    }

    .event-readiness-overview-card--muted {
        border-color: var(--sor-border, #e5e7eb);
        background: var(--sor-surface, #f8fafc);
    }

    .event-readiness-overview-card--muted .event-readiness-overview-card__icon-wrap {
        background: #f3f4f6;
        color: #9ca3af;
    }

    .event-readiness-overview-card--muted .event-readiness-overview-card__status {
        color: #6b7280;
    }

    /* Status imprezy — kolor tła komórki */
    .event-list-status-cell .fi-select-input,
    .event-list-status-cell select {
        border-radius: 0.5rem;
        font-weight: 700;
        font-size: 0.78rem;
    }

    .event-list-status-cell--inquiry .fi-select-input,
    .event-list-status-cell--inquiry select {
        background: #f3f4f6 !important;
        color: #4b5563 !important;
        border-color: #d1d5db !important;
    }

    .event-list-status-cell--offer .fi-select-input,
    .event-list-status-cell--offer select {
        background: #dbeafe !important;
        color: #1d4ed8 !important;
        border-color: #93c5fd !important;
    }

    .event-list-status-cell--provisional_reservation .fi-select-input,
    .event-list-status-cell--provisional_reservation select {
        background: #fef3c7 !important;
        color: #b45309 !important;
        border-color: #fcd34d !important;
    }

    .event-list-status-cell--confirmed .fi-select-input,
    .event-list-status-cell--confirmed select,
    .event-list-status-cell--odprawa_ok .fi-select-input,
    .event-list-status-cell--odprawa_ok select {
        background: #d1fae5 !important;
        color: #047857 !important;
        border-color: #6ee7b7 !important;
    }

    .event-list-status-cell--to_settle .fi-select-input,
    .event-list-status-cell--to_settle select {
        background: #ede9fe !important;
        color: #6d28d9 !important;
        border-color: #c4b5fd !important;
    }

    .event-list-status-cell--settled .fi-select-input,
    .event-list-status-cell--settled select {
        background: #e0e7ff !important;
        color: #3730a3 !important;
        border-color: #a5b4fc !important;
    }

    .event-list-status-cell--pending_cancellation .fi-select-input,
    .event-list-status-cell--pending_cancellation select,
    .event-list-status-cell--cancelled .fi-select-input,
    .event-list-status-cell--cancelled select {
        background: #fee2e2 !important;
        color: #b91c1c !important;
        border-color: #fca5a5 !important;
    }

    .event-status-flags {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
    }

    .event-flag-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 3.5rem;
        padding: 0.2rem 0.45rem;
        border-radius: 9999px;
        font-size: 0.68rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .event-flag-pill--ok {
        background: #d1fae5;
        color: #047857;
        box-shadow: inset 0 0 0 1px #6ee7b7;
    }

    .event-flag-pill--warn {
        background: #ffedd5;
        color: #c2410c;
        box-shadow: inset 0 0 0 1px #fdba74;
    }

    .finance-module-nav-item,
    .workflow-module-nav-item {
        text-decoration: none;
        min-height: 0;
    }

    .workflow-module-nav {
        position: sticky;
        top: 0;
        z-index: 20;
        max-width: 100%;
        background: linear-gradient(to bottom, var(--sor-surface-elevated) 85%, transparent);
        padding-top: 0.15rem;
        margin-bottom: 0.65rem;
    }

    /* Chrome imprezy: breadcrumbs / zakładki / akcje — w rytmie paska informacyjnego */
    .fi-page:has(.workflow-info-bar) .fi-breadcrumbs,
    .fi-page:has(.workflow-info-bar) .fi-page-header .fi-breadcrumbs {
        font-size: 0.75rem;
        font-weight: 600;
        gap: 0.35rem;
        margin-bottom: 0.35rem;
    }

    .fi-page:has(.workflow-info-bar) .fi-breadcrumbs-item,
    .fi-page:has(.workflow-info-bar) .fi-breadcrumbs-item-label,
    .fi-page:has(.workflow-info-bar) .fi-breadcrumbs a {
        font-size: 0.75rem;
        font-weight: 600;
        color: rgb(107 114 128);
    }

    .fi-page:has(.workflow-info-bar) .fi-breadcrumbs-item:last-child .fi-breadcrumbs-item-label,
    .fi-page:has(.workflow-info-bar) .fi-breadcrumbs li:last-child {
        color: rgb(17 24 39);
        font-weight: 700;
    }

    .dark .fi-page:has(.workflow-info-bar) .fi-breadcrumbs-item:last-child .fi-breadcrumbs-item-label,
    .dark .fi-page:has(.workflow-info-bar) .fi-breadcrumbs li:last-child {
        color: rgb(243 244 246);
    }

    .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs,
    .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation {
        margin-bottom: 0.5rem;
    }

    .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs .fi-tabs,
    .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation .fi-tabs {
        gap: 0.35rem;
        border-bottom: 1px solid rgb(243 244 246);
        padding-bottom: 0.35rem;
    }

    .dark .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs .fi-tabs,
    .dark .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation .fi-tabs {
        border-bottom-color: rgb(31 41 55);
    }

    .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs .fi-tabs-item,
    .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation .fi-tabs-item {
        border-radius: 0.375rem;
        padding: 0.25rem 0.55rem;
        font-size: 0.75rem;
        font-weight: 600;
        min-height: 0;
        background: rgb(243 244 246);
        color: rgb(55 65 81);
        box-shadow: none;
    }

    .dark .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs .fi-tabs-item,
    .dark .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation .fi-tabs-item {
        background: rgb(31 41 55);
        color: rgb(229 231 235);
    }

    .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs .fi-tabs-item-label,
    .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation .fi-tabs-item-label {
        font-size: 0.75rem;
        font-weight: 600;
    }

    .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs .fi-tabs-item-icon,
    .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation .fi-tabs-item-icon {
        width: 0.875rem;
        height: 0.875rem;
    }

    .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs .fi-tabs-item[aria-selected="true"],
    .fi-page:has(.workflow-info-bar) .fi-page-sub-navigation-tabs .fi-tabs-item.fi-active,
    .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation .fi-tabs-item[aria-selected="true"],
    .fi-page:has(.workflow-info-bar) .fi-resource-sub-navigation .fi-tabs-item.fi-active {
        background: rgb(217 119 6);
        color: #fff;
    }

    .fi-page:has(.workflow-info-bar) .fi-header-actions .fi-btn,
    .fi-page:has(.workflow-info-bar) .fi-page-header-actions .fi-btn,
    .fi-page:has(.workflow-info-bar) .fi-ac-btn-action {
        min-height: 0 !important;
        padding: 0.25rem 0.55rem !important;
        font-size: 0.6875rem !important;
        font-weight: 600 !important;
        border-radius: 0.375rem !important;
        gap: 0.35rem;
        line-height: 1.25;
    }

    .fi-page:has(.workflow-info-bar) .fi-header-actions .fi-btn svg,
    .fi-page:has(.workflow-info-bar) .fi-page-header-actions .fi-btn svg,
    .fi-page:has(.workflow-info-bar) .fi-ac-btn-action svg {
        width: 0.875rem !important;
        height: 0.875rem !important;
    }

    .fi-page:has(.workflow-info-bar) .fi-header,
    .fi-page:has(.workflow-info-bar) .fi-page-header {
        gap: 0.5rem;
        margin-bottom: 0.35rem;
    }

    .fi-page:has(.workflow-info-bar) .fi-header-heading,
    .fi-page:has(.workflow-info-bar) .fi-page-header-heading {
        font-size: 0.95rem;
        line-height: 1.25;
    }

    .fi-header,
    .fi-page-header-main-ctn {
        min-width: 0;
        max-width: 100%;
    }

    .workflow-record-context {
        position: relative;
        z-index: 10;
    }

    /* Kompaktowy pasek informacji imprezy — kolumny wymuszone (Tailwind purge nie zawsze ma grid-cols-4/5). */
    .workflow-info-bar {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .workflow-info-bar__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .dark .workflow-info-bar__header {
        border-bottom-color: rgb(31 41 55);
    }

    .workflow-info-bar__title {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
        color: rgb(17 24 39);
    }

    .dark .workflow-info-bar__title {
        color: rgb(255 255 255);
    }

    .workflow-info-bar__schedule {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.35rem 1rem;
        margin-top: 0.35rem;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.35;
        color: rgb(31 41 55);
    }

    .dark .workflow-info-bar__schedule {
        color: rgb(229 231 235);
    }

    .workflow-info-bar__schedule-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: rgb(107 114 128);
        margin-right: 0.2rem;
    }

    .dark .workflow-info-bar__schedule-label {
        color: rgb(156 163 175);
    }

    .workflow-info-bar__chip {
        display: inline-flex;
        align-items: baseline;
        gap: 0.25rem;
        border-radius: 0.375rem;
        background: rgb(243 244 246);
        padding: 0.15rem 0.45rem;
        font-size: 0.8125rem;
        font-weight: 700;
        line-height: 1.25;
        color: rgb(17 24 39);
    }

    .dark .workflow-info-bar__chip {
        background: rgb(31 41 55);
        color: rgb(243 244 246);
    }

    .workflow-info-bar__meta {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.5rem 0.75rem;
        padding: 0.5rem 0.65rem;
        border-radius: 0.5rem;
        background: rgb(249 250 251);
    }

    .dark .workflow-info-bar__meta {
        background: rgba(31, 41, 55, 0.5);
    }

    .workflow-info-bar__section-title {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin-bottom: 0.35rem;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(55 65 81);
    }

    .dark .workflow-info-bar__section-title {
        color: rgb(209 213 219);
    }

    .workflow-info-bar__vendors {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.35rem;
    }

    .workflow-info-bar__pair {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.35rem;
        min-width: 0;
    }

    .workflow-info-bar__clients {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
    }

    .workflow-info-bar__tile {
        min-width: 0;
        padding: 0.4rem 0.5rem;
        border-radius: 0.5rem;
        background: rgb(249 250 251);
    }

    .dark .workflow-info-bar__tile {
        background: rgba(31, 41, 55, 0.35);
    }

    .workflow-info-bar__tile-label {
        font-size: 10px;
        line-height: 1.2;
        color: rgb(107 114 128);
        margin-bottom: 0.1rem;
    }

    .workflow-info-bar__tile-value {
        font-size: 11px;
        font-weight: 700;
        line-height: 1.25;
        color: rgb(17 24 39);
        word-break: break-word;
    }

    .dark .workflow-info-bar__tile-value {
        color: rgb(243 244 246);
    }

    .workflow-info-bar__tile--ok {
        background: rgba(16, 185, 129, 0.08);
        box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.18);
    }

    .workflow-info-bar__tile--ok .workflow-info-bar__tile-label {
        color: rgb(4 120 87);
    }

    .workflow-info-bar__tile--ok .workflow-info-bar__tile-value {
        color: rgb(6 95 70);
    }

    .workflow-info-bar__tile--danger {
        background: rgba(244, 63, 94, 0.08);
        box-shadow: inset 0 0 0 1px rgba(244, 63, 94, 0.18);
    }

    .workflow-info-bar__tile--danger .workflow-info-bar__tile-label {
        color: rgb(190 18 60);
    }

    .workflow-info-bar__tile--danger .workflow-info-bar__tile-value {
        color: rgb(159 18 57);
    }

    .workflow-info-bar__tile--pilot {
        background: rgba(99, 102, 241, 0.08);
        box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.18);
    }

    .workflow-info-bar__tile--pilot .workflow-info-bar__tile-label {
        color: rgb(67 56 202);
    }

    .workflow-info-bar__tile--pilot .workflow-info-bar__tile-value {
        color: rgb(49 46 129);
    }

    .workflow-info-bar__tile--info {
        background: rgba(59, 130, 246, 0.08);
        box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.18);
    }

    .workflow-info-bar__tile--info .workflow-info-bar__tile-label {
        color: rgb(29 78 216);
    }

    .workflow-info-bar__tile--info .workflow-info-bar__tile-value {
        color: rgb(30 64 175);
    }

    .workflow-info-bar__tile--warn {
        background: rgba(245, 158, 11, 0.1);
        box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.2);
    }

    .workflow-info-bar__tile--warn .workflow-info-bar__tile-label {
        color: rgb(180 83 9);
    }

    .workflow-info-bar__tile--warn .workflow-info-bar__tile-value {
        color: rgb(146 64 14);
    }

    /* Jedna belka finansów imprezy (strona Finanse) */
    .event-finance-summary-bar__grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) minmax(0, 1.2fr);
        gap: 0.85rem 1rem;
        align-items: start;
    }

    .event-finance-summary-bar__section {
        min-width: 0;
    }

    .event-finance-summary-bar__section + .event-finance-summary-bar__section {
        padding-left: 1rem;
        border-left: 1px solid rgb(243 244 246);
    }

    .dark .event-finance-summary-bar__section + .event-finance-summary-bar__section {
        border-left-color: rgb(31 41 55);
    }

    .event-finance-summary-bar__tiles {
        display: grid;
        gap: 0.35rem;
    }

    .event-finance-summary-bar__tiles--offer {
        grid-template-columns: 1fr;
    }

    .event-finance-summary-bar__tiles--vendors {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .event-finance-summary-bar__tiles--clients {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    @media (max-width: 1279px) {
        .event-finance-summary-bar__grid {
            grid-template-columns: 1fr;
        }

        .event-finance-summary-bar__section + .event-finance-summary-bar__section {
            padding-left: 0;
            padding-top: 0.75rem;
            border-left: 0;
            border-top: 1px solid rgb(243 244 246);
        }

        .dark .event-finance-summary-bar__section + .event-finance-summary-bar__section {
            border-top-color: rgb(31 41 55);
        }
    }

    @media (max-width: 639px) {
        .event-finance-summary-bar__tiles--vendors,
        .event-finance-summary-bar__tiles--clients {
            grid-template-columns: 1fr;
        }
    }

    .workflow-info-bar .workflow-record-context-action {
        min-height: 0 !important;
    }

    @media (max-width: 1023px) {
        .workflow-info-bar__meta {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .workflow-info-bar__vendors {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .workflow-info-bar__clients {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 639px) {
        .workflow-info-bar__header {
            flex-direction: column;
        }

        .workflow-info-bar__meta,
        .workflow-info-bar__vendors,
        .workflow-info-bar__clients {
            grid-template-columns: 1fr;
        }
    }

    .workflow-record-context-link,
    .fi-btn,
    .fi-ac-btn-action,
    .fi-ta-actions .fi-btn {
        min-height: var(--admin-touch-min);
    }

    .admin-action-group .fi-btn {
        min-height: var(--admin-touch-min);
    }

    /* Event list stacked cells */
    .admin-event-termin {
        font-size: 0.75rem;
        color: #6b7280;
        margin-bottom: 2px;
    }

    .admin-event-times {
        font-size: 0.7rem;
        color: #9ca3af;
        line-height: 1.3;
        margin-bottom: 3px;
        text-transform: none;
    }

    .admin-event-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #111827;
        line-height: 1.3;
    }

    .admin-event-template {
        font-size: 0.72rem;
        color: #9ca3af;
        margin-top: 2px;
    }

    .admin-event-client-start {
        font-size: 0.75rem;
        color: #6b7280;
        margin-bottom: 2px;
    }

    .admin-event-client-name {
        font-weight: 500;
    }

    .admin-event-client-meta {
        font-size: 0.75rem;
        color: #6b7280;
    }

    .admin-finance-line {
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .admin-finance-muted {
        color: var(--sor-text-muted);
    }

    .admin-finance-warn {
        color: var(--sor-danger);
        font-weight: 600;
    }

    .admin-finance-ok {
        color: var(--sor-success);
        font-weight: 600;
    }

    /* Hotel plan: cards on mobile */
    @media (max-width: 767.98px) {
        .hotel-day-card {
            display: block;
        }

        .hotel-day-table-row {
            display: none;
        }

        .workflow-module-nav-item {
            min-width: 0;
        }
    }

    .admin-program-view-tabs {
        margin-bottom: 0;
    }

    .fi-ta-table .fi-ta-row:has(.task-list-subtask) > td {
        background: color-mix(in srgb, #f8fafc 85%, transparent);
    }

    .dark .fi-ta-table .fi-ta-row:has(.task-list-subtask) > td {
        background: color-mix(in srgb, rgb(255 255 255 / 0.04) 100%, transparent);
    }

    /* Lista zadań: table-layout:fixed + max-width:0 wymusza respektowanie szerokości (bez nachodzenia). */
    .fi-ta-table:has(.task-list-cell) {
        table-layout: fixed;
        width: 100%;
        min-width: 68rem;
    }

    .fi-ta-table:has(.task-list-cell) > thead > tr > th,
    .fi-ta-table:has(.task-list-cell) > tbody > tr > td {
        vertical-align: top;
        overflow: hidden;
        max-width: 0;
        box-sizing: border-box;
    }

    /* Checkbox / selection — nie ściskaj. */
    .fi-ta-table:has(.task-list-cell) > thead > tr > th.fi-ta-selection-cell,
    .fi-ta-table:has(.task-list-cell) > tbody > tr > td.fi-ta-selection-cell,
    .fi-ta-table:has(.task-list-cell) .fi-ta-selection-cell {
        width: 3rem;
        min-width: 3rem;
        max-width: 3rem;
    }

    .fi-ta-table th.fi-ta-col-task-summary,
    .fi-ta-table td.fi-ta-col-task-summary {
        width: 38%;
        min-width: 16rem;
        max-width: none;
    }

    .fi-ta-table th.fi-ta-col-task-context,
    .fi-ta-table td.fi-ta-col-task-context {
        width: 15%;
        min-width: 9rem;
        max-width: none;
        white-space: normal;
        overflow-wrap: break-word;
        word-break: break-word;
    }

    .fi-ta-table th.fi-ta-col-task-status,
    .fi-ta-table td.fi-ta-col-task-status {
        width: 10%;
        min-width: 7.5rem;
        max-width: none;
        overflow: visible;
    }

    .fi-ta-table th.fi-ta-col-task-due,
    .fi-ta-table td.fi-ta-col-task-due {
        width: 11%;
        min-width: 7.5rem;
        max-width: none;
        white-space: nowrap;
    }

    .fi-ta-table th.fi-ta-col-task-modified,
    .fi-ta-table td.fi-ta-col-task-modified {
        width: 12%;
        min-width: 8rem;
        max-width: none;
        white-space: nowrap;
    }

    .fi-ta-table:has(.task-list-cell) .fi-ta-actions-header-cell,
    .fi-ta-table:has(.task-list-cell) .fi-ta-actions-cell,
    .fi-ta-table:has(.task-list-cell) th:last-child,
    .fi-ta-table:has(.task-list-cell) td:last-child {
        width: 5.5rem;
        min-width: 5.5rem;
        max-width: 5.5rem;
        white-space: nowrap;
        overflow: visible;
    }

    .task-list-context-cell {
        display: block;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        white-space: normal;
        overflow-wrap: break-word;
        word-break: break-word;
    }

    .task-list-cell {
        display: block;
        width: 100%;
        min-width: 0;
        max-width: 100%;
    }

    /* ——— Split-view Zadania (mockup docs/zadania poprawione) ——— */
    .tasks-split-view {
        --task-bg: #F5F6F8;
        --task-surface: #FFFFFF;
        --task-surface-alt: #FAFBFC;
        --task-border: #E5E8EC;
        --task-text: #1D2433;
        --task-text-muted: #6B7280;
        --task-text-faint: #98A2B3;
        --task-accent: #1C4E80;
        --task-accent-soft: #E8F0FA;
        --task-status-todo: #B45309;
        --task-status-todo-bg: #FDF3E2;
        --task-status-progress: #2563EB;
        --task-status-progress-bg: #EAF1FE;
        --task-status-done: #15803D;
        --task-status-done-bg: #E9F7EF;
        --task-status-cancel: #B42318;
        --task-status-cancel-bg: #FDECEA;
        --task-status-neutral: #6B7280;
        --task-status-neutral-bg: #F3F4F6;
        --task-radius-sm: 6px;
        --task-radius-md: 10px;
    }

    .tasks-split-view {
        display: flex;
        gap: 0;
        min-height: min(70vh, 52rem);
        border: 1px solid var(--task-border);
        border-radius: var(--task-radius-md);
        background: var(--task-bg);
        overflow: hidden;
    }

    .tasks-split-view__shell {
        display: flex;
        flex: 1;
        min-height: min(70vh, 52rem);
        width: 100%;
        min-width: 0;
    }

    .tasks-split-view--embedded {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--task-border, #E5E8EC);
        border-radius: var(--task-radius-md, 10px);
        overflow: hidden;
        background: var(--task-bg, #F5F6F8);
        min-height: min(70vh, 52rem);
    }

    .tasks-split-view--embedded .tasks-split-view__shell {
        border-top: 1px solid var(--task-border, #E5E8EC);
    }

    @media (max-width: 1023px) {
        .tasks-split-view--has-selection .tasks-split-view__list {
            display: none;
        }

        .tasks-split-view:not(.tasks-split-view--has-selection) .tasks-split-view__detail {
            display: none;
        }
    }

    .tasks-split-view__list {
        width: 100%;
        max-width: 100%;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        min-height: 0;
        background: var(--task-surface);
        border-right: 1px solid var(--task-border);
    }

    .tasks-split-view__list-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid var(--task-border);
        background: var(--task-surface);
        flex-shrink: 0;
    }

    .tasks-split-view__sort {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
        width: 100%;
    }

    .tasks-split-view__sort-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--task-text-faint);
        text-transform: uppercase;
        letter-spacing: 0.02em;
        flex-shrink: 0;
    }

    .tasks-split-view__sort-select {
        min-width: 0;
        flex: 1;
        border: 1px solid var(--task-border);
        border-radius: var(--task-radius-sm);
        color: #1D2433;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.4rem 1.85rem 0.4rem 0.55rem;
    }

    .tasks-split-view__sort-select:focus {
        outline: 2px solid var(--task-accent);
        outline-offset: 1px;
    }

    /*
     * Selecty w split-view: jedna strzałka (SVG), bez natywnego chevrona
     * i bez tiling z Tailwind Forms / .fi-main select.
     */
    .tasks-split-view select.task-split-select,
    .tasks-split-view .tasks-split-view__sort-select,
    .tasks-split-view .task-split-select {
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        background-color: #FAFBFC !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%231D2433' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.55rem center !important;
        background-size: 0.85rem 0.85rem !important;
        padding-right: 1.85rem !important;
        color: #1D2433 !important;
        cursor: pointer;
        box-shadow: none !important;
    }

    .tasks-split-view select.task-split-select.task-status-pill--todo {
        background-color: #FDF3E2 !important;
        color: #B45309 !important;
        border: 1px solid #F0D9A8 !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%23B45309' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    }

    .tasks-split-view select.task-split-select.task-status-pill--progress {
        background-color: #EAF1FE !important;
        color: #1D4ED8 !important;
        border: 1px solid #BFDBFE !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%231D4ED8' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    }

    .tasks-split-view select.task-split-select.task-status-pill--done {
        background-color: #E9F7EF !important;
        color: #15803D !important;
        border: 1px solid #BBF7D0 !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%2315803D' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    }

    .tasks-split-view select.task-split-select.task-status-pill--cancel {
        background-color: #FDECEA !important;
        color: #B42318 !important;
        border: 1px solid #FECACA !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%23B42318' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    }

    .tasks-split-view select.task-split-select.task-status-pill--neutral {
        background-color: #F3F4F6 !important;
        color: #374151 !important;
        border: 1px solid #E5E7EB !important;
    }

    .tasks-split-view select.task-split-select option {
        background-color: #fff;
        color: #1D2433;
    }

    @media (min-width: 1024px) {
        .tasks-split-view__list {
            width: 380px;
            max-width: 380px;
        }
    }

    .tasks-split-view__list .fi-ta-ctn,
    .tasks-split-view__list .fi-ta {
        border: none;
        box-shadow: none;
        background: transparent;
    }

    .tasks-split-view__list .fi-ta-content,
    .tasks-split-view__list .fi-ta-content-ctn {
        padding: 0 !important;
    }

    .tasks-split-view__list .fi-ta-table:has(.task-split-row) {
        table-layout: fixed;
        width: 100%;
        min-width: 0 !important;
    }

    .tasks-split-view__list .fi-ta-table:has(.task-split-row) > thead {
        display: none;
    }

    /* Checkbox bulk — wąski, bez miażdżenia treści wiersza. Kolumna Edytuj/Usuń ukryta. */
    .tasks-split-view__list .fi-ta-table:has(.task-split-row) .fi-ta-actions-cell,
    .tasks-split-view__list .fi-ta-table:has(.task-split-row) .fi-ta-actions-header-cell {
        display: none !important;
        width: 0 !important;
        min-width: 0 !important;
        max-width: 0 !important;
        padding: 0 !important;
        border: none !important;
    }

    .tasks-split-view__list .fi-ta-table:has(.task-split-row) .fi-ta-selection-cell {
        display: table-cell !important;
        width: 2.25rem !important;
        min-width: 2.25rem !important;
        max-width: 2.25rem !important;
        padding: 0.85rem 0.25rem 0.25rem !important;
        vertical-align: top !important;
        overflow: visible !important;
    }

    .tasks-split-view__list .fi-ta-table:has(.task-split-row) > tbody > tr > td {
        vertical-align: top;
        overflow: visible;
        max-width: none !important;
        padding: 2px 6px !important;
        border-bottom: none !important;
    }

    .tasks-split-view__list .fi-ta-table:has(.task-split-row) th.fi-ta-col-task-summary,
    .tasks-split-view__list .fi-ta-table:has(.task-split-row) td.fi-ta-col-task-summary {
        width: auto !important;
        min-width: 0 !important;
        max-width: none !important;
        color: #111827 !important;
        opacity: 1 !important;
    }

    .tasks-split-view__list .fi-ta-table:has(.task-split-row) .fi-ta-cell,
    .tasks-split-view__list .fi-ta-table:has(.task-split-row) .fi-ta-col-wrp,
    .tasks-split-view__list .fi-ta-table:has(.task-split-row) .fi-ta-text-item,
    .tasks-split-view__list .fi-ta-table:has(.task-split-row) .fi-ta-text-item-label {
        color: inherit !important;
        opacity: 1 !important;
    }

    .tasks-split-view__list .fi-ta-row:hover > td {
        background: transparent !important;
    }

    .tasks-split-view__detail {
        flex: 1;
        min-width: 0;
        min-height: 0;
        display: flex;
        flex-direction: column;
        background: var(--task-bg);
        scroll-margin-top: 1rem;
    }

    .task-split-row {
        display: block;
        padding: 12px;
        border-radius: var(--task-radius-sm);
        border: 1px solid transparent;
        cursor: pointer;
        text-align: left;
        width: 100%;
        box-sizing: border-box;
        background: transparent;
        color: var(--task-text);
    }

    .task-split-row:hover {
        background: var(--task-surface-alt);
    }

    .task-split-row.is-active {
        background: var(--task-accent-soft);
        border-color: #CFE0F3;
    }

    .task-split-row__top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 4px;
    }

    .task-split-row__title {
        font-weight: 700;
        font-size: 13.5px;
        line-height: 1.35;
        color: #111827 !important;
        opacity: 1 !important;
        min-width: 0;
        flex: 1;
        overflow-wrap: anywhere;
    }

    .task-split-row__due {
        font-size: 12px;
        color: #6B7280 !important;
        white-space: nowrap;
        flex-shrink: 0;
        padding-top: 1px;
        font-weight: 500;
    }

    .task-split-row__due.is-overdue {
        color: #B42318 !important;
        font-weight: 700;
    }

    .task-split-row__meta {
        font-size: 12.5px;
        color: #4B5563 !important;
        margin-bottom: 2px;
        line-height: 1.35;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .task-split-row__ownership {
        font-size: 0.7rem;
        color: #6B7280 !important;
        margin-bottom: 8px;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .task-split-row__desc {
        position: relative;
        margin: -0.15rem 0 0.55rem;
    }

    .task-split-row__desc-teaser {
        font-size: 0.76rem;
        line-height: 1.4;
        color: #374151 !important;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .task-split-row.is-active .task-split-row__desc-teaser {
        color: #1F2937 !important;
    }

    .task-split-row__desc-full {
        font-size: 0.78rem;
        line-height: 1.5;
        color: #1F2937 !important;
        margin-bottom: 0.25rem;
    }

    .task-split-row__desc-full :where(p, ul, ol, blockquote, h1, h2, h3, h4) {
        margin: 0 0 0.4rem;
    }

    .task-split-row__desc-full :where(p, ul, ol, blockquote, h1, h2, h3, h4):last-child {
        margin-bottom: 0;
    }

    .task-split-row__desc-full ul,
    .task-split-row__desc-full ol {
        padding-inline-start: 1.1rem;
    }

    .task-split-row__desc-full a {
        color: #1C4E80;
        text-decoration: underline;
    }

    .task-split-row__expand {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        margin-top: 0.3rem;
        padding: 0.15rem 0;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 0.68rem;
        font-weight: 700;
        color: #1C4E80;
        line-height: 1.2;
    }

    .task-split-row__expand:hover {
        color: #163A60;
        text-decoration: underline;
    }

    .task-split-row__expand:focus-visible {
        outline: 2px solid #1C4E80;
        outline-offset: 2px;
        border-radius: 2px;
    }

    .task-split-row__comment .task-split-row__expand {
        margin-top: 0.4rem;
    }

    .task-split-row__comment-thread {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        margin-top: 0.15rem;
    }

    .task-split-row__comment-body.is-expanded {
        display: block;
        -webkit-line-clamp: unset;
        overflow: visible;
        white-space: pre-wrap;
    }

    .task-split-row__bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        min-width: 0;
    }

    .task-status-pill {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        max-width: 100%;
        font-size: 11.5px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 999px;
        line-height: 1.4;
        white-space: nowrap;
    }

    .task-status-pill--todo {
        background: #FDF3E2;
        color: #B45309;
    }

    .task-status-pill--progress {
        background: #EAF1FE;
        color: #2563EB;
    }

    .task-status-pill--done {
        background: #E9F7EF;
        color: #15803D;
    }

    .task-status-pill--cancel {
        background: #FDECEA;
        color: #B42318;
    }

    .task-status-pill--neutral {
        background: #F3F4F6;
        color: #6B7280;
    }

    .task-avatar {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #1C4E80;
        color: #fff;
        font-size: 10.5px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        line-height: 1;
    }

    .task-split-row__comment {
        margin-top: 0.6rem;
        padding: 0.5rem 0.6rem;
        border-radius: 8px;
        background: #E8F0FA;
        border: 1px solid #CFE0F3;
        border-left: 3px solid #1C4E80;
        box-shadow: 0 1px 2px rgba(28, 78, 128, 0.06);
    }

    .task-split-row.is-active .task-split-row__comment {
        background: #DCEAF8;
        border-color: #B7D0EA;
        border-left-color: #1C4E80;
    }

    .task-split-row__comment-head {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem;
        line-height: 1.3;
        margin-bottom: 0.3rem;
        min-width: 0;
    }

    .task-split-row__comment-head-main {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
        min-width: 0;
        flex: 1 1 auto;
    }

    .task-split-row__comment-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #1C4E80;
        background: #fff;
        border: 1px solid #B7D0EA;
        border-radius: 999px;
        padding: 0.1rem 0.45rem;
    }

    .task-split-row__comment-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.15rem;
        height: 1.15rem;
        padding: 0 0.3rem;
        border-radius: 999px;
        font-size: 0.65rem;
        font-weight: 700;
        color: #fff;
        background: #1C4E80;
    }

    .task-split-row__comment-author {
        font-size: 0.68rem;
        font-weight: 600;
        color: #3B6A9A;
    }

    .task-split-row__when {
        flex-shrink: 0;
        margin-left: auto;
        font-size: 0.65rem;
        font-weight: 600;
        line-height: 1.2;
        color: #6B7280 !important;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .task-split-row__comment-body {
        font-size: 0.76rem;
        line-height: 1.4;
        font-weight: 500;
        color: #1D2433;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        white-space: pre-wrap;
    }

    .task-split-row__files {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        margin-top: 0.5rem;
        padding: 0.45rem 0.55rem;
        border-radius: 8px;
        background: #FFF7ED;
        border: 1px solid #FED7AA;
        border-left: 3px solid #EA580C;
    }

    .task-split-row__files-label {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #C2410C;
        margin-bottom: 0.1rem;
    }

    .task-split-row__files-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.15rem;
        height: 1.15rem;
        padding: 0 0.3rem;
        border-radius: 999px;
        font-size: 0.65rem;
        font-weight: 700;
        color: #fff;
        background: #EA580C;
    }

    .task-split-row__file {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        font-size: 0.74rem;
        font-weight: 600;
        line-height: 1.3;
        color: #9A3412;
        background: #fff;
        border: 1px solid #FDBA74;
        border-radius: 6px;
        padding: 0.25rem 0.45rem;
        text-decoration: none;
    }

    .task-split-row__file-name {
        flex: 1 1 auto;
        min-width: 0;
    }

    .task-split-row__file .task-split-row__when {
        color: #C2410C !important;
        opacity: 0.9;
    }

    .task-split-row__file:hover {
        color: #7C2D12;
        background: #FFEDD5;
        border-color: #FB923C;
    }

    .task-split-row__file.is-missing {
        color: #9CA3AF;
        background: #F9FAFB;
        border-color: #E5E7EB;
        cursor: default;
    }

    .task-split-row__file-more {
        font-size: 0.68rem;
        color: #C2410C;
        font-weight: 700;
        padding-left: 0.15rem;
    }

    .task-split-detail__files {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .task-split-detail__file {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.84rem;
        color: #1C4E80;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .task-split-detail__file.is-missing {
        color: var(--task-text-faint, #98A2B3);
        text-decoration: none;
    }

    .task-split-detail {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        background: var(--task-bg, #F5F6F8);
    }

    .task-split-detail__toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        border-bottom: 1px solid var(--task-border, #E5E8EC);
        background: #fff;
        flex-shrink: 0;
    }

    .task-split-detail__toolbar-back,
    .task-split-detail__toolbar-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #374151;
        padding: 0.25rem 0.15rem;
    }

    .task-split-detail__toolbar-back:hover,
    .task-split-detail__toolbar-btn:hover {
        color: #1C4E80;
    }

    .task-split-detail__toolbar-btn.is-danger {
        color: #B42318;
    }

    .task-split-detail__toolbar-actions {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }

    .task-split-detail__editor {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 1rem 1.15rem 1.5rem;
        background: #fff;
    }

    @media (min-width: 1024px) {
        .task-split-detail__editor {
            padding: 1.15rem 1.5rem 1.75rem;
        }
    }

    .task-split-detail__editor .fi-task-full-editor {
        max-width: 52rem;
    }

    .task-split-detail__empty {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        min-height: 16rem;
        color: #6B7280;
        font-size: 0.9rem;
        padding: 2rem;
        text-align: center;
        background: #fff;
    }

    .task-split-detail__scroll {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem 1.75rem 0;
    }

    @media (min-width: 1024px) {
        .task-split-detail__scroll {
            padding: 1.75rem 2.5rem 0;
        }
    }

    .task-split-detail__empty {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        min-height: 16rem;
        color: var(--task-text-faint);
        font-size: 0.9rem;
        padding: 2rem;
        text-align: center;
    }

    .task-split-detail__header-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .task-split-detail__title {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 0.35rem;
        color: #111827 !important;
        line-height: 1.3;
        opacity: 1 !important;
    }

    .task-split-detail__sub {
        color: #4B5563 !important;
        font-size: 0.8125rem;
    }

    .task-split-detail__sub a {
        color: #1C4E80 !important;
        text-decoration: none;
        font-weight: 600;
    }

    .task-split-detail__sub a:hover {
        text-decoration: underline;
    }

    .task-split-detail__actions {
        display: flex;
        gap: 0.75rem;
        flex-shrink: 0;
        padding-top: 0.15rem;
        font-size: 0.8125rem;
    }

    .task-split-detail__actions button {
        color: #374151 !important;
        font-weight: 600;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        opacity: 1 !important;
    }

    .task-split-detail__actions button:hover {
        color: #1C4E80 !important;
    }

    .task-split-detail__actions button.is-danger {
        color: #B42318 !important;
    }

    .task-split-detail__status-row {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.9rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .task-split-detail__status-row .task-split-select {
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 999px;
        padding: 0.4rem 1.85rem 0.4rem 0.75rem !important;
        line-height: 1.3;
    }

    .task-split-detail__meta-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.65rem 1.75rem;
        background: var(--task-surface);
        border: 1px solid var(--task-border);
        border-radius: var(--task-radius-md);
        padding: 1rem 1.1rem;
        margin-top: 1.1rem;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    }

    @media (min-width: 640px) {
        .task-split-detail__meta-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .task-split-detail__meta-label {
        color: var(--task-text-faint);
        font-size: 0.72rem;
        font-weight: 600;
        margin-bottom: 0.15rem;
    }

    .task-split-detail__meta-value {
        color: var(--task-text);
        font-size: 0.8125rem;
    }

    .task-split-detail__section {
        margin-top: 1.5rem;
    }

    .task-split-detail__section-title {
        font-size: 0.8125rem;
        font-weight: 700;
        margin-bottom: 0.65rem;
        color: var(--task-text);
    }

    .task-split-detail__description {
        background: var(--task-surface);
        border: 1px solid var(--task-border);
        border-radius: var(--task-radius-md);
        padding: 0.875rem 1rem;
        color: var(--task-text);
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        font-size: 0.875rem;
        white-space: pre-wrap;
    }

    .task-split-detail__discussion-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.65rem;
    }

    .task-split-detail__discussion-count {
        color: var(--task-text-faint);
        font-weight: 500;
        font-size: 0.78rem;
    }

    .task-thread-expand {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        width: 100%;
        background: var(--task-surface-alt, #FAFBFC);
        border: 1px dashed var(--task-border, #E5E8EC);
        border-radius: var(--task-radius-sm, 6px);
        padding: 0.55rem 0.875rem;
        margin-bottom: 0.65rem;
        color: var(--task-text-muted, #6B7280);
        font-weight: 500;
        font-size: 0.78rem;
        cursor: pointer;
        text-align: left;
    }

    .task-thread-expand:hover {
        background: #F0F2F5;
        color: var(--task-text, #1D2433);
    }

    .task-thread-expand:focus-visible {
        outline: 2px solid var(--task-accent, #1C4E80);
        outline-offset: 2px;
    }

    .dark .task-thread-expand {
        background: rgb(255 255 255 / 0.05);
        border-color: rgb(255 255 255 / 0.12);
        color: rgb(156 163 175);
    }

    .dark .task-thread-expand:hover {
        background: rgb(255 255 255 / 0.1);
        color: rgb(243 244 246);
    }

    .task-comment-card {
        border-radius: var(--task-radius-md);
        border: 1px solid var(--task-border);
        padding: 0.75rem 0.875rem;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        background: var(--task-surface);
    }

    .task-comment-card--earlier {
        background: var(--task-surface-alt);
    }

    .task-comment-card--latest {
        border-color: #CFE0F3;
        background: var(--task-accent-soft);
    }

    .task-comment-card__head {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.4rem;
    }

    .task-comment-card__author {
        font-weight: 600;
        font-size: 0.8125rem;
        color: var(--task-text);
    }

    .task-comment-card__time {
        color: var(--task-text-faint);
        font-size: 0.75rem;
    }

    .task-comment-card__body {
        color: var(--task-text);
        font-size: 0.84rem;
        white-space: pre-wrap;
        line-height: 1.45;
    }

    .task-split-detail__reply {
        border-top: 1px solid var(--task-border);
        background: var(--task-surface);
        padding: 0.875rem 1.25rem;
        flex-shrink: 0;
    }

    @media (min-width: 1024px) {
        .task-split-detail__reply {
            padding: 0.875rem 2.5rem;
        }
    }

    .task-split-detail__reply-inner {
        border: 1px solid var(--task-border);
        border-radius: var(--task-radius-md);
        background: var(--task-surface-alt);
        overflow: hidden;
    }

    .task-split-detail__reply-inner textarea {
        width: 100%;
        border: none;
        background: transparent;
        resize: vertical;
        min-height: 2.75rem;
        padding: 0.75rem 0.875rem;
        font-size: 0.875rem;
        color: var(--task-text);
        outline: none;
    }

    .task-split-detail__reply-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0.65rem;
        border-top: 1px solid var(--task-border);
        gap: 0.5rem;
    }

    .dark .tasks-split-view {
        --task-bg: rgb(17 24 39);
        --task-surface: rgb(31 41 55);
        --task-surface-alt: rgb(55 65 81 / 0.45);
        --task-border: rgb(255 255 255 / 0.1);
        --task-text: rgb(243 244 246);
        --task-text-muted: rgb(156 163 175);
        --task-text-faint: rgb(107 114 128);
        --task-accent-soft: color-mix(in srgb, #1C4E80 35%, transparent);
    }

    .fi-ta-text-item-label:empty + .fi-ta-text-item {
        font-weight: 600;
    }

    .admin-program-planner-section {
        overflow-x: auto;
    }

    .money-nowrap,
    .fi-ta-text-item .money-nowrap,
    .fi-ta-cell.money-nowrap,
    .fi-ta-cell.money-nowrap .fi-ta-text-item,
    .fi-ta-cell.money-nowrap .fi-ta-text-item-label {
        white-space: nowrap !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
    }

    /* Katalog punktów: set / punkt / miasto w wynikach selecta — kilka linii, nie ucinać. */
    .fi-dropdown-list-item {
        align-items: flex-start;
    }
    .fi-dropdown-list-item-label {
        white-space: normal;
        line-height: 1.35;
    }

    @media (max-width: 767.98px) {
        .fi-ta-table > .fi-ta-table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .fi-fo-component-ctn {
            min-width: 0;
        }

        .fi-ac-btn-action {
            min-height: var(--admin-touch-min, 2.75rem);
        }
    }

    /* Portal pilota — aliasy do sor-lw (patrz sekcja widoków Livewire poniżej) */
    .pilot-touch-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: var(--admin-touch-min, 2.75rem);
        padding: 0.65rem 1rem;
        font-weight: 600;
        border-radius: var(--sor-radius-lg);
        text-decoration: none;
    }

    @media (max-width: 767.98px) {
        .fi-panel-pilot .pilot-field,
        .fi-panel-pilot .fi-input,
        .fi-panel-pilot .fi-select-input,
        .fi-panel-pilot .fi-textarea,
        .fi-panel-pilot input[type="text"],
        .fi-panel-pilot input[type="number"],
        .fi-panel-pilot input[type="email"],
        .fi-panel-pilot input[type="tel"],
        .fi-panel-pilot input[type="date"],
        .fi-panel-pilot input[type="datetime-local"],
        .fi-panel-pilot select,
        .fi-panel-pilot textarea {
            min-height: var(--admin-touch-min, 2.75rem);
            font-size: 1rem;
        }

        .fi-panel-pilot .fi-btn,
        .fi-panel-pilot .pilot-touch-btn {
            min-height: var(--admin-touch-min, 2.75rem);
        }

        .fi-panel-pilot .workflow-module-nav-item {
            min-width: 7.5rem;
        }

        .fi-panel-pilot .pilot-hotel-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .fi-panel-pilot .pilot-hotel-table-wrap table {
            min-width: 36rem;
        }

        .fi-panel-pilot .pilot-mobile-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .fi-panel-pilot .pilot-mobile-actions > .fi-btn,
        .fi-panel-pilot .pilot-mobile-actions > .pilot-touch-btn,
        .fi-panel-pilot .pilot-mobile-actions > a,
        .fi-panel-pilot .pilot-mobile-actions > button {
            width: 100%;
        }
    }

    /* ── SOR41 design system (docs/DESIGN_SYSTEM.md) ── */

    .fi-layout,
    .fi-body {
        background-color: var(--sor-surface);
        color: var(--sor-text);
    }

    .fi-main,
    .fi-page,
    .fi-page-content {
        background-color: transparent;
        color: inherit;
    }

    .fi-main {
        margin-inline: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }

    .fi-page > section {
        padding-block: 0.85rem !important;
        gap: 0.85rem !important;
    }

    /* Program imprezy — mniej pustego miejsca w pionie i pełna szerokość kolumny */
    .fi-page:has(.admin-program-toolbar) > section {
        padding-block: 0.65rem !important;
        gap: 0.65rem !important;
    }

    .fi-page:has(.admin-program-toolbar) .workflow-record-context {
        margin-bottom: 0.65rem !important;
    }

    .fi-page:has(.admin-program-toolbar) .fi-page-sub-navigation-tabs {
        margin-bottom: 0.25rem;
    }

    .fi-page:has(.admin-program-toolbar) .fi-resource-relation-managers {
        width: 100%;
        max-width: 100%;
    }

    .fi-sidebar {
        background-color: var(--sor-surface-elevated);
        border-inline-end: 1px solid var(--sor-border);
    }

    .fi-sidebar-header {
        border-bottom: 1px solid var(--sor-border);
    }

    .fi-sidebar-group.sor-nav-group {
        border-inline-start: 3px solid transparent;
        padding-inline-start: 0.35rem;
        margin-inline-start: 0.25rem;
        border-radius: var(--sor-radius-sm);
        transition: border-color var(--sor-transition);
    }

    .fi-sidebar-group.sor-nav-group--events { border-inline-start-color: var(--sor-module-events); }
    .fi-sidebar-group.sor-nav-group--finance { border-inline-start-color: var(--sor-module-finance); }
    .fi-sidebar-group.sor-nav-group--executive { border-inline-start-color: var(--sor-module-executive); }
    .fi-sidebar-group.sor-nav-group--contacts { border-inline-start-color: var(--sor-module-contacts); }
    .fi-sidebar-group.sor-nav-group--people { border-inline-start-color: var(--sor-module-people); }
    .fi-sidebar-group.sor-nav-group--dictionaries { border-inline-start-color: var(--sor-module-dictionaries); }
    .fi-sidebar-group.sor-nav-group--system { border-inline-start-color: var(--sor-module-system); }
    .fi-sidebar-group.sor-nav-group--pilot-trips { border-inline-start-color: var(--sor-module-pilot-trips); }
    .fi-sidebar-group.sor-nav-group--pilot-settlements { border-inline-start-color: var(--sor-module-pilot-settlements); }

    .fi-sidebar-group-label {
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        font-size: 0.68rem;
        color: var(--sor-text-muted);
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn,
    .fi-sidebar-item.fi-sidebar-item-active > .fi-sidebar-item-btn {
        background-color: var(--sor-brand-primary-soft);
        color: var(--sor-brand-primary);
    }

    .fi-topbar {
        background-color: var(--sor-surface-elevated);
        border-bottom: 1px solid var(--sor-border);
        box-shadow: var(--sor-shadow-sm);
    }

    .fi-section,
    .fi-wi-stats-overview-stat,
    .fi-ta-ctn {
        border-radius: var(--sor-radius-lg);
        border-color: var(--sor-border);
        box-shadow: var(--sor-shadow-sm);
    }

    .fi-section-header {
        border-bottom: 1px solid var(--sor-border);
    }

    .fi-btn {
        border-radius: var(--sor-radius-md);
        transition: background-color var(--sor-transition), border-color var(--sor-transition), box-shadow var(--sor-transition);
    }

    .fi-btn-color-primary {
        --tw-ring-color: color-mix(in srgb, var(--sor-brand-primary) 35%, transparent);
    }

    .fi-ta-header-cell {
        font-size: var(--admin-table-header-size);
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--sor-text-muted);
        background-color: var(--sor-surface);
    }

    .fi-ta-row:hover > td {
        background-color: color-mix(in srgb, var(--sor-surface) 70%, var(--sor-surface-elevated)) !important;
    }

    .fi-modal-window {
        border-radius: var(--sor-radius-xl);
        box-shadow: var(--sor-shadow-lg);
        border: 1px solid var(--sor-border);
    }

    .fi-badge {
        border-radius: var(--sor-radius-sm);
        font-weight: 600;
    }

    /* Empty states */
    .sor-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        padding: 2.5rem 1.5rem;
        text-align: center;
        border: 1px dashed var(--sor-border-strong);
        border-radius: var(--sor-radius-lg);
        background: var(--sor-surface-elevated);
    }

    .sor-empty-state__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.25rem;
        height: 3.25rem;
        border-radius: 9999px;
        background: var(--sor-brand-primary-soft);
        color: var(--sor-brand-primary);
    }

    .sor-empty-state__icon-svg {
        width: 1.5rem;
        height: 1.5rem;
    }

    .sor-empty-state--events .sor-empty-state__icon { background: #EFF6FF; color: var(--sor-module-events); }
    .sor-empty-state--finance .sor-empty-state__icon { background: #ECFDF5; color: var(--sor-module-finance); }
    .sor-empty-state--program .sor-empty-state__icon { background: #FFFBEB; color: var(--sor-brand-primary); }
    .sor-empty-state--contacts .sor-empty-state__icon { background: #ECFEFF; color: var(--sor-module-contacts); }
    .sor-empty-state--tasks .sor-empty-state__icon { background: #F5F3FF; color: var(--sor-module-executive); }

    .sor-empty-state__heading {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--sor-text);
        line-height: 1.35;
    }

    .sor-empty-state__description {
        margin: 0;
        max-width: 28rem;
        font-size: 0.92rem;
        line-height: 1.5;
        color: var(--sor-text-muted);
    }

    .sor-empty-state__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 0.35rem;
    }

    /* Design system preview page */
    .sor-design-preview__logo-card {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding: 1.25rem;
        border: 1px solid var(--sor-border);
        border-radius: var(--sor-radius-lg);
        background: var(--sor-surface-elevated);
    }

    .sor-design-preview__label {
        margin: 0;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--sor-text-muted);
    }

    .sor-design-preview__swatch {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        padding: 0.65rem;
        border: 1px solid var(--sor-border);
        border-radius: var(--sor-radius-md);
        background: var(--sor-surface-elevated);
    }

    .sor-design-preview__swatch-color {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: var(--sor-radius-sm);
        border: 1px solid color-mix(in srgb, var(--sor-text) 8%, transparent);
        flex-shrink: 0;
    }

    .sor-design-preview__swatch-meta {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        min-width: 0;
    }

    .sor-design-preview__swatch-label {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--sor-text);
    }

    .sor-design-preview__swatch-hex {
        font-size: 0.75rem;
        color: var(--sor-text-muted);
        font-variant-numeric: tabular-nums;
    }

    .sor-design-preview__swatch-token {
        font-size: 0.68rem;
        color: var(--sor-text-muted);
        word-break: break-all;
    }

    .sor-design-preview__nav-chip {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.75rem 1rem;
        border: 1px solid var(--sor-border);
        border-radius: var(--sor-radius-md);
        background: var(--sor-surface-elevated);
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--sor-text);
        border-inline-start-width: 3px;
    }

    .sor-design-preview__nav-chip.sor-nav-group--events { border-inline-start-color: var(--sor-module-events); }
    .sor-design-preview__nav-chip.sor-nav-group--finance { border-inline-start-color: var(--sor-module-finance); }
    .sor-design-preview__nav-chip.sor-nav-group--executive { border-inline-start-color: var(--sor-module-executive); }
    .sor-design-preview__nav-chip.sor-nav-group--contacts { border-inline-start-color: var(--sor-module-contacts); }
    .sor-design-preview__nav-chip.sor-nav-group--people { border-inline-start-color: var(--sor-module-people); }
    .sor-design-preview__nav-chip.sor-nav-group--dictionaries { border-inline-start-color: var(--sor-module-dictionaries); }
    .sor-design-preview__nav-chip.sor-nav-group--system { border-inline-start-color: var(--sor-module-system); }

    /*
     * Widoki Livewire / custom Blade — komponenty sor-lw (nie wymagają Tailwinda).
     * Używaj tych klas zamiast bg-teal-600, bg-white, text-gray-* w panelu.
     */
    .sor-lw-stack > * + * {
        margin-top: 1rem;
    }

    .sor-lw-card,
    .pilot-card {
        border-radius: var(--sor-radius-xl);
        border: 1px solid var(--sor-border);
        background: var(--sor-surface-elevated);
        padding: 1rem;
        box-shadow: var(--sor-shadow-sm);
    }

    .sor-lw-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--sor-text);
    }

    .sor-lw-muted {
        font-size: 0.75rem;
        color: var(--sor-text-muted);
    }

    .sor-lw-link {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--sor-module-pilot-trips);
        text-decoration: none;
    }

    .sor-lw-link:hover {
        color: #0f766e;
    }

    .sor-lw-row {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    @media (min-width: 640px) {
        .sor-lw-row--inline {
            flex-direction: row;
        }

        .sor-lw-row--inline > .sor-lw-field,
        .sor-lw-row--inline > select.sor-lw-field {
            flex: 1 1 auto;
        }
    }

    .sor-lw-btn,
    .pilot-touch-btn.sor-lw-btn--accent {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: var(--admin-touch-min);
        padding: 0 1rem;
        border-radius: var(--sor-radius-md);
        font-size: 0.875rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        text-decoration: none;
        line-height: 1.25;
    }

    .sor-lw-btn--primary {
        background: var(--sor-brand-primary);
        color: #fff;
    }

    .sor-lw-btn--primary:hover:not(:disabled) {
        background: var(--sor-brand-primary-hover);
    }

    .sor-lw-btn--accent,
    .pilot-touch-btn.sor-lw-btn--accent {
        background: var(--sor-module-pilot-trips);
        color: #fff;
    }

    .sor-lw-btn--accent:hover:not(:disabled) {
        background: #0f766e;
    }

    .sor-lw-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .sor-lw-alert {
        border-radius: var(--sor-radius-md);
        border: 1px solid #fde68a;
        background: var(--sor-brand-primary-soft);
        padding: 1rem;
        font-size: 0.875rem;
        color: #78350f;
    }

    .sor-lw-empty {
        border-radius: var(--sor-radius-xl);
        border: 1px dashed var(--sor-border-strong);
        background: var(--sor-surface);
        padding: 1.5rem;
        text-align: center;
        font-size: 0.875rem;
        color: var(--sor-text-muted);
    }

    .sor-lw-progress {
        height: 0.5rem;
        overflow: hidden;
        border-radius: 9999px;
        background: var(--sor-border);
    }

    .sor-lw-progress__bar {
        height: 100%;
        border-radius: 9999px;
        background: var(--sor-module-pilot-trips);
        transition: width 0.2s ease;
    }

    .sor-lw-check {
        display: flex;
        height: 1.75rem;
        width: 1.75rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        border: 2px solid var(--sor-border-strong);
        background: var(--sor-surface-elevated);
        color: transparent;
        cursor: pointer;
    }

    .sor-lw-check.is-done {
        border-color: var(--sor-module-pilot-trips);
        background: var(--sor-module-pilot-trips);
        color: #fff;
    }

    .sor-lw-check:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .sor-lw-task {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        border-radius: var(--sor-radius-xl);
        border: 1px solid var(--sor-border);
        background: var(--sor-surface-elevated);
        padding: 0.75rem;
        box-shadow: var(--sor-shadow-sm);
    }

    .sor-lw-task__title {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--sor-text);
    }

    .sor-lw-task__title.is-done {
        color: var(--sor-text-muted);
        text-decoration: line-through;
    }

    .sor-lw-accent {
        color: var(--sor-module-pilot-trips);
        font-weight: 700;
    }

    /* Kompatybilność wsteczna: Tailwind teal/amber/primary w widokach Livewire w panelu admin */
    .fi-body button.bg-teal-600,
    .fi-body a.bg-teal-600,
    .fi-main button.bg-teal-600,
    .fi-main a.bg-teal-600,
    .fi-body .bg-teal-600,
    .fi-main .bg-teal-600 {
        background-color: var(--sor-module-pilot-trips) !important;
        color: #fff !important;
        border: none;
    }

    .fi-body button.bg-teal-600:hover:not(:disabled),
    .fi-body a.bg-teal-600:hover,
    .fi-main button.bg-teal-600:hover:not(:disabled),
    .fi-body .hover\:bg-teal-700:hover,
    .fi-main .hover\:bg-teal-700:hover {
        background-color: #0f766e !important;
    }

    .fi-body button.bg-amber-500,
    .fi-body button.bg-amber-600,
    .fi-main button.bg-amber-500,
    .fi-main button.bg-amber-600,
    .fi-body a.bg-amber-500,
    .fi-body a.bg-amber-600,
    .fi-main a.bg-amber-500,
    .fi-main a.bg-amber-600 {
        background-color: var(--sor-warning) !important;
        color: #fff !important;
        border: 1px solid color-mix(in srgb, var(--sor-warning) 75%, #000) !important;
    }

    .fi-body button.bg-amber-500:hover:not(:disabled),
    .fi-body button.bg-amber-600:hover:not(:disabled),
    .fi-main button.bg-amber-500:hover:not(:disabled),
    .fi-main button.bg-amber-600:hover:not(:disabled),
    .fi-body button.hover\:bg-amber-600:hover,
    .fi-main button.hover\:bg-amber-600:hover {
        background-color: var(--sor-brand-primary-hover) !important;
    }

    .fi-body button.bg-primary-600,
    .fi-body button.bg-primary-700,
    .fi-main button.bg-primary-600,
    .fi-main button.bg-primary-700,
    .fi-body a.bg-primary-600,
    .fi-main a.bg-primary-600 {
        background-color: var(--sor-brand-primary) !important;
        color: #fff !important;
        border: 1px solid color-mix(in srgb, var(--sor-brand-primary) 75%, #000) !important;
    }

    .fi-body button.bg-primary-600:hover:not(:disabled),
    .fi-main button.bg-primary-600:hover:not(:disabled),
    .fi-body button.hover\:bg-primary-700:hover,
    .fi-main button.hover\:bg-primary-700:hover,
    .fi-body button.hover\:bg-primary-500:hover,
    .fi-main button.hover\:bg-primary-500:hover {
        background-color: var(--sor-brand-primary-hover) !important;
    }

    .fi-body button.bg-orange-600,
    .fi-main button.bg-orange-600,
    .fi-body a.bg-orange-600,
    .fi-main a.bg-orange-600 {
        background-color: #ea580c !important;
        color: #fff !important;
        border: 1px solid #c2410c !important;
    }

    .fi-body button.bg-orange-600:hover:not(:disabled),
    .fi-main button.bg-orange-600:hover:not(:disabled),
    .fi-body button.hover\:bg-orange-700:hover,
    .fi-main button.hover\:bg-orange-700:hover {
        background-color: #c2410c !important;
    }

    .fi-body button.bg-blue-600,
    .fi-main button.bg-blue-600 {
        background-color: var(--sor-info) !important;
        color: #fff !important;
        border: 1px solid color-mix(in srgb, var(--sor-info) 75%, #000) !important;
    }

    .fi-body button.bg-red-600,
    .fi-main button.bg-red-600 {
        background-color: var(--sor-danger) !important;
        color: #fff !important;
        border: 1px solid color-mix(in srgb, var(--sor-danger) 75%, #000) !important;
    }

    .fi-body button.bg-emerald-600,
    .fi-main button.bg-emerald-600 {
        background-color: var(--sor-success) !important;
        color: #fff !important;
        border: 1px solid color-mix(in srgb, var(--sor-success) 75%, #000) !important;
    }

    .fi-body .text-teal-700,
    .fi-main .text-teal-700 {
        color: var(--sor-module-pilot-trips) !important;
    }

    .fi-body button.text-white,
    .fi-body a.text-white,
    .fi-main button.text-white,
    .fi-main a.text-white {
        color: #fff !important;
    }

    /* Legacy Tailwind gray/white w widokach Livewire — mapowanie na tokeny */
    :is(.dark, html[data-theme="dark"]) .fi-body .bg-white,
    :is(.dark, html[data-theme="dark"]) .fi-body .bg-gray-50,
    :is(.dark, html[data-theme="dark"]) .fi-body .bg-gray-100 {
        background-color: var(--sor-surface-elevated) !important;
    }

    :is(.dark, html[data-theme="dark"]) .fi-body .border-gray-200,
    :is(.dark, html[data-theme="dark"]) .fi-body .border-gray-300 {
        border-color: var(--sor-border) !important;
    }

    :is(.dark, html[data-theme="dark"]) .fi-body .text-gray-900,
    :is(.dark, html[data-theme="dark"]) .fi-body .text-gray-800,
    :is(.dark, html[data-theme="dark"]) .fi-body .text-gray-950 {
        color: var(--sor-text) !important;
    }

    :is(.dark, html[data-theme="dark"]) .fi-body .text-gray-600,
    :is(.dark, html[data-theme="dark"]) .fi-body .text-gray-500,
    :is(.dark, html[data-theme="dark"]) .fi-body .text-gray-400 {
        color: var(--sor-text-muted) !important;
    }

    :is(.dark, html[data-theme="dark"]) .fi-body .topbar-notification-panel {
        background-color: var(--sor-surface-elevated) !important;
        border-color: var(--sor-border) !important;
    }

    /* Tiptap — formularz tworzenia imprezy, uwagi, modal zadania */
    .fi-body .fi-fo-field-wrp .tiptap-wrapper,
    .fi-body .event-notes-editor .tiptap-wrapper,
    .fi-body .tiptap-wrapper {
        position: relative;
        /* z-0 z pakietu chowało panele pod kolejnymi sekcjami formularza */
        z-index: 20;
        overflow: visible !important;
        border-radius: var(--sor-radius-md);
    }

    .fi-body .fi-fo-field-wrp .tiptap-toolbar,
    .fi-body .event-notes-editor .tiptap-toolbar,
    .fi-body .tiptap-toolbar {
        position: sticky;
        top: 0;
        z-index: 30;
        /* overflow-x:auto obcinało submenu koloru / nagłówków (absolute panels) */
        overflow: visible !important;
        flex-wrap: wrap;
        background: var(--sor-surface-elevated);
        border-bottom: 1px solid var(--sor-border);
    }

    .fi-body .fi-fo-field-wrp .tiptap-toolbar-left,
    .fi-body .event-notes-editor .tiptap-toolbar-left,
    .fi-body .tiptap-toolbar-left {
        flex-wrap: wrap;
        overflow: visible !important;
        min-width: 0;
    }

    .fi-body .tiptap-toolbar .relative {
        overflow: visible !important;
    }

    /*
     * Panele kolor/nagłówki renderują się przez Popover API (top layer).
     * Sam z-index nie wystarczy względem .fi-layout { overflow-x-clip }.
     */
    .fi-body .tiptap-panel,
    .tiptap-panel:popover-open {
        z-index: 9999 !important;
        min-width: 12rem;
        max-width: min(22rem, 92vw);
        box-shadow: 0 10px 25px rgb(0 0 0 / 0.18);
        border: 0;
        padding: 0;
        overflow: visible;
    }

    .fi-body .tiptap-panel tiptap-hex-color-picker,
    .tiptap-panel tiptap-hex-color-picker,
    .tiptap-hex-picker {
        display: block;
        width: 100%;
        min-width: 200px;
        height: 200px;
        margin: 0 auto;
    }

    .tiptap-color-picker-body {
        min-width: 13.5rem;
        max-width: 16rem;
        padding: 0.25rem;
    }

    .tiptap-color-a-icon {
        color: inherit;
    }

    .fi-body .tiptap-tool .tiptap-color-a-icon {
        pointer-events: none;
    }

    .fi-body .fi-fo-field-wrp .tiptap-prosemirror-wrapper {
        min-height: 8rem;
        max-height: none;
    }

    /*
     * Uwagi imprezy — przeciąganie wysokości (jak natywny textarea).
     * Klasa .event-notes-editor jest na .tiptap-content (dziecko), więc celujemy
     * przez .event-notes-field (wrapper) lub :has(.event-notes-editor).
     * overflow ≠ visible + jawna wysokość startowa — wymagane dla CSS resize.
     * !important przebija max-h-[40rem] / overflow-y-scroll z pakietu TipTap.
     */
    .fi-body .event-notes-field .tiptap-prosemirror-wrapper,
    .fi-body .fi-fo-field-wrp .tiptap-prosemirror-wrapper:has(.event-notes-editor),
    .fi-body .tiptap-prosemirror-wrapper:has(.event-notes-editor) {
        min-height: 12rem !important;
        height: 12rem;
        max-height: 85vh !important;
        overflow-x: auto !important;
        overflow-y: auto !important;
        resize: vertical !important;
    }

    /*
     * Treść zadania (TipTap) — przeciąganie wysokości jak uwagi imprezy.
     * W panelu split startujemy wyżej, żeby pole nie wyglądało na „paskowe”.
     */
    .fi-body .task-content-field .tiptap-prosemirror-wrapper,
    .fi-body .fi-fo-field-wrp .tiptap-prosemirror-wrapper:has(.task-content-editor),
    .fi-body .tiptap-prosemirror-wrapper:has(.task-content-editor) {
        min-height: 14rem !important;
        height: 14rem;
        max-height: 85vh !important;
        overflow-x: auto !important;
        overflow-y: auto !important;
        resize: vertical !important;
    }

    .task-split-detail__editor .task-content-field .tiptap-prosemirror-wrapper,
    .task-split-detail__editor .tiptap-prosemirror-wrapper:has(.task-content-editor) {
        min-height: 18rem !important;
        height: min(42vh, 22rem);
        max-height: 75vh !important;
    }

    .fi-body textarea.event-notes-textarea {
        min-height: 4.5rem;
        max-height: 85vh;
        resize: vertical;
    }

    .task-full-editor-shell {
        overflow: visible !important;
    }

    .fi-body .fi-fo-field-wrp .tiptap-editor .ProseMirror {
        min-height: 6rem;
        margin-inline: 0 !important;
        text-align: left !important;
        line-height: 1.6 !important;
        letter-spacing: normal !important;
    }

    .fi-body .task-content-field .tiptap-editor .ProseMirror,
    .fi-body .tiptap-editor:has(.task-content-editor) .ProseMirror,
    .fi-body .task-content-editor.tiptap-content {
        min-height: 12rem;
    }

    .fi-body .event-notes-field .tiptap-editor .ProseMirror,
    .fi-body .event-notes-editor.tiptap-content,
    .fi-body .event-notes-editor.ProseMirror {
        min-height: 10rem;
        margin-inline: 0 !important;
        text-align: left !important;
        line-height: 1.6 !important;
        letter-spacing: normal !important;
    }

    /* Tabele w uwagach — widoczne obramowanie / nagłówki (także poza samym TipTap CSS) */
    .fi-body .event-notes-field .ProseMirror table,
    .fi-body .tiptap-editor .ProseMirror table {
        border-collapse: collapse;
        width: 100%;
        margin: 0.75rem 0;
        table-layout: fixed;
        overflow: hidden;
    }

    .fi-body .event-notes-field .ProseMirror table td,
    .fi-body .event-notes-field .ProseMirror table th,
    .fi-body .tiptap-editor .ProseMirror table td,
    .fi-body .tiptap-editor .ProseMirror table th {
        border: 1px solid rgb(156 163 175) !important;
        padding: 0.35rem 0.5rem !important;
        vertical-align: top;
        text-align: left !important;
        line-height: 1.45 !important;
    }

    .fi-body .event-notes-field .ProseMirror table th,
    .fi-body .tiptap-editor .ProseMirror table th {
        background-color: rgb(243 244 246);
        font-weight: 700;
    }

    :is(.dark, html[data-theme="dark"]) .fi-body .event-notes-field .ProseMirror table td,
    :is(.dark, html[data-theme="dark"]) .fi-body .event-notes-field .ProseMirror table th,
    :is(.dark, html[data-theme="dark"]) .fi-body .tiptap-editor .ProseMirror table td,
    :is(.dark, html[data-theme="dark"]) .fi-body .tiptap-editor .ProseMirror table th {
        border-color: rgb(75 85 99) !important;
    }

    :is(.dark, html[data-theme="dark"]) .fi-body .event-notes-field .ProseMirror table th,
    :is(.dark, html[data-theme="dark"]) .fi-body .tiptap-editor .ProseMirror table th {
        background-color: rgb(31 41 55);
    }

    .fi-body .tiptap-prosemirror-wrapper {
        margin-inline: 0 !important;
    }

    .fi-body .tiptap-prosemirror-wrapper[class*="prosemirror-w-"] {
        padding-inline: 1rem !important;
    }

    /* Nadpisuje inline text-align:center z Tiptap (akapit / nagłówek) */
    .fi-body .tiptap-editor .ProseMirror mark,
    .fi-body .event-notes-editor .tiptap-editor .ProseMirror mark {
        background-color: #fef08a;
        color: inherit;
        border-radius: 0.15rem;
        padding: 0 0.1em;
    }

    .fi-body .tiptap-editor .ProseMirror [style*="color"],
    .fi-body .event-notes-editor .tiptap-editor .ProseMirror span[style*="color"] {
        /* zachowaj inline color z TipTap Color */
    }

    .fi-body .fi-fo-field-wrp .tiptap-editor .ProseMirror :where(p, h1, h2, h3, h4, h5, h6, li, blockquote, td, th) {
        text-align: left !important;
        line-height: 1.6 !important;
        letter-spacing: normal !important;
    }

    .fi-body .fi-fo-rich-editor .tiptap-bubble-menu,
    .fi-body .fi-fo-rich-editor .tiptap-floating-menu,
    .fi-body .event-notes-editor .tiptap-bubble-menu,
    .fi-body .event-notes-editor .tiptap-floating-menu,
    .fi-body .tiptap-bubble-menu,
    .fi-body .tiptap-floating-menu {
        z-index: 90;
    }

    .fi-body .fi-section:not(.fi-collapsed) .fi-fo-field-wrp.event-notes-field,
    .fi-body .fi-section:not(.fi-collapsed) .fi-fo-field-wrp:has(.event-notes-editor) {
        overflow: visible;
    }

/* Szablon umowy — Filament RichEditor: czytelna typografia */
.fi-body .fi-fo-rich-editor.contract-template-rich,
.fi-body .contract-template-rich .fi-fo-rich-editor,
.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .fi-fo-rich-editor,
.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .ProseMirror,
.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .tiptap {
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    font-size: 0.95rem !important;
    line-height: 1.65 !important;
    letter-spacing: normal !important;
}

.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .ProseMirror,
.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .tiptap {
    min-height: 22rem !important;
    padding: 0.85rem 1rem !important;
}

.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .ProseMirror p,
.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .tiptap p {
    margin: 0 0 0.65rem !important;
    line-height: 1.65 !important;
    letter-spacing: normal !important;
}

.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .ProseMirror strong,
.fi-body .fi-fo-field-wrp:has(.contract-template-rich) .tiptap strong {
    font-weight: 700 !important;
}

.fi-body .contract-template-preview {
    font-size: 0.9rem;
    line-height: 1.65;
    letter-spacing: normal;
    white-space: normal;
}

.fi-body .contract-template-preview p {
    margin: 0 0 0.65rem;
}

    /* =====================================================================
       Responsive system — sticky ladder, overflow, dense UI, devices
       Breakpoints: phone ≤639 | tablet ≤767/1023 | laptop ≥1024 | desktop ≥1280
       ===================================================================== */
    :root {
        --sor-chrome-top: 0px;
        --sor-sticky-z-topbar: 60;
        --sor-sticky-z-workflow: 28;
        --sor-sticky-z-local: 18;
        --sor-sticky-z-dropdown: 50;
    }

    @media (min-width: 1280px) {
        :root {
            --sor-chrome-top: 3.25rem;
        }
    }

    /* Drabina sticky: topbar → workflow nav → lokalny toolbar */
    .workflow-module-nav {
        top: var(--sor-chrome-top) !important;
        z-index: var(--sor-sticky-z-workflow) !important;
    }

    .admin-program-toolbar,
    .sor-sticky-toolbar {
        position: sticky;
        top: var(--sor-chrome-top);
        z-index: var(--sor-sticky-z-local);
        background: color-mix(in srgb, var(--sor-surface-elevated) 95%, transparent);
        backdrop-filter: blur(6px);
    }

    .fi-page:has(.workflow-module-nav) .admin-program-toolbar,
    .fi-page:has(.workflow-module-nav) .sor-sticky-toolbar {
        top: calc(var(--sor-chrome-top) + 3.25rem);
    }

    /* Na telefonie/tablecie wyłączamy sticky lokalne — unikamy nakładania warstw */
    @media (max-width: 1023.98px) {
        .admin-program-toolbar,
        .sor-sticky-toolbar,
        .fi-page:has(.workflow-module-nav) .admin-program-toolbar,
        .fi-page:has(.workflow-module-nav) .sor-sticky-toolbar {
            position: relative !important;
            top: auto !important;
            z-index: auto !important;
            backdrop-filter: none;
        }

        .workflow-module-nav {
            position: relative !important;
            top: auto !important;
            z-index: 10 !important;
        }

        .fi-body .fi-fo-field-wrp .tiptap-toolbar,
        .fi-body .event-notes-editor .tiptap-toolbar {
            position: relative !important;
            top: auto !important;
        }
    }

    /* Opisy w workflow nav — chowamy na wąskich ekranach, zostaje label */
    @media (max-width: 1023.98px) {
        .workflow-module-nav-item {
            min-width: 0 !important;
            padding-block: 0.25rem !important;
            padding-inline: 0.55rem !important;
        }

        .workflow-module-nav .text-\[0\.65rem\] {
            display: none;
        }
    }

    /* Gwarancja scrollu poziomego — nigdy nie giną w overflow-hidden parent */
    .sor-scroll-x,
    .sor-dense-table-wrap,
    .fi-section-content:has(table),
    .sor-lw-card:has(table) {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        max-width: 100%;
    }

    .sor-lw-card.overflow-hidden:has(table),
    .overflow-hidden:has(> .overflow-x-auto),
    .overflow-hidden:has(> table) {
        overflow-x: auto !important;
    }

    /* Gridy dense UI — wymuszenie 1 kolumny na telefonie */
    @media (max-width: 639.98px) {
        .sor-grid-responsive,
        .event-program-planner .grid.grid-cols-3,
        .event-program-planner .grid.grid-cols-2,
        .event-program-day-tree .grid.grid-cols-2,
        .fi-page .grid.grid-cols-3:not(.sm\:grid-cols-2):not(.sm\:grid-cols-3) {
            grid-template-columns: minmax(0, 1fr) !important;
        }

        .fi-page .grid.grid-cols-2:not([class*="sm:grid-cols"]):not([class*="md:grid-cols"]) {
            grid-template-columns: minmax(0, 1fr) !important;
        }

        .fi-header-actions,
        .fi-page-header-actions,
        .fi-ac {
            flex-wrap: wrap !important;
            gap: 0.5rem !important;
            max-width: 100%;
        }

        .fi-header-actions .fi-btn,
        .fi-page-header-actions .fi-btn,
        .fi-ac .fi-btn {
            max-width: 100%;
        }

        .fi-main,
        .fi-page,
        .fi-page-content,
        .fi-section-content {
            min-width: 0 !important;
            max-width: 100% !important;
        }
    }

    @media (min-width: 640px) and (max-width: 1023.98px) {
        .event-program-planner .grid.grid-cols-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
    }

    /* Chat — layout mobile: lista LUB wątek */
    .sor-chat-shell {
        min-height: min(80vh, 52rem);
        max-width: 100%;
    }

    .sor-chat-shell .sor-chat-aside {
        width: 20rem;
        min-width: 16rem;
        max-width: 20rem;
        flex-shrink: 0;
    }

    .sor-chat-shell .sor-chat-main {
        min-width: 0;
        flex: 1 1 auto;
    }

    @media (max-width: 767.98px) {
        .sor-chat-shell {
            margin: 0.5rem !important;
            min-height: calc(100dvh - 6rem);
            flex-direction: column;
        }

        .sor-chat-shell .sor-chat-aside {
            width: 100% !important;
            min-width: 0 !important;
            max-width: none !important;
            border-right: none !important;
            border-bottom: 1px solid var(--sor-border);
            max-height: 100%;
        }

        .sor-chat-shell[data-mobile-pane="thread"] .sor-chat-aside {
            display: none !important;
        }

        .sor-chat-shell[data-mobile-pane="list"] .sor-chat-main {
            display: none !important;
        }

        .sor-chat-shell .sor-chat-back {
            display: inline-flex !important;
        }
    }

    .sor-chat-shell .sor-chat-back {
        display: none;
    }

    /* Kanban / kalendarz — bezpieczny scroll */
    @media (max-width: 1023.98px) {
        .kanban-board {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 0.75rem;
            gap: 0.75rem;
            -webkit-overflow-scrolling: touch;
        }

        .kanban-board .kanban-column {
            flex: 0 0 auto;
            min-width: min(17rem, 85vw);
            max-width: 85vw;
        }

        .event-program-planner-scroll,
        .admin-program-planner-section,
        .fc {
            max-width: 100%;
        }

        .event-program-planner-scroll .fc {
            min-width: 36rem;
        }
    }

    /* Drawer finansów — pełna szerokość na telefonie */
    @media (max-width: 639.98px) {
        .fi-page aside.max-w-md {
            max-width: 100% !important;
        }

        .custom-topbar-notifications .topbar-notification-panel,
        .topbar-notification-panel {
            width: min(28rem, calc(100vw - 1rem)) !important;
            left: 0.5rem !important;
            right: 0.5rem !important;
        }
    }

    /* Formularze Filament — pola nie wychodzą poza viewport */
    .fi-fo-component-ctn,
    .fi-fo-field-wrp,
    .fi-input-wrp,
    .fi-select-input,
    .fi-textarea {
        max-width: 100%;
        min-width: 0;
    }

    /* Livewire sticky toolbary (uczestnicy / hotel) — klasa zamiast Tailwind sticky top-0 */
    .sor-sticky-toolbar {
        margin-bottom: 1rem;
        border: 1px solid var(--sor-border);
        border-radius: var(--sor-radius-xl);
        padding: 1rem;
        box-shadow: var(--sor-shadow-sm);
    }

    /* Tablet landscape / iPad — bezpieczne paddingi contentu */
    @media (min-width: 768px) and (max-width: 1279.98px) {
        .fi-page-content,
        .fi-simple-main-ctn {
            padding-inline: 0.85rem;
        }

        .fi-ta-table {
            min-width: min(var(--admin-table-min-width), 100%);
        }
    }

    /* Zapobieganie nachodzeniu flex/grid children — bez globalnego min-width:0 na * */
    .fi-page .fi-section-content,
    .fi-page .fi-fo-component-ctn,
    .fi-page .fi-fo-field-wrp,
    .sor-chat-shell .sor-chat-aside,
    .sor-chat-shell .sor-chat-main,
    .workflow-module-nav {
        min-width: 0;
        max-width: 100%;
    }

    /* Hint scrollu poziomego — widoczny tylko gdy kontener faktycznie się przewija */
    @media (max-width: 1023.98px) {
        .sor-scroll-hint {
            position: relative;
        }

        .sor-scroll-hint::before {
            content: '↔ Przesuń w bok, aby zobaczyć więcej';
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--sor-text-muted);
        }

        .fi-ta-ctn:has(.fi-ta-table),
        .sor-dense-table-wrap,
        .sor-scroll-x,
        .overflow-x-auto:has(table[class*='min-w-']) {
            position: relative;
        }

        .fi-ta-ctn:has(.fi-ta-table)::before,
        .sor-dense-table-wrap::before,
        .sor-scroll-x::before,
        .overflow-x-auto:has(table[class*='min-w-'])::before {
            content: '↔ Przesuń w bok, aby zobaczyć więcej';
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--sor-text-muted);
        }
    }

    /* Modale Livewire fixed — nad sidebar, pod Filament modal */
    .fi-body .fixed.inset-0.z-40,
    .fi-body .fixed.inset-0.z-50 {
        z-index: 1400 !important;
    }
</style>
