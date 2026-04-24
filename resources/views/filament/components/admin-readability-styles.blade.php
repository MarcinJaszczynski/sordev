<style>
    :root {
        --admin-root-font: 14px;
        --admin-line-height: 1.5;
        --admin-body-size: 0.95rem;
        --admin-input-size: 0.95rem;
        --admin-label-size: 0.9rem;
        --admin-helper-size: 0.82rem;
        --admin-heading-size: 1.08rem;
        --admin-table-cell-size: 0.9rem;
        --admin-table-header-size: 0.78rem;
        --admin-sidebar-label-size: 0.92rem;
        --admin-topbar-size: 0.9rem;
        --admin-table-min-width: 36rem;
        --admin-column-min-width: 18rem;
    }

    @media (min-width: 640px) {
        :root {
            --admin-root-font: 15px;
            --admin-body-size: 0.96rem;
            --admin-input-size: 0.96rem;
            --admin-label-size: 0.91rem;
            --admin-heading-size: 1.12rem;
            --admin-table-cell-size: 0.92rem;
            --admin-table-header-size: 0.8rem;
            --admin-topbar-size: 0.92rem;
            --admin-table-min-width: 40rem;
        }
    }

    @media (min-width: 768px) {
        :root {
            --admin-root-font: 15px;
            --admin-body-size: 0.98rem;
            --admin-input-size: 0.98rem;
            --admin-label-size: 0.94rem;
            --admin-helper-size: 0.84rem;
            --admin-heading-size: 1.16rem;
            --admin-table-cell-size: 0.94rem;
            --admin-table-header-size: 0.82rem;
            --admin-sidebar-label-size: 0.95rem;
            --admin-topbar-size: 0.94rem;
            --admin-table-min-width: 44rem;
            --admin-column-min-width: 19rem;
        }
    }

    @media (min-width: 1280px) {
        :root {
            --admin-root-font: 16px;
            --admin-body-size: 1rem;
            --admin-input-size: 1rem;
            --admin-label-size: 0.96rem;
            --admin-helper-size: 0.86rem;
            --admin-heading-size: 1.22rem;
            --admin-table-cell-size: 0.96rem;
            --admin-table-header-size: 0.84rem;
            --admin-sidebar-label-size: 0.97rem;
            --admin-topbar-size: 0.96rem;
            --admin-table-min-width: 48rem;
            --admin-column-min-width: 20rem;
        }
    }

    @media (min-width: 1536px) {
        :root {
            --admin-root-font: 17px;
            --admin-body-size: 1.02rem;
            --admin-input-size: 1rem;
            --admin-label-size: 0.97rem;
            --admin-heading-size: 1.26rem;
            --admin-table-cell-size: 0.98rem;
            --admin-table-header-size: 0.86rem;
            --admin-table-min-width: 52rem;
        }
    }

    @media (min-width: 2560px) {
        :root {
            --admin-root-font: 18px;
            --admin-body-size: 1.06rem;
            --admin-input-size: 1.04rem;
            --admin-label-size: 1rem;
            --admin-helper-size: 0.9rem;
            --admin-heading-size: 1.34rem;
            --admin-table-cell-size: 1rem;
            --admin-table-header-size: 0.9rem;
            --admin-sidebar-label-size: 1rem;
            --admin-topbar-size: 1rem;
            --admin-table-min-width: 56rem;
            --admin-column-min-width: 22rem;
        }
    }

    @media (min-width: 3840px) {
        :root {
            --admin-root-font: 19px;
            --admin-body-size: 1.08rem;
            --admin-input-size: 1.06rem;
            --admin-heading-size: 1.4rem;
            --admin-table-cell-size: 1.02rem;
            --admin-table-header-size: 0.92rem;
        }
    }

    html {
        font-size: var(--admin-root-font);
    }

    body {
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }

    .fi-layout,
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

    /* Modal window must NOT get max-width:100% — Filament controls its width via props (max-w-2xl etc.) */
    .fi-modal-window {
        min-width: 0;
    }

    .fi-main {
        font-size: var(--admin-body-size);
        line-height: var(--admin-line-height);
        /* overflow-x intentionally omitted: hiding it blocks child table scrollbars */
    }

    .fi-page,
    .fi-page-content {
        /* keep child horizontal scrollers and popovers visible */
        overflow-x: visible;
    }

    .fi-page-content,
    .fi-simple-main-ctn {
        padding-inline: clamp(0.75rem, 2vw, 1.5rem);
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
    .fi-input-wrp,
    .ts-control,
    .choices__inner {
        font-size: var(--admin-input-size);
    }

    .fi-input,
    .fi-select-input,
    .fi-textarea,
    .fi-fo-date-time-picker input,
    .ts-control,
    .choices__inner {
        background-color: rgb(229 231 235);
        border-color: rgb(107 114 128);
        color: rgb(17 24 39);
    }

    .dark .fi-input,
    .dark .fi-select-input,
    .dark .fi-textarea,
    .dark .fi-fo-date-time-picker input,
    .dark .ts-control,
    .dark .choices__inner {
        background-color: rgb(24 24 27);
        border-color: rgb(161 161 170);
        color: rgb(244 244 245);
    }

    .fi-input:focus,
    .fi-select-input:focus,
    .fi-textarea:focus,
    .fi-fo-date-time-picker input:focus,
    .ts-control:focus-within,
    .choices__inner:focus-within {
        border-color: rgb(217 119 6);
        box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.28);
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
    }

    .fi-topbar .custom-topbar-notifications {
        flex: 1 1 auto;
        min-width: 0;
        max-width: 100%;
    }

    .fi-topbar .custom-topbar-notifications .topbar-notifications-items {
        min-width: 0;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .fi-topbar .custom-topbar-notifications .topbar-notifications-items > .relative,
    .fi-topbar .custom-topbar-notifications .topbar-notifications-items > .flex {
        flex: 0 0 auto;
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
    .mkh-kanban-board,
    .f-kanban-root {
        overflow-x: auto;
        overflow-y: visible; /* hidden blocked dropdowns/tooltips inside table cells */
        -webkit-overflow-scrolling: touch;
    }

    .fi-ta-table {
        width: 100%;
        min-width: var(--admin-table-min-width);
        table-layout: auto;
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

    .mkh-kanban-board,
    .f-kanban-root {
        gap: 1rem;
        padding-bottom: 0.25rem;
    }

    .mkh-kanban-board .mkh-column,
    .f-kanban-column {
        min-width: var(--admin-column-min-width);
        max-width: min(24rem, 92vw);
    }

    .admin-table-stack {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        padding: 0.15rem 0;
    }

    .admin-table-stack-compact {
        gap: 0.18rem;
    }

    .admin-table-title {
        font-size: calc(var(--admin-body-size) + 0.08rem);
        font-weight: 800;
        line-height: 1.35;
        color: #111827;
    }

    .admin-table-meta,
    .admin-table-value,
    .admin-table-value-strong {
        font-size: var(--admin-body-size);
        line-height: 1.45;
        color: #374151;
    }

    .admin-table-value-strong {
        font-weight: 700;
    }

    .admin-table-muted-label {
        font-size: calc(var(--admin-helper-size) + 0.02rem);
        line-height: 1.4;
        color: #9ca3af;
    }

    .admin-table-pill {
        display: inline-block;
        width: fit-content;
        padding: 0.18rem 0.6rem;
        border-radius: 9999px;
        font-size: calc(var(--admin-helper-size) + 0.06rem);
        font-weight: 700;
        line-height: 1.35;
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

        .mkh-kanban-board .mkh-column,
        .f-kanban-column {
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
</style>
