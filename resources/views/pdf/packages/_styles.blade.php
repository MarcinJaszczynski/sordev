{{-- Styl zbliżony do pliki/raporty — DejaVu Sans dla polskich znaków w DomPDF --}}
<style>
    /*
     * DomPDF pod wydruk: margines na body (jak pakiet hotelu).
     * @page = 0, żeby nie dublować z body. Bez * { margin:0 } — to wcześniej
     * zerowało body i treść lądowała na krawędzi kartki.
     */
    @page {
        size: A4 portrait;
        margin: 0;
    }
    html {
        margin: 0;
        padding: 0;
    }
    body {
        margin: 18mm 16mm 20mm 16mm;
        padding: 0;
        background: #fff;
        font-family: DejaVu Sans, sans-serif;
        color: #1a1a1a;
        font-size: 11px;
        line-height: 1.35;
    }
    .a4 {
        width: 100%;
        max-width: 100%;
        background: #fff;
        padding: 0;
        margin: 0;
        box-sizing: border-box;
    }
    .logo-wrap { text-align: center; margin: 0 0 12px 0; padding: 0; }
    .logo-wrap img { width: 56px; max-height: 56px; display: inline-block; }
    .doc-header { text-align: center; margin: 0 0 12px 0; padding: 0; }
    .doc-title { font-size: 18px; font-weight: normal; color: #111827; line-height: 1.25; margin: 0 0 4px 0; padding: 0; }
    .doc-sub { font-size: 12px; color: #6b7280; margin: 0 0 3px 0; padding: 0; }
    .doc-pilot {
        font-size: 11px;
        color: #111827;
        text-align: center;
        margin: 6px 0 10px 0;
        padding: 0;
    }
    .doc-meta { font-size: 10px; color: #9ca3af; margin: 0; padding: 0; }
    .doc-meta strong { color: #4b5563; }
    .divider { border: none; border-top: 1px solid #e5e7eb; margin: 0 0 12px 0; padding: 0; }
    .section { background: #f8f8f8; margin: 0 0 10px 0; padding: 0; page-break-inside: avoid; }
    .section-title {
        font-size: 9px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .6px; color: #6b7280;
        padding: 8px 12px 6px;
        margin: 0;
        border-bottom: 1px solid #d1d5db;
    }
    .section-body { padding: 6px 12px 10px; margin: 0; }
    table.rows { width: 100%; border-collapse: collapse; font-size: 11px; margin: 0; }
    table.rows td { padding: 4px 0; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
    table.rows tr:last-child td { border-bottom: none; }
    table.rows .lbl { color: #6b7280; width: 34%; padding-right: 8px; }
    table.rows .val { color: #111827; font-weight: bold; }
    table.rows .val--departure { color: #1d4ed8; background: #eff6ff; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--destination { color: #15803d; background: #f0fdf4; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--return { color: #c2410c; background: #fff7ed; padding: 4px 6px; border-radius: 4px; }
    table.rows .val--return-place { color: #374151; padding: 4px 6px; }
    table.rows .val--return-place-diff { color: #7c3aed; background: #f5f3ff; padding: 4px 6px; border-radius: 4px; }
    table.rows .val small { font-weight: normal; color: #6b7280; font-size: 10px; }
    .notes-field {
        border: 1px solid #d1d5db; min-height: 56px;
        padding: 6px; font-size: 11px; color: #374151;
        margin: 0;
    }
    .hotel-name { font-size: 12px; font-weight: 700; color: #111827; margin: 6px 0 2px; }
    .hotel-addr { font-size: 11px; color: #374151; line-height: 1.4; margin-bottom: 2px; }
    .hotel-region { font-size: 10px; color: #6b7280; margin-bottom: 4px; }
    .hotel-tel { font-size: 11px; font-weight: bold; color: #111827; }
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
    .program-day { margin: 0 0 12px 0; padding: 0; page-break-inside: avoid; }
    .program-day-title {
        padding: 6px 8px;
        margin: 0;
        background: #eef2f7;
        border: 1px solid #d1d5db;
        border-bottom: none;
        font-size: 11px; font-weight: 700; color: #111827;
    }
    .program-table {
        width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5px; margin: 0;
    }
    .program-table th, .program-table td {
        border: 1px solid #d1d5db; padding: 5px 6px; vertical-align: top;
        word-wrap: break-word; overflow-wrap: break-word;
    }
    .program-table th {
        background: #f8fafc; color: #6b7280; font-size: 8.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .25px;
    }
    .program-table .pt-title { font-weight: 700; color: #111827; }
    .program-table .pt-desc { color: #374151; margin-top: 2px; }
    .program-table .pt-contact { color: #4b5563; margin-top: 3px; font-size: 9px; }
    .handwrite {
        display: block;
        min-height: 18px;
        border-bottom: 1px dotted #9ca3af;
        margin-top: 4px;
    }
    .handwrite-tall {
        display: block;
        min-height: 28px;
        border: 1px dotted #cbd5e1;
        background: #fff;
        margin-top: 3px;
    }
    .ag-table { width: 100%; border-collapse: collapse; font-size: 9px; margin: 0 0 8px 0; }
    .ag-table th, .ag-table td { border: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top; }
    .ag-table th { background: #eef2f7; color: #374151; font-weight: 700; }
    .page-footer {
        margin-top: 14px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center;
    }
    .page-footer .company { font-size: 10px; font-weight: 700; color: #374151; margin-bottom: 2px; }
    .page-footer .address { font-size: 9px; color: #6b7280; line-height: 1.45; }
    .muted { color: #6b7280; font-size: 10px; }
    .money, .money-nowrap { white-space: nowrap; }
</style>
