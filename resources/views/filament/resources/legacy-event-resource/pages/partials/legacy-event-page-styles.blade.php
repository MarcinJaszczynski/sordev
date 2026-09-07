<style>
    .legacy-event-page-root {
        --legacy-bg: #F5F4EF;
        --legacy-card: #FFFFFF;
        --legacy-muted-card: #F1EFE8;
        --legacy-border: #E5E3DA;
        --legacy-text: #2C2C2A;
        --legacy-text-secondary: #5F5E5A;
        --legacy-text-muted: #888780;
        --legacy-accent-bg: #E6F1FB;
        --legacy-accent-text: #0C447C;
        --legacy-danger-bg: #FCEBEB;
        --legacy-danger-text: #791F1F;
        --legacy-success-bg: #EAF3DE;
        --legacy-success-text: #27500A;
        --legacy-warning-bg: #FAEEDA;
        --legacy-warning-text: #633806;
        --legacy-radius: 12px;
        color: var(--legacy-text);
        /* Ujednolicony odstęp jak na karcie kontrahenta — nie „przyklejone” do headera */
        padding-top: 0.25rem;
        margin-top: 0.15rem;
    }

    .legacy-event-page-root .fi-section {
        margin-top: 0.65rem;
    }

    .legacy-event-page-root .fi-section:first-of-type {
        margin-top: 0.35rem;
    }

    .legacy-status-bar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 12px 14px;
        background: var(--legacy-muted-card);
        border-radius: var(--legacy-radius);
        flex-wrap: wrap;
        gap: 10px 14px;
        margin-bottom: 10px;
        border: 1px solid var(--legacy-border);
    }

    .legacy-status-main {
        margin: 0;
        font-size: var(--admin-heading-size, 1.05rem);
        font-weight: 600;
        line-height: 1.3;
    }

    .legacy-status-sub {
        margin: 2px 0 0;
        font-size: var(--admin-input-size, 0.875rem);
        color: var(--legacy-text-secondary);
        line-height: 1.35;
    }

    .legacy-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        justify-content: flex-end;
    }

    .legacy-badge {
        display: inline-flex;
        align-items: center;
        font-size: var(--admin-label-size, 0.75rem);
        padding: 4px 10px;
        border-radius: 8px;
        font-weight: 500;
    }

    .legacy-badge--success { background: var(--legacy-success-bg); color: var(--legacy-success-text); }
    .legacy-badge--danger { background: var(--legacy-danger-bg); color: var(--legacy-danger-text); }
    .legacy-badge--warning { background: var(--legacy-warning-bg); color: var(--legacy-warning-text); }
    .legacy-badge--accent { background: var(--legacy-accent-bg); color: var(--legacy-accent-text); }
    .legacy-badge--muted {
        background: var(--legacy-card);
        color: var(--legacy-text-secondary);
        border: 1px solid var(--legacy-border);
    }

    .legacy-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }

    @media (max-width: 900px) {
        .legacy-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    .legacy-stat-card {
        background: var(--legacy-muted-card);
        border-radius: 8px;
        padding: 10px 12px;
        border: 1px solid transparent;
    }

    .legacy-stat-card--danger { background: var(--legacy-danger-bg); }
    .legacy-stat-card--success { background: var(--legacy-success-bg); }

    .legacy-stat-label {
        margin: 0 0 2px;
        font-size: var(--admin-helper-size, 0.7rem);
        color: var(--legacy-text-secondary);
    }

    .legacy-stat-value {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 650;
        line-height: 1.25;
        color: var(--legacy-text);
    }

    .legacy-stat-hint {
        margin: 2px 0 0;
        font-size: 0.7rem;
        color: var(--legacy-text-muted);
    }
</style>
