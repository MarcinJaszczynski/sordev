<style>
    /*
     * Kontrahent — ta sama paleta co Pilot / Transport / Hotele
     */
    .contractor-page-root {
        --contractor-bg: #F5F4EF;
        --contractor-card: #FFFFFF;
        --contractor-muted-card: #F1EFE8;
        --contractor-border: #E5E3DA;
        --contractor-control-border: #D3D1C7;
        --contractor-text: #2C2C2A;
        --contractor-text-secondary: #5F5E5A;
        --contractor-text-muted: #888780;
        --contractor-accent-bg: #E6F1FB;
        --contractor-accent-text: #0C447C;
        --contractor-danger-bg: #FCEBEB;
        --contractor-danger-text: #791F1F;
        --contractor-success-bg: #EAF3DE;
        --contractor-success-text: #27500A;
        --contractor-warning-bg: #FAEEDA;
        --contractor-warning-text: #633806;
        --contractor-control-radius: 8px;
        --contractor-card-radius: 12px;
        --contractor-control-font: var(--admin-input-size);
        --contractor-label-font: var(--admin-label-size);
        --contractor-helper-font: var(--admin-helper-size);
        --contractor-heading-font: var(--admin-heading-size);
        color: var(--contractor-text);
        font-size: var(--admin-body-size);
    }

    .contractor-status-bar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 12px 14px;
        background: var(--contractor-muted-card);
        border-radius: var(--contractor-card-radius);
        flex-wrap: wrap;
        gap: 10px 14px;
        margin-bottom: 10px;
    }

    .contractor-status-main-col {
        min-width: 0;
        flex: 1 1 280px;
    }

    .contractor-status-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .contractor-status-icon {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: var(--contractor-card);
        border: 1px solid var(--contractor-border);
        font-size: 18px;
        flex-shrink: 0;
    }

    .contractor-status-main {
        margin: 0;
        font-size: var(--contractor-heading-font);
        font-weight: 600;
        line-height: 1.3;
    }

    .contractor-status-sub {
        margin: 2px 0 0;
        font-size: var(--contractor-control-font);
        color: var(--contractor-text-secondary);
        line-height: 1.35;
    }

    .contractor-status-sep {
        margin: 0 2px;
        color: var(--contractor-text-muted);
    }

    .contractor-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        justify-content: flex-end;
    }

    .contractor-badge {
        display: inline-flex;
        align-items: center;
        font-size: var(--contractor-label-font);
        padding: 4px 10px;
        border-radius: 8px;
        font-weight: 500;
    }

    .contractor-badge--success {
        background: var(--contractor-success-bg);
        color: var(--contractor-success-text);
    }

    .contractor-badge--warning {
        background: var(--contractor-warning-bg);
        color: var(--contractor-warning-text);
    }

    .contractor-badge--danger {
        background: var(--contractor-danger-bg);
        color: var(--contractor-danger-text);
    }

    .contractor-badge--accent {
        background: var(--contractor-accent-bg);
        color: var(--contractor-accent-text);
    }

    .contractor-badge--muted {
        background: var(--contractor-card);
        color: var(--contractor-text-secondary);
        border: 1px solid var(--contractor-border);
    }

    .contractor-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }

    @media (max-width: 900px) {
        .contractor-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    .contractor-stat-card {
        background: var(--contractor-muted-card);
        border-radius: var(--contractor-control-radius);
        padding: 10px 12px;
    }

    .contractor-stat-card--danger {
        background: var(--contractor-danger-bg);
    }

    .contractor-stat-label {
        margin: 0 0 2px;
        font-size: var(--contractor-helper-font);
        color: var(--contractor-text-secondary);
    }

    .contractor-stat-card--danger .contractor-stat-label {
        color: var(--contractor-danger-text);
    }

    .contractor-stat-value {
        margin: 0;
        font-size: var(--contractor-heading-font);
        font-weight: 600;
        line-height: 1.25;
    }

    .contractor-stat-card--danger .contractor-stat-value {
        color: var(--contractor-danger-text);
    }

    .contractor-subtabs {
        display: flex;
        gap: 6px;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--contractor-border);
        overflow-x: auto;
    }

    .contractor-subtab {
        appearance: none;
        background: transparent;
        border: 0;
        border-bottom: 2px solid transparent;
        padding: 8px 14px;
        font-size: var(--contractor-control-font);
        font-weight: 500;
        color: var(--contractor-text-secondary);
        cursor: pointer;
        white-space: nowrap;
    }

    .contractor-subtab:hover {
        color: var(--contractor-text);
    }

    .contractor-subtab.is-active {
        color: var(--contractor-accent-text);
        border-bottom-color: var(--contractor-accent-text);
    }

    .contractor-card {
        background: var(--contractor-card);
        border: 1px solid var(--contractor-border);
        border-radius: var(--contractor-card-radius);
        padding: 14px 16px;
        margin-bottom: 12px;
    }

    .contractor-card--tight {
        padding: 12px 14px;
    }

    .contractor-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 6px;
    }

    .contractor-card-title {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.3;
    }

    .contractor-card-title a {
        color: inherit;
        text-decoration: none;
    }

    .contractor-card-title a:hover {
        color: var(--contractor-accent-text);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .contractor-card-meta {
        margin: 0;
        font-size: var(--contractor-label-font);
        color: var(--contractor-text-secondary);
        line-height: 1.4;
    }

    .contractor-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }

    .contractor-empty {
        padding: 18px 14px;
        text-align: center;
        color: var(--contractor-text-muted);
        font-size: var(--contractor-control-font);
        background: var(--contractor-muted-card);
        border-radius: var(--contractor-control-radius);
        margin-bottom: 12px;
    }

    .contractor-section-label {
        margin: 0 0 8px;
        font-size: var(--contractor-label-font);
        font-weight: 600;
        color: var(--contractor-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .contractor-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 16px;
    }

    .contractor-sticky-actions {
        position: sticky;
        bottom: 0;
        z-index: 10;
        margin-top: 12px;
        padding: 10px 0 4px;
        background: linear-gradient(to top, var(--contractor-card) 70%, transparent);
    }

    .contractor-payment-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
        vertical-align: middle;
    }

    .contractor-payment-dot--green { background: #27500A; }
    .contractor-payment-dot--orange { background: #633806; }
    .contractor-payment-dot--red { background: #791F1F; }
    .contractor-payment-dot--gray { background: #888780; }

    .contractor-stat-card--clickable {
        appearance: none;
        border: 1px solid transparent;
        text-align: left;
        cursor: pointer;
        width: 100%;
        transition: border-color 0.12s ease, box-shadow 0.12s ease;
    }

    .contractor-stat-card--clickable:hover {
        border-color: var(--contractor-control-border);
    }

    .contractor-stat-card--clickable.is-active {
        border-color: var(--contractor-accent-text);
        box-shadow: inset 0 0 0 1px var(--contractor-accent-text);
    }

    .contractor-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px 12px;
        margin-bottom: 12px;
        padding: 10px 12px;
        background: var(--contractor-muted-card);
        border-radius: var(--contractor-card-radius);
        border: 1px solid var(--contractor-border);
    }

    .contractor-toolbar-search {
        flex: 1 1 220px;
        min-width: 180px;
    }

    .contractor-toolbar-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        flex: 2 1 420px;
    }

    .contractor-toolbar-count {
        margin: 0;
        margin-left: auto;
        font-size: var(--contractor-label-font);
        color: var(--contractor-text-muted);
        white-space: nowrap;
    }

    .contractor-input,
    .contractor-select {
        width: 100%;
        appearance: none;
        border: 1px solid var(--contractor-control-border);
        background: var(--contractor-card);
        color: var(--contractor-text);
        border-radius: var(--contractor-control-radius);
        padding: 7px 10px;
        font-size: var(--contractor-control-font);
        line-height: 1.3;
    }

    .contractor-select {
        width: auto;
        min-width: 160px;
        background-image: linear-gradient(45deg, transparent 50%, var(--contractor-text-muted) 50%),
            linear-gradient(135deg, var(--contractor-text-muted) 50%, transparent 50%);
        background-position: calc(100% - 14px) calc(50% - 2px), calc(100% - 9px) calc(50% - 2px);
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
        padding-right: 28px;
    }

    .contractor-filter-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .contractor-filter-chip {
        appearance: none;
        border: 1px solid var(--contractor-border);
        background: var(--contractor-card);
        color: var(--contractor-text-secondary);
        border-radius: 8px;
        padding: 5px 10px;
        font-size: var(--contractor-label-font);
        font-weight: 500;
        cursor: pointer;
    }

    .contractor-filter-chip:hover {
        color: var(--contractor-text);
    }

    .contractor-filter-chip.is-active {
        background: var(--contractor-accent-bg);
        color: var(--contractor-accent-text);
        border-color: transparent;
    }

    .contractor-table-wrap {
        overflow-x: auto;
        border: 1px solid var(--contractor-border);
        border-radius: var(--contractor-card-radius);
        background: var(--contractor-card);
        margin-bottom: 12px;
    }

    .contractor-table {
        width: 100%;
        min-width: 72rem;
        border-collapse: collapse;
        font-size: var(--contractor-control-font);
    }

    .contractor-table th {
        text-align: left;
        padding: 9px 12px;
        font-size: var(--contractor-helper-font);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--contractor-text-secondary);
        background: var(--contractor-muted-card);
        border-bottom: 1px solid var(--contractor-border);
        white-space: nowrap;
    }

    .contractor-table td {
        padding: 10px 12px;
        vertical-align: top;
        border-bottom: 1px solid var(--contractor-border);
        color: var(--contractor-text);
    }

    .contractor-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .contractor-table tbody tr:hover td {
        background: #FAF9F5;
    }

    .contractor-table .text-right {
        text-align: right;
    }

    .contractor-table-title {
        font-weight: 600;
        line-height: 1.3;
    }

    .contractor-table-title a,
    .contractor-table-link {
        color: inherit;
        text-decoration: none;
    }

    .contractor-table-title a:hover,
    .contractor-table-link:hover {
        color: var(--contractor-accent-text);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .contractor-table-sub {
        margin-top: 2px;
        font-size: var(--contractor-helper-font);
        color: var(--contractor-text-muted);
    }

    .contractor-table-muted {
        color: var(--contractor-text-muted);
    }

    .contractor-table-amount {
        font-weight: 600;
        white-space: nowrap;
    }

    .contractor-table-date {
        white-space: nowrap;
        color: var(--contractor-text-secondary);
        font-size: var(--contractor-label-font);
    }

    .contractor-row-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1px solid var(--contractor-control-border);
        background: var(--contractor-muted-card);
        color: var(--contractor-accent-text);
        text-decoration: none;
        font-weight: 600;
    }

    .contractor-row-link:hover {
        background: var(--contractor-accent-bg);
    }

    .contractor-table .contractor-chip-row {
        margin-top: 0;
    }
</style>
