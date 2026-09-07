<style>
    /*
     * Hotele — ta sama paleta co Transport / mockup-reference-hotele.html
     */
    .hotel-page-root {
        --hotel-bg: #F5F4EF;
        --hotel-card: #FFFFFF;
        --hotel-muted-card: #F1EFE8;
        --hotel-border: #E5E3DA;
        --hotel-control-border: #D3D1C7;
        --hotel-text: #2C2C2A;
        --hotel-text-secondary: #5F5E5A;
        --hotel-text-muted: #888780;
        --hotel-accent-bg: #E6F1FB;
        --hotel-accent-text: #0C447C;
        --hotel-danger-bg: #FCEBEB;
        --hotel-danger-text: #791F1F;
        --hotel-success-bg: #EAF3DE;
        --hotel-success-text: #27500A;
        --hotel-control-radius: 8px;
        --hotel-card-radius: 12px;
        --hotel-control-font: var(--admin-input-size);
        --hotel-label-font: var(--admin-label-size);
        --hotel-helper-font: var(--admin-helper-size);
        --hotel-heading-font: var(--admin-heading-size);
        color: var(--hotel-text);
        font-size: var(--admin-body-size);
    }

    .hotel-status-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        background: var(--hotel-muted-card);
        border-radius: var(--hotel-card-radius);
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 12px;
    }

    .hotel-status-bar--stacked {
        flex-direction: column;
        align-items: stretch;
    }

    .hotel-status-bar--compact {
        flex-direction: row;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px 12px;
        padding: 10px 12px;
    }

    .hotel-status-inline-meta {
        font-weight: 400;
        font-size: var(--hotel-control-font);
        color: var(--hotel-text-secondary);
    }

    .hotel-night-chips {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 8px;
    }

    .hotel-night-chips--inline {
        display: flex;
        flex: 1 1 auto;
        flex-wrap: wrap;
        gap: 6px;
        min-width: 0;
    }

    .hotel-night-chip {
        background: var(--hotel-card);
        border: 1px solid var(--hotel-border);
        border-radius: var(--hotel-control-radius);
        padding: 10px 12px;
        min-width: 0;
    }

    .hotel-night-chip--inline {
        display: inline-flex;
        align-items: baseline;
        gap: 6px;
        padding: 4px 8px;
        max-width: 100%;
        white-space: nowrap;
    }

    .hotel-night-chip--inline .hotel-night-chip-name,
    .hotel-night-chip--inline .hotel-night-chip-link,
    .hotel-night-chip--inline .hotel-night-chip-meta {
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hotel-night-chip--inline .hotel-night-chip-link,
    .hotel-night-chip--inline .hotel-night-chip-name {
        font-size: var(--hotel-label-font);
        font-weight: 600;
        max-width: 160px;
    }

    .hotel-night-chip--inline .hotel-night-chip-meta {
        font-size: var(--hotel-helper-font);
        max-width: 120px;
    }

    .hotel-status-bar--compact .hotel-status-main {
        font-size: 14px;
    }

    .hotel-status-bar--compact .hotel-status-icon {
        width: 28px;
        height: 28px;
        font-size: 14px;
    }

    .hotel-night-chip-day {
        margin: 0;
        font-size: var(--hotel-label-font);
        font-weight: 600;
        color: var(--hotel-text-secondary);
        flex-shrink: 0;
    }

    .hotel-night-chip-name {
        margin: 4px 0 0;
        font-size: var(--hotel-control-font);
        font-weight: 600;
        line-height: 1.3;
    }

    .hotel-night-chip-link {
        color: var(--hotel-accent-text);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .hotel-night-chip-link:hover {
        text-decoration-thickness: 2px;
    }

    .hotel-night-chip-meta {
        margin: 2px 0 0;
        font-size: var(--hotel-label-font);
        color: var(--hotel-text-muted);
        line-height: 1.35;
        word-break: break-word;
    }

    .hotel-stat-value--money {
        font-size: var(--hotel-heading-font);
    }

    .hotel-services-columns {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        align-items: start;
    }

    @media (max-width: 1200px) {
        .hotel-services-columns { grid-template-columns: 1fr; }
    }

    .hotel-card--flush {
        padding-bottom: 8px;
    }

    .hotel-services-col .fi-resource-relation-manager {
        gap: 0.75rem;
    }

    .hotel-status-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .hotel-status-icon {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: var(--hotel-card);
        border: 1px solid var(--hotel-border);
        font-size: 18px;
        flex-shrink: 0;
    }

    .hotel-status-main {
        margin: 0;
        font-size: var(--hotel-heading-font);
        font-weight: 600;
        line-height: 1.3;
    }

    .hotel-status-sub {
        margin: 2px 0 0;
        font-size: var(--hotel-control-font);
        color: var(--hotel-text-secondary);
    }

    .hotel-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    @media (max-width: 900px) {
        .hotel-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    .hotel-stat-card {
        background: var(--hotel-muted-card);
        border-radius: var(--hotel-control-radius);
        padding: 12px;
    }

    .hotel-stat-card--danger {
        background: var(--hotel-danger-bg);
    }

    .hotel-stat-label {
        margin: 0 0 4px;
        font-size: var(--hotel-label-font);
        color: var(--hotel-text-secondary);
    }

    .hotel-stat-card--danger .hotel-stat-label {
        color: var(--hotel-danger-text);
    }

    .hotel-stat-value {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        line-height: 1.2;
    }

    .hotel-stat-card--danger .hotel-stat-value {
        color: var(--hotel-danger-text);
    }

    .hotel-subtabs {
        display: flex;
        gap: 6px;
        margin-bottom: 14px;
        border-bottom: 1px solid var(--hotel-border);
        overflow-x: auto;
    }

    .hotel-subtab {
        appearance: none;
        background: transparent;
        border: 0;
        border-bottom: 2px solid transparent;
        padding: 8px 14px;
        font-size: var(--hotel-control-font);
        font-weight: 500;
        color: var(--hotel-text-secondary);
        cursor: pointer;
        white-space: nowrap;
    }

    .hotel-subtab:hover {
        color: var(--hotel-text);
    }

    .hotel-subtab.is-active {
        color: var(--hotel-accent-text);
        border-bottom-color: var(--hotel-accent-text);
    }

    .hotel-card {
        background: var(--hotel-card);
        border: 1px solid var(--hotel-border);
        border-radius: var(--hotel-card-radius);
        padding: 16px 20px;
        margin-bottom: 14px;
    }

    .hotel-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .hotel-card-title {
        margin: 0;
        font-size: var(--hotel-heading-font);
        font-weight: 600;
    }

    .hotel-overview-table {
        width: 100%;
        font-size: var(--hotel-control-font);
        border-collapse: collapse;
    }

    .hotel-overview-table th {
        font-weight: 500;
        text-align: left;
        color: var(--hotel-text-secondary);
        padding: 6px 4px;
    }

    .hotel-overview-table td {
        padding: 8px 4px;
        border-top: 1px solid var(--hotel-border);
        vertical-align: middle;
    }

    .hotel-overview-table td.is-muted {
        color: var(--hotel-text-muted);
    }

    .hotel-status-pill {
        display: inline-flex;
        border-radius: 8px;
        padding: 2px 8px;
        font-size: var(--hotel-label-font);
        font-weight: 600;
    }

    .hotel-status-pill--success {
        background: var(--hotel-success-bg);
        color: var(--hotel-success-text);
    }

    .hotel-status-pill--warning {
        background: #FAEEDA;
        color: #633806;
    }

    .hotel-status-pill--gray {
        background: var(--hotel-muted-card);
        color: var(--hotel-text-secondary);
    }

    .hotel-status-pill--danger {
        background: var(--hotel-danger-bg);
        color: var(--hotel-danger-text);
    }

    .hotel-readiness-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 8px;
        padding: 4px 10px;
        font-size: var(--hotel-label-font);
        font-weight: 600;
        white-space: nowrap;
    }

    .hotel-readiness-pill--success {
        background: var(--hotel-success-bg);
        color: var(--hotel-success-text);
    }

    .hotel-readiness-pill--warning {
        background: #FAEEDA;
        color: #633806;
    }

    .hotel-readiness-pill--danger {
        background: var(--hotel-danger-bg);
        color: var(--hotel-danger-text);
    }

    .hotel-readiness-pill--muted {
        background: var(--hotel-muted-card);
        color: var(--hotel-text-secondary);
    }

    .hotel-notes-jump {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 8px;
        padding: 4px 10px;
        font-size: var(--hotel-label-font);
        font-weight: 600;
        white-space: nowrap;
        background: #FAEEDA;
        color: #633806;
        border: 1px solid #E8D4B0;
        cursor: pointer;
        line-height: 1.35;
    }

    .hotel-notes-jump:hover {
        background: #F5E2C4;
    }

    .hotel-planning-grid {
        display: grid;
        grid-template-columns: 200px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
    }

    @media (max-width: 760px) {
        .hotel-planning-grid { grid-template-columns: 1fr; }
    }

    .hotel-night-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .hotel-night-card {
        border-radius: var(--hotel-control-radius);
        padding: 10px 12px;
        background: var(--hotel-muted-card);
        border: 1px solid transparent;
        text-align: left;
        width: 100%;
        cursor: pointer;
        transition: background 120ms ease;
    }

    .hotel-night-card:hover {
        background: #EAE8E0;
    }

    .hotel-night-card.is-active {
        background: var(--hotel-accent-bg);
        border-color: transparent;
    }

    .hotel-night-card-title {
        margin: 0;
        font-size: var(--hotel-control-font);
        font-weight: 600;
        color: var(--hotel-text);
    }

    .hotel-night-card.is-active .hotel-night-card-title {
        color: var(--hotel-accent-text);
    }

    .hotel-night-card-sub {
        margin: 2px 0 0;
        font-size: var(--hotel-label-font);
        color: var(--hotel-text-secondary);
        line-height: 1.35;
    }

    .hotel-night-card.is-active .hotel-night-card-sub {
        color: var(--hotel-accent-text);
    }

    .hotel-night-card.is-deficient {
        border-color: var(--hotel-danger-text);
        background: var(--hotel-danger-bg);
    }

    .hotel-night-card.is-deficient.is-active {
        border-color: var(--hotel-danger-text);
        background: var(--hotel-danger-bg);
    }

    .hotel-night-card.is-deficient .hotel-night-card-title {
        color: var(--hotel-danger-text);
    }

    .hotel-night-card.is-deficient.is-active .hotel-night-card-sub {
        color: var(--hotel-danger-text);
    }

    .hotel-night-card.is-empty .hotel-night-card-sub {
        color: var(--hotel-text-muted);
    }

    .hotel-step-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }

    .hotel-step-chip {
        border-radius: var(--hotel-control-radius);
        padding: 5px 10px;
        font-size: var(--hotel-label-font);
        font-weight: 500;
        border: 0;
        background: var(--hotel-muted-card);
        color: var(--hotel-text-secondary);
        cursor: pointer;
    }

    .hotel-step-chip.is-active {
        background: var(--hotel-accent-bg);
        color: var(--hotel-accent-text);
    }

    .hotel-sticky-actions {
        position: sticky;
        bottom: 0;
        z-index: 10;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        border-radius: var(--hotel-card-radius);
        border: 1px solid var(--hotel-border);
        background: var(--hotel-muted-card);
        padding: 12px 16px;
        margin-top: 14px;
    }

    .hotel-sticky-actions .hotel-sticky-actions-primary {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-left: auto;
    }

    .hotel-copy-bar {
        background: var(--hotel-muted-card);
        border-radius: var(--hotel-card-radius);
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .hotel-services-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    @media (max-width: 900px) {
        .hotel-services-grid { grid-template-columns: 1fr; }
    }

    .hotel-service-summary {
        background: var(--hotel-muted-card);
        border-radius: var(--hotel-control-radius);
        padding: 10px 12px;
    }

    .hotel-service-summary-title {
        margin: 0 0 4px;
        font-size: var(--hotel-control-font);
        font-weight: 600;
    }

    .hotel-service-summary-meta {
        margin: 0;
        font-size: var(--hotel-label-font);
        color: var(--hotel-text-muted);
    }

    .hotel-accordion {
        border-top: 1px solid var(--hotel-border);
        margin-top: 12px;
        padding-top: 12px;
    }

    .hotel-accordion > summary {
        cursor: pointer;
        font-size: var(--hotel-control-font);
        font-weight: 600;
        color: var(--hotel-accent-text);
        list-style: none;
    }

    .hotel-accordion > summary::-webkit-details-marker {
        display: none;
    }

    .hotel-page-root .fi-section {
        background: var(--hotel-card);
        border: 1px solid var(--hotel-border);
        border-radius: var(--hotel-card-radius);
        box-shadow: none;
    }

    .hotel-page-root .fi-btn {
        border-radius: var(--hotel-control-radius) !important;
        font-size: var(--hotel-control-font) !important;
        font-weight: 500 !important;
        min-height: 34px;
    }
</style>
