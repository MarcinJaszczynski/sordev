{{-- DomPDF: zawsze DejaVu Sans — bold bez font-family gubi polskie znaki --}}
<style>
    @page { margin: 12mm 11mm; size: a4 portrait; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        background: #fff;
        font-family: DejaVu Sans, sans-serif;
        color: #1a1a1a;
        padding: 0;
        font-size: 12px;
    }
    /* DomPDF: tylko normal/bold — unikać font-weight 500/600 */
    b, strong, .fw-bold,
    .doc-title, .section-title, .files-title,
    table.rows .val, table.rows .val small,
    .hotel-name, .hotel-tel, .hotel-branch,
    .program-day-title, .program-table th,
    .ag-table th, .page-footer .company,
    .driver-box-title, .driver-hl, .driver-time,
    .agenda-title, .kv-label, .meta-line strong {
        font-family: DejaVu Sans, sans-serif;
    }
    b, strong, .fw-bold {
        font-weight: bold;
    }
    .a4 {
        width: 100%;
        background: #fff;
        padding: 0 4px 12px;
    }
    .logo-wrap { text-align: center; margin-bottom: 10px; }
    .logo-wrap img { width: 52px; max-height: 52px; display: inline-block; }
    .doc-header { text-align: center; margin-bottom: 12px; }
    .doc-title {
        font-size: 18px;
        font-weight: bold;
        color: #111827;
        line-height: 1.3;
        margin-bottom: 4px;
        letter-spacing: .04em;
    }
    .doc-sub { font-size: 12px; color: #374151; margin-bottom: 3px; font-family: DejaVu Sans, sans-serif; }
    .doc-pilot {
        font-size: 12px;
        color: #111827;
        text-align: center;
        margin: 6px 0 10px;
        font-family: DejaVu Sans, sans-serif;
    }
    .doc-meta { font-size: 11px; color: #6b7280; font-family: DejaVu Sans, sans-serif; }
    .doc-meta strong { color: #4b5563; font-weight: bold; }
    .divider { border: none; border-top: 1px solid #e5e7eb; margin: 0 0 12px 0; }
    .section { background: #f8f8f8; margin-bottom: 8px; }
    .section-title {
        font-size: 9px; font-weight: bold; text-transform: uppercase;
        letter-spacing: .6px; color: #6b7280;
        padding: 8px 14px 6px;
        border-bottom: 1px solid #d1d5db;
    }
    .section-body { padding: 0 14px 10px; }
    table.rows { width: 100%; border-collapse: collapse; font-size: 11px; }
    table.rows td { padding: 4px 0; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
    table.rows tr:last-child td { border-bottom: none; }
    table.rows .lbl { color: #6b7280; width: 40%; padding-right: 8px; font-family: DejaVu Sans, sans-serif; }
    table.rows .val { color: #111827; font-weight: bold; }
    table.rows .val--departure { color: #1d4ed8; background: #eff6ff; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--destination { color: #15803d; background: #f0fdf4; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--return { color: #c2410c; background: #fff7ed; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--return-place { color: #374151; padding: 4px 6px; }
    table.rows .val--return-place-diff { color: #7c3aed; background: #f5f3ff; padding: 4px 6px; border-radius: 4px; }
    table.rows .val small { font-weight: normal; color: #6b7280; font-size: 10px; }
    .notes-field {
        border: 1px solid #d1d5db; min-height: 48px;
        padding: 6px; font-size: 11px; color: #374151;
        font-family: DejaVu Sans, sans-serif;
    }
    .hotel-name { font-size: 12px; font-weight: bold; color: #111827; margin: 8px 0 2px; }
    .hotel-branch { font-size: 11px; color: #4b5563; margin-bottom: 2px; font-weight: normal; }
    .hotel-addr { font-size: 11px; color: #374151; line-height: 1.45; margin-bottom: 2px; font-family: DejaVu Sans, sans-serif; }
    .hotel-region { font-size: 10px; color: #6b7280; margin-bottom: 6px; font-family: DejaVu Sans, sans-serif; }
    .hotel-tel { font-size: 11px; font-weight: bold; color: #111827; }
    .files {
        margin: 8px 0 10px;
        padding: 8px 10px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
    }
    .files-title {
        font-size: 9px; font-weight: bold; text-transform: uppercase;
        letter-spacing: .6px; color: #6b7280; margin-bottom: 6px;
    }
    .files-list { list-style: none; margin: 0; padding: 0; }
    .files-list li { font-size: 11px; color: #111827; margin-bottom: 4px; word-break: break-all; font-family: DejaVu Sans, sans-serif; }
    .files-empty { font-size: 11px; color: #6b7280; font-family: DejaVu Sans, sans-serif; }
    .files-note { font-size: 9px; color: #6b7280; margin-top: 6px; font-family: DejaVu Sans, sans-serif; }
    .program-day { margin-bottom: 18px; page-break-inside: avoid; }
    .program-day-title {
        padding: 6px 8px;
        background: #eef2f7;
        border: 1px solid #d1d5db;
        border-bottom: none;
        font-size: 12px; font-weight: bold; color: #111827;
        margin-bottom: 0;
        letter-spacing: 0.02em;
    }
    .program-table {
        width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10px;
    }
    .program-table th, .program-table td {
        border: 1px solid #d1d5db; padding: 5px 6px; vertical-align: top;
        font-family: DejaVu Sans, sans-serif;
        word-break: keep-all;
    }
    .program-table th {
        background: #f8fafc; color: #6b7280; font-size: 9px; font-weight: bold;
        text-transform: uppercase; letter-spacing: .3px;
    }
    .ag-table { width: 100%; border-collapse: collapse; font-size: 9px; margin-bottom: 8px; }
    .ag-table th, .ag-table td {
        border: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top;
        font-family: DejaVu Sans, sans-serif;
    }
    .ag-table th { background: #eef2f7; color: #374151; font-weight: bold; }
    .page-footer {
        margin-top: 14px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center;
    }
    .page-footer .company { font-size: 10px; font-weight: bold; color: #374151; margin-bottom: 2px; }
    .page-footer .address { font-size: 9px; color: #6b7280; line-height: 1.5; font-family: DejaVu Sans, sans-serif; }
    .page-footer .nip { font-size: 9px; color: #6b7280; font-family: DejaVu Sans, sans-serif; }
    .muted { color: #6b7280; font-size: 10px; font-family: DejaVu Sans, sans-serif; }
    .money, .money-nowrap { white-space: nowrap; }

    /* Agenda hotelowa — układ jak wzorzec RAFA */
    .agenda-title { color: #1e3a5f; font-weight: bold; letter-spacing: .04em; }
    .agenda-table thead th { background: #1e3a5f; color: #f8fafc; }
    .meta-grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .meta-grid td { vertical-align: top; padding: 4px 8px 4px 0; font-size: 11px; font-family: DejaVu Sans, sans-serif; }
    .meta-grid .col-left { width: 52%; }
    .meta-grid .col-right { width: 48%; }
    .kv-label { color: #6b7280; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
    .kv-value { color: #111827; font-size: 12px; font-weight: bold; font-family: DejaVu Sans, sans-serif; line-height: 1.35; }
    .kv-value .sub { display: block; font-weight: normal; font-size: 10px; color: #4b5563; margin-top: 1px; }
    .hotel-program-label {
        font-size: 12px; font-weight: bold; color: #1e3a5f; margin: 8px 0 6px;
        font-family: DejaVu Sans, sans-serif;
    }
    .agenda-table .cell-date { width: 22%; white-space: nowrap; }
    .agenda-table .cell-date .time {
        display: block; font-weight: bold; color: #111827; font-size: 11px;
        font-family: DejaVu Sans, sans-serif;
    }
    .agenda-table .cell-date .day {
        display: block; color: #6b7280; font-size: 10px; margin-top: 2px;
        font-family: DejaVu Sans, sans-serif;
    }

    /* Teczka kierowcy — krótka karta operacyjna */
    .driver-box {
        border: 2px solid #1e3a5f;
        background: #f8fafc;
        margin-bottom: 12px;
        padding: 0;
    }
    .driver-box-title {
        background: #1e3a5f;
        color: #fff;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .5px;
        padding: 8px 14px;
    }
    .driver-box .section-body,
    .driver-box table.rows { padding: 8px 14px 10px; }
    .driver-hl {
        font-size: 14px;
        font-weight: bold;
        color: #111827;
        letter-spacing: .02em;
    }
    .driver-time { font-size: 13px; font-weight: bold; color: #1e3a5f; }
    .hotel-block { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
    .hotel-block:last-child { border-bottom: none; margin-bottom: 0; }
    tr.hl-transport td { background: #eff6ff; }
    tr.hl-hotel td { background: #f0fdf4; }
</style>
