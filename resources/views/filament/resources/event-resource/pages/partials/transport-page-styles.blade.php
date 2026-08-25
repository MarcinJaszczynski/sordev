<style>
    /*
     * Transport page — wizualna zgodność z mockup-reference-transport.html
     * Paleta: karty 12px, kontrolki 8px, border #D3D1C7 / #E5E3DA
     */
    .transport-page-root {
        --transport-bg: #F5F4EF;
        --transport-card: #FFFFFF;
        --transport-muted-card: #F1EFE8;
        --transport-border: #E5E3DA;
        --transport-control-border: #D3D1C7;
        --transport-text: #2C2C2A;
        --transport-text-secondary: #5F5E5A;
        --transport-text-muted: #888780;
        --transport-accent-bg: #E6F1FB;
        --transport-accent-text: #0C447C;
        --transport-control-radius: 8px;
        --transport-card-radius: 12px;
        --transport-control-height: var(--admin-touch-min);
        /* Podpięte pod globalną skalę zaplecza (referencja Operacje) */
        --transport-control-font: var(--admin-input-size);
        --transport-label-font: var(--admin-label-size);
        color: var(--transport-text);
        font-size: var(--admin-body-size);
    }

    /* ===== Status bar ===== */
    .transport-status-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        background: var(--transport-card);
        border: 1px solid var(--transport-border);
        border-radius: var(--transport-card-radius);
        flex-wrap: wrap;
        gap: 14px;
        box-shadow: 0 1px 0 rgba(44, 44, 42, 0.04);
    }

    .transport-status-title {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 280px;
        flex: 1 1 auto;
    }

    .transport-status-title .transport-status-icon {
        width: 44px;
        height: 44px;
        font-size: 28px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--transport-muted-card);
        border: 1px solid var(--transport-border);
        border-radius: 10px;
    }

    .transport-status-title p {
        margin: 0;
        color: var(--transport-text);
    }

    .transport-status-title p.transport-status-main {
        font-size: 20px;
        font-weight: 600;
        letter-spacing: -0.01em;
        line-height: 1.25;
    }

    .transport-status-title p.transport-status-sub {
        margin-top: 4px;
        font-size: 14px;
        color: var(--transport-text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 720px;
    }

    .transport-badges {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        flex: 0 1 auto;
        justify-content: flex-end;
    }

    .transport-badge {
        font-size: var(--transport-control-font);
        padding: 6px 12px;
        border-radius: var(--transport-control-radius);
        font-weight: 600;
        line-height: 1.35;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    /* Success na białym tle karty — bez zlewania z beżem status bara */
    .transport-badge--warning {
        background: #FAEEDA;
        color: #633806;
        border-color: #E8D4B0;
    }
    .transport-badge--success {
        background: #EAF3DE;
        color: #27500A;
        border-color: #C0D6A8;
    }
    .transport-badge--danger {
        background: #FCEBEB;
        color: #791F1F;
        border-color: #E8B4B4;
    }
    .transport-badge--muted {
        background: #F1EFE8;
        color: #5F5E5A;
        border-color: var(--transport-control-border);
    }

    /* ===== Sticky actions ===== */
    .transport-sticky-actions {
        position: sticky;
        bottom: 0;
        z-index: 10;
        border-radius: var(--transport-card-radius);
        border: 1px solid var(--transport-border);
        background: var(--transport-muted-card);
        padding: 12px 16px;
        margin-top: 14px;
    }

    .transport-sticky-actions .fi-form-actions {
        margin: 0;
    }

    /* ===== Layout 2 kolumny ===== */
    .transport-transport-form-grid {
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, 1fr) !important;
        align-items: start;
        gap: 14px !important;
    }

    .transport-main-column,
    .transport-sidebar {
        display: flex;
        flex-direction: column;
        gap: 14px;
        overflow: visible;
    }

    /* Sticky sidebar nie może mieć overflow:hidden — ucina dropdowny selectów. */
    .transport-page .transport-sidebar.sticky {
        overflow: visible;
        z-index: 5;
    }

    /* ===== Karty sekcji =====
       overflow:visible — searchable Select (autokar / kierowca) renderuje dropdown
       wewnątrz sekcji; overflow:hidden przycina listę i psuje live search. */
    .transport-page .transport-form-card.fi-section,
    .transport-page .transport-sidebar-card.fi-section {
        background: var(--transport-card);
        border: 1px solid var(--transport-border);
        border-radius: var(--transport-card-radius);
        box-shadow: none;
        overflow: visible;
    }

    .transport-page .transport-form-card.fi-section > .fi-section-header,
    .transport-page .transport-sidebar-card.fi-section > .fi-section-header {
        padding: 16px 20px 0;
        border: 0;
        background: transparent;
    }

    .transport-page .transport-form-card.fi-section > .fi-section-content-ctn,
    .transport-page .transport-sidebar-card.fi-section > .fi-section-content-ctn {
        border: 0;
        background: transparent;
    }

    .transport-page .transport-form-card.fi-section > .fi-section-content-ctn > .fi-section-content,
    .transport-page .transport-sidebar-card.fi-section > .fi-section-content-ctn > .fi-section-content {
        padding: 12px 20px 20px;
    }

    .transport-page .transport-form-card .fi-section-header-heading,
    .transport-page .transport-sidebar-card .fi-section-header-heading {
        font-size: 15px;
        font-weight: 500;
        color: var(--transport-text);
    }

    .transport-page .transport-form-card .fi-section-header-description,
    .transport-page .transport-sidebar-card .fi-section-header-description {
        color: var(--transport-text-secondary);
        font-size: var(--transport-control-font);
        font-weight: 400;
    }

    /* ===== Fieldsety — bez „ramek w ramkach”, tylko separator ===== */
    .transport-page-root .fi-fieldset {
        border: 0 !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 0 12px !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    .transport-page-root .fi-fieldset > .fi-fieldset-header,
    .transport-page-root .fi-fieldset > legend,
    .transport-page-root .fi-fieldset-legend {
        padding: 0 0 8px !important;
        margin: 0 !important;
        font-size: var(--transport-label-font) !important;
        font-weight: 500 !important;
        color: var(--transport-text-secondary) !important;
        background: transparent !important;
        border: 0 !important;
    }

    .transport-page-root .transport-day-routes-fieldset.fi-fieldset {
        margin-top: 4px !important;
        padding-top: 12px !important;
        border-top: 1px solid var(--transport-border) !important;
    }

    /* ===== Trasy dzienne ===== */
    .transport-page .transport-route-row.fi-section {
        border: 1px solid var(--transport-border);
        border-radius: var(--transport-control-radius);
        background: var(--transport-muted-card);
        box-shadow: none;
        margin-bottom: 8px;
        overflow: hidden;
    }

    .transport-page .transport-route-row.fi-section > .fi-section-header {
        padding: 10px 12px;
        min-height: 0;
        background: transparent;
    }

    .transport-page .transport-route-row.fi-section > .fi-section-content-ctn {
        border-top: 1px solid var(--transport-border);
        background: transparent;
    }

    .transport-page .transport-route-row.fi-section > .fi-section-content-ctn > .fi-section-content {
        padding: 10px 12px 12px;
    }

    .transport-page .transport-route-row.fi-section .fi-section-header-heading {
        font-size: var(--transport-control-font);
        font-weight: 500;
        color: var(--transport-text-secondary);
    }

    .transport-sidebar-empty {
        border: 1px dashed var(--transport-border);
        border-radius: var(--transport-control-radius);
        background: var(--transport-muted-card);
        padding: 12px 14px;
        font-size: var(--transport-control-font);
        color: var(--transport-text-secondary);
        line-height: 1.45;
    }

    /* ===== Etykiety — jedna wysokość w rzędzie ===== */
    .transport-page-root .fi-fo-field-wrp {
        gap: 0 !important;
    }

    .transport-page-root .fi-fo-field-wrp-label {
        margin-bottom: 4px !important;
        min-height: 1.15rem;
    }

    .transport-page-root .fi-fo-field-wrp-label > span,
    .transport-page-root .fi-fo-field-wrp-label label {
        font-size: var(--transport-label-font) !important;
        font-weight: 500 !important;
        color: var(--transport-text-secondary) !important;
        letter-spacing: 0 !important;
        line-height: 1.2 !important;
    }

    .transport-page-root .fi-fo-field-wrp-helper-text {
        margin-top: 4px !important;
        font-size: var(--transport-label-font) !important;
        line-height: 1.35 !important;
        color: var(--transport-text-muted) !important;
        font-weight: 400 !important;
    }

    /* Siatka pól: wyrównanie do góry, żeby kontrolki startowały w tej samej linii */
    .transport-page-root .fi-fo-component-ctn,
    .transport-page-root .fi-grid {
        align-items: start !important;
    }

    /* ===== Kontrolki — JEDEN styl (mockup: radius 8, border #D3D1C7, h≈38) ===== */
    .transport-page-root :is(
        .fi-input-wrp,
        .fi-select-input,
        .fi-fo-select .fi-input-wrp,
        .fi-fo-date-time-picker .fi-input-wrp,
        .choices__inner,
        .ts-control
    ) {
        min-height: var(--transport-control-height) !important;
        height: var(--transport-control-height) !important;
        border: 1px solid var(--transport-control-border) !important;
        border-radius: var(--transport-control-radius) !important;
        background: #FFFFFF !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
        /* Nie overflow:hidden — ucina dropdown searchable Select. */
        overflow: visible;
    }

    /* Select searchable: bez sztywnej wysokości (lista/opcje muszą się otworzyć). */
    .transport-page-root .fi-fo-select .fi-input-wrp,
    .transport-page-root .fi-fo-select .choices,
    .transport-page-root .fi-fo-select .choices__inner {
        height: auto !important;
        min-height: var(--transport-control-height) !important;
        overflow: visible !important;
    }

    .transport-page-root .fi-fo-select,
    .transport-page-root .fi-fo-select .fi-input-wrp,
    .transport-page-root .fi-fo-select .choices {
        position: relative;
        z-index: 20;
    }

    .transport-page-root .fi-fo-select .choices.is-open,
    .transport-page-root .fi-fo-select .choices.is-focused {
        z-index: 60;
    }

    .transport-page-root .fi-fo-select .choices__list--dropdown,
    .transport-page-root .fi-fo-select .choices__list[aria-expanded="true"] {
        z-index: 70 !important;
    }

    .transport-page-root :is(
        input.fi-input,
        select.fi-select-input,
        .fi-input-wrp input,
        .fi-input-wrp select,
        .fi-fo-date-time-picker input,
        .fi-main input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="hidden"]),
        .fi-main select
    ) {
        min-height: calc(var(--transport-control-height) - 2px) !important;
        height: calc(var(--transport-control-height) - 2px) !important;
        border-radius: var(--transport-control-radius) !important;
        border-color: var(--transport-control-border) !important;
        background: #FFFFFF !important;
        color: var(--transport-text) !important;
        font-size: var(--transport-control-font) !important;
        font-weight: 400 !important;
        line-height: 1.25 !important;
        padding: 8px 10px !important;
        box-shadow: none !important;
    }

    /* Gdy input siedzi w wrapperze — bez podwójnej ramki / podwójnego radiusa */
    .transport-page-root .fi-input-wrp :is(input.fi-input, select, .fi-input) {
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
        min-height: 100% !important;
        height: 100% !important;
    }

    .transport-page-root textarea.fi-textarea,
    .transport-page-root .fi-main textarea,
    .transport-page-root .fi-input-wrp textarea {
        min-height: 72px !important;
        height: auto !important;
        border: 1px solid var(--transport-control-border) !important;
        border-radius: var(--transport-control-radius) !important;
        background: #FFFFFF !important;
        color: var(--transport-text) !important;
        font-size: var(--transport-control-font) !important;
        font-weight: 400 !important;
        padding: 8px 10px !important;
        box-shadow: none !important;
        line-height: 1.4 !important;
    }

    .transport-page-root .fi-input-wrp:has(textarea) {
        height: auto !important;
        min-height: 72px !important;
    }

    .transport-page-root :is(
        .fi-input-wrp,
        .fi-select-input,
        input.fi-input,
        textarea.fi-textarea,
        select,
        .choices__inner,
        .ts-control
    ):focus,
    .transport-page-root :is(
        .fi-input-wrp,
        .fi-select-input,
        .choices__inner,
        .ts-control
    ):focus-within {
        outline: none !important;
        border-color: #378ADD !important;
        box-shadow: 0 0 0 2px rgba(55, 138, 221, 0.15) !important;
    }

    /* Suffix / prefix (np. PLN) — spójna wysokość */
    .transport-page-root .fi-input-wrp .fi-input-wrp-suffix,
    .transport-page-root .fi-input-wrp .fi-input-wrp-prefix {
        font-size: var(--transport-label-font) !important;
        color: var(--transport-text-secondary) !important;
        font-weight: 400 !important;
    }

    /* Przyciski akcji w formularzu / sidebarze */
    .transport-page-root .fi-btn,
    .transport-page-root .fi-ac-btn-action {
        border-radius: var(--transport-control-radius) !important;
        font-size: var(--transport-control-font) !important;
        font-weight: 500 !important;
        min-height: 34px;
    }

    /* Toggle — kompaktowy, w jednej linii z etykietą */
    .transport-page-root .fi-fo-toggle {
        align-items: center;
    }

    /* Checkbox „Szukaj we wszystkich” — kompaktowy, nie rozpycha rzędu */
    .transport-page-root .fi-fo-checkbox {
        margin-bottom: 6px;
    }

    .transport-page-root .fi-fo-checkbox .fi-checkbox-input {
        border-radius: 4px !important;
    }

    /* TipTap / rich text — ten sam border radius */
    .transport-page-root .fi-fo-rich-editor,
    .transport-page-root .tiptap-editor,
    .transport-page-root .ProseMirror {
        border-radius: var(--transport-control-radius) !important;
    }

    .transport-page-root .tiptap-editor,
    .transport-page-root .fi-fo-rich-editor > div {
        border: 1px solid var(--transport-control-border) !important;
        border-radius: var(--transport-control-radius) !important;
        overflow: hidden;
        background: #FFFFFF;
    }

    /* Info box kosztu */
    .transport-page-root .transport-info-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        border-radius: var(--transport-control-radius);
        border: 0;
        background: var(--transport-accent-bg);
        color: var(--transport-accent-text);
        font-size: var(--transport-control-font);
        line-height: 1.4;
        margin: 4px 0 10px;
    }

    .transport-page-root .transport-info-box b {
        font-weight: 500;
    }

    .transport-page-root .transport-info-box span:last-child {
        font-size: 15px;
        font-weight: 500;
        white-space: nowrap;
    }

    .transport-page-root .transport-info-box--warning {
        background: #FAF3DC;
        color: #633806;
    }

    /* Sidebar: kompaktowa karta Autokar / przewoźnik / kierowca */
    .transport-page .transport-carrier-card.fi-section > .fi-section-content-ctn > .fi-section-content {
        padding: 10px 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .transport-page .transport-carrier-card .fi-section-header-description {
        font-size: var(--transport-label-font);
    }

    .transport-page .transport-carrier-card .fi-fo-field-wrp {
        margin-bottom: 0 !important;
    }

    .transport-page .transport-carrier-card .transport-info-box {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .transport-page .transport-carrier-card .transport-info-box span:last-child {
        white-space: normal;
        font-size: 14px;
    }

    .transport-page .transport-carrier-card .transport-contractor-preview {
        margin-top: 2px;
    }

    .transport-page .transport-carrier-card .fi-ac,
    .transport-page .transport-carrier-card .fi-actions {
        flex-wrap: wrap;
        gap: 6px;
    }

    .transport-page .transport-carrier-card .fi-btn {
        font-size: var(--transport-label-font) !important;
        min-height: 32px;
        padding-inline: 10px !important;
    }

    /* Podgląd kontrahenta w sidebarze */
    .transport-page-root .transport-contractor-preview {
        border-radius: var(--transport-control-radius) !important;
        border-color: var(--transport-border) !important;
        background: var(--transport-muted-card) !important;
    }

    /* Panel finansów (Livewire) — bez szarej karty Filamenta */
    .transport-page-root .transport-finance-shell {
        background: transparent;
        border: 0;
        box-shadow: none;
        padding: 0;
        border-radius: 0;
    }

    @media (max-width: 720px) {
        .transport-status-bar {
            padding: 16px;
        }

        .transport-status-title .transport-status-icon {
            width: 40px;
            height: 40px;
            font-size: 24px;
        }

        .transport-status-title p.transport-status-main {
            font-size: 18px;
        }

        .transport-status-title p.transport-status-sub {
            max-width: 100%;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
        }

        .transport-badges {
            justify-content: flex-start;
            width: 100%;
        }

        .transport-transport-form-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>
