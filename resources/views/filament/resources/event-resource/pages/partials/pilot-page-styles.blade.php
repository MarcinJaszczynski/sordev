<style>
    /*
     * Pilot — ta sama paleta co Transport / Hotele / mockup-reference-pilot.html
     */
    .pilot-page-root {
        --pilot-bg: #F5F4EF;
        --pilot-card: #FFFFFF;
        --pilot-muted-card: #F1EFE8;
        --pilot-border: #E5E3DA;
        --pilot-control-border: #D3D1C7;
        --pilot-text: #2C2C2A;
        --pilot-text-secondary: #5F5E5A;
        --pilot-text-muted: #888780;
        --pilot-accent-bg: #E6F1FB;
        --pilot-accent-text: #0C447C;
        --pilot-danger-bg: #FCEBEB;
        --pilot-danger-text: #791F1F;
        --pilot-success-bg: #EAF3DE;
        --pilot-success-text: #27500A;
        --pilot-control-radius: 8px;
        --pilot-card-radius: 12px;
        --pilot-control-font: var(--admin-input-size);
        --pilot-label-font: var(--admin-label-size);
        --pilot-helper-font: var(--admin-helper-size);
        --pilot-heading-font: var(--admin-heading-size);
        color: var(--pilot-text);
        font-size: var(--admin-body-size);
    }

    .pilot-page-root:not([data-tab="briefing"]) .pilot-form-tab--briefing,
    .pilot-page-root:not([data-tab="cash"]) .pilot-form-tab--cash {
        display: none !important;
    }

    .pilot-status-bar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 12px 14px;
        background: var(--pilot-muted-card);
        border-radius: var(--pilot-card-radius);
        flex-wrap: wrap;
        gap: 10px 14px;
        margin-bottom: 10px;
    }

    .pilot-status-bar--rich {
        align-items: center;
    }

    .pilot-status-main-col {
        min-width: 0;
        flex: 1 1 280px;
    }

    .pilot-status-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .pilot-status-icon {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: var(--pilot-card);
        border: 1px solid var(--pilot-border);
        font-size: 18px;
        flex-shrink: 0;
    }

    .pilot-status-main {
        margin: 0;
        font-size: var(--pilot-heading-font);
        font-weight: 600;
        line-height: 1.3;
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 8px;
    }

    .pilot-contractor-link {
        font-size: var(--pilot-label-font);
        font-weight: 500;
        color: var(--pilot-accent-text);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .pilot-contractor-link:hover {
        text-decoration-thickness: 2px;
    }

    .pilot-status-sub {
        margin: 2px 0 0;
        font-size: var(--pilot-control-font);
        color: var(--pilot-text-secondary);
        line-height: 1.35;
    }

    .pilot-status-meta {
        margin: 2px 0 0;
        font-size: var(--pilot-helper-font);
        color: var(--pilot-text-muted);
    }

    .pilot-status-sep {
        margin: 0 2px;
        color: var(--pilot-text-muted);
    }

    .pilot-status-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        flex: 1 1 320px;
        justify-content: flex-end;
    }

    .pilot-status-actions .pilot-portal-toolbar--compact {
        width: 100%;
        display: flex;
        justify-content: flex-end;
    }

    .pilot-modules-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 12px;
        padding: 8px 12px;
        background: var(--pilot-muted-card);
        border-radius: var(--pilot-control-radius);
        border: 1px solid var(--pilot-border);
    }

    .pilot-modules-label {
        font-size: var(--pilot-label-font);
        font-weight: 500;
        color: var(--pilot-text-secondary);
    }

    .pilot-cash-layout-side {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .pilot-cash-layout-side .space-y-4 > * + * {
        margin-top: 0.75rem;
    }

    .pilot-badge {
        display: inline-flex;
        align-items: center;
        font-size: var(--pilot-label-font);
        padding: 4px 10px;
        border-radius: 8px;
        font-weight: 500;
    }

    .pilot-badge--success {
        background: var(--pilot-success-bg);
        color: var(--pilot-success-text);
    }

    .pilot-badge--muted {
        background: var(--pilot-card);
        color: var(--pilot-text-secondary);
        border: 1px solid var(--pilot-border);
    }

    .pilot-status-link {
        appearance: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: var(--pilot-label-font);
        padding: 5px 10px;
        border-radius: 8px;
        border: 1px solid var(--pilot-control-border);
        background: var(--pilot-card);
        color: var(--pilot-text);
        text-decoration: none;
        cursor: pointer;
    }

    .pilot-status-link:hover {
        background: var(--pilot-muted-card);
    }

    .pilot-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }

    @media (max-width: 900px) {
        .pilot-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    .pilot-stat-card {
        background: var(--pilot-muted-card);
        border-radius: var(--pilot-control-radius);
        padding: 10px 12px;
    }

    .pilot-stat-card--danger {
        background: var(--pilot-danger-bg);
    }

    .pilot-stat-label {
        margin: 0 0 2px;
        font-size: var(--pilot-helper-font);
        color: var(--pilot-text-secondary);
    }

    .pilot-stat-card--danger .pilot-stat-label {
        color: var(--pilot-danger-text);
    }

    .pilot-stat-value {
        margin: 0;
        font-size: var(--pilot-heading-font);
        font-weight: 600;
        line-height: 1.25;
    }

    .pilot-stat-card--danger .pilot-stat-value {
        color: var(--pilot-danger-text);
    }

    .pilot-subtabs {
        display: flex;
        gap: 6px;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--pilot-border);
        overflow-x: auto;
    }

    .pilot-subtab {
        appearance: none;
        background: transparent;
        border: 0;
        border-bottom: 2px solid transparent;
        padding: 8px 14px;
        font-size: var(--pilot-control-font);
        font-weight: 500;
        color: var(--pilot-text-secondary);
        cursor: pointer;
        white-space: nowrap;
    }

    .pilot-subtab:hover {
        color: var(--pilot-text);
    }

    .pilot-subtab.is-active {
        color: var(--pilot-accent-text);
        border-bottom-color: var(--pilot-accent-text);
    }

    .pilot-card {
        background: var(--pilot-card);
        border: 1px solid var(--pilot-border);
        border-radius: var(--pilot-card-radius);
        padding: 14px 16px;
        margin-bottom: 12px;
    }

    .pilot-card--tight {
        padding: 12px 14px;
        margin-bottom: 0;
    }

    .pilot-card--muted {
        background: var(--pilot-muted-card);
    }

    .pilot-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }

    .pilot-card-title {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }

    .pilot-card-meta {
        font-size: var(--pilot-label-font);
        color: var(--pilot-text-secondary);
    }

    .pilot-cash-flow {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 4px;
        text-align: center;
    }

    @media (max-width: 720px) {
        .pilot-cash-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    .pilot-cash-flow-step {
        background: var(--pilot-muted-card);
        border-radius: var(--pilot-control-radius);
        padding: 8px 6px;
    }

    .pilot-cash-flow-step--danger {
        background: var(--pilot-danger-bg);
    }

    .pilot-cash-flow-label {
        margin: 0 0 2px;
        font-size: var(--pilot-helper-font);
        color: var(--pilot-text-muted);
    }

    .pilot-cash-flow-step--danger .pilot-cash-flow-label {
        color: var(--pilot-danger-text);
    }

    .pilot-cash-flow-value {
        margin: 0;
        font-size: var(--pilot-control-font);
        font-weight: 600;
        line-height: 1.3;
    }

    .pilot-cash-flow-step--danger .pilot-cash-flow-value {
        color: var(--pilot-danger-text);
    }

    .pilot-cash-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(260px, 0.8fr);
        gap: 12px;
        align-items: start;
    }

    @media (max-width: 1100px) {
        .pilot-cash-layout { grid-template-columns: 1fr; }
    }

    .pilot-step-heading {
        margin: 0 0 8px;
        font-size: var(--pilot-control-font);
        font-weight: 600;
        color: var(--pilot-accent-text);
    }

    .pilot-accordion {
        margin-top: 4px;
    }

    .pilot-accordion > summary {
        cursor: pointer;
        font-size: var(--pilot-control-font);
        color: var(--pilot-accent-text);
        font-weight: 500;
        list-style: none;
        padding: 4px 0;
    }

    .pilot-accordion > summary::-webkit-details-marker {
        display: none;
    }

    .pilot-accordion[open] > summary {
        margin-bottom: 10px;
    }

    .pilot-sticky-actions {
        position: sticky;
        bottom: 0;
        z-index: 20;
        margin-top: 12px;
        padding: 10px 0 4px;
        background: linear-gradient(to top, var(--pilot-bg) 70%, transparent);
    }

    .pilot-page-root .fi-fo-component-ctn,
    .pilot-page-root .fi-section {
        border-radius: var(--pilot-card-radius);
    }

    .pilot-page-root .fi-section {
        background: var(--pilot-card);
        border: 1px solid var(--pilot-border);
        box-shadow: none;
    }

    .pilot-page-root .fi-section + .fi-section {
        margin-top: 10px;
    }

    .pilot-panel-livewire .fi-section,
    .pilot-panel-livewire > .rounded-xl {
        border-color: var(--pilot-border) !important;
        border-radius: var(--pilot-card-radius) !important;
    }

    /* Zagęszczenie cash-desk w kolumnie rozliczenia */
    .pilot-cash-layout-main .space-y-4 > * + * {
        margin-top: 0.75rem;
    }

    .pilot-cash-layout-main section h3,
    .pilot-cash-layout-main .text-base.font-semibold {
        font-size: 0.875rem !important;
    }
</style>

