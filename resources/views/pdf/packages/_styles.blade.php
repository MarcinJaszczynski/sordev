{{-- Styl zbliżony do pliki/raporty — DejaVu Sans dla polskich znaków w DomPDF --}}
<style>
    @page { margin: 14mm 12mm; size: a4 portrait; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        background: #fff;
        font-family: 'DejaVu Sans', sans-serif;
        color: #1a1a1a;
        padding: 0;
        font-size: 12px;
    }
    .a4 {
        width: 100%;
        background: #fff;
        padding: 0 4px 12px;
    }
    .logo-wrap { text-align: center; margin-bottom: 12px; }
    .logo-wrap img { width: 56px; max-height: 56px; display: inline-block; }
    .doc-header { text-align: center; margin-bottom: 14px; }
    .doc-title { font-size: 20px; font-weight: 400; color: #111827; line-height: 1.3; margin-bottom: 4px; }
    .doc-sub { font-size: 12px; color: #6b7280; margin-bottom: 3px; }
    .doc-pilot {
        font-size: 12px;
        color: #111827;
        text-align: center;
        margin: 6px 0 10px;
    }
    .doc-meta { font-size: 11px; color: #9ca3af; }
    .doc-meta strong { color: #4b5563; }
    .divider { border: none; border-top: 1px solid #e5e7eb; margin: 0 0 12px 0; }
    .section { background: #f8f8f8; margin-bottom: 8px; }
    .section-title {
        font-size: 9px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .6px; color: #6b7280;
        padding: 8px 14px 6px;
        border-bottom: 1px solid #d1d5db;
    }
    .section-body { padding: 0 14px 10px; }
    table.rows { width: 100%; border-collapse: collapse; font-size: 11px; }
    table.rows td { padding: 4px 0; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
    table.rows tr:last-child td { border-bottom: none; }
    table.rows .lbl { color: #6b7280; width: 40%; padding-right: 8px; }
    table.rows .val { color: #111827; font-weight: 600; }
    table.rows .val--departure { color: #1d4ed8; background: #eff6ff; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--destination { color: #15803d; background: #f0fdf4; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--return { color: #c2410c; background: #fff7ed; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--return-place { color: #374151; padding: 4px 6px; }
    table.rows .val--return-place-diff { color: #7c3aed; background: #f5f3ff; padding: 4px 6px; border-radius: 4px; }
    table.rows .val small { font-weight: 400; color: #6b7280; font-size: 10px; }
    .notes-field {
        border: 1px solid #d1d5db; min-height: 64px;
        padding: 6px; font-size: 11px; color: #374151;
    }
    .hotel-name { font-size: 12px; font-weight: 700; color: #111827; margin: 8px 0 2px; }
    .hotel-addr { font-size: 11px; color: #374151; line-height: 1.45; margin-bottom: 2px; }
    .hotel-region { font-size: 10px; color: #6b7280; margin-bottom: 6px; }
    .hotel-tel { font-size: 11px; font-weight: 600; color: #111827; }
    .files {
        margin: 8px 0 10px;
        padding: 8px 10px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
    }
    .files-title {
        font-size: 9px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .6px; color: #6b7280; margin-bottom: 6px;
    }
    .files-list { list-style: none; margin: 0; padding: 0; }
    .files-list li { font-size: 11px; color: #111827; margin-bottom: 4px; word-break: break-all; }
    .files-empty { font-size: 11px; color: #6b7280; }
    .files-note { font-size: 9px; color: #6b7280; margin-top: 6px; }
    .program-day { margin-bottom: 10px; }
    .program-day-title {
        padding: 6px 8px;
        background: #eef2f7;
        border: 1px solid #d1d5db;
        border-bottom: none;
        font-size: 11px; font-weight: 700; color: #111827;
    }
    .program-table {
        width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10px;
    }
    .program-table th, .program-table td {
        border: 1px solid #d1d5db; padding: 5px 6px; vertical-align: top; word-break: break-word;
    }
    .program-table th {
        background: #f8fafc; color: #6b7280; font-size: 9px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .3px;
    }
    .ag-table { width: 100%; border-collapse: collapse; font-size: 9px; margin-bottom: 8px; }
    .ag-table th, .ag-table td { border: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top; }
    .ag-table th { background: #eef2f7; color: #374151; font-weight: 700; }
    .page-footer {
        margin-top: 14px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center;
    }
    .page-footer .company { font-size: 10px; font-weight: 700; color: #374151; margin-bottom: 2px; }
    .page-footer .address { font-size: 9px; color: #6b7280; line-height: 1.5; }
    .muted { color: #6b7280; font-size: 10px; }
    .money, .money-nowrap { white-space: nowrap; }
    .program-day { margin-bottom: 18px; page-break-inside: avoid; }
    .program-day-title { font-size: 14px; font-weight: 700; margin-bottom: 8px; letter-spacing: 0.04em; }
    .program-table td, .program-table th { word-break: keep-all; }
</style>
