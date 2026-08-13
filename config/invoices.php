<?php

return [
    'agency_nip' => env('INVOICES_AGENCY_NIP', '7162508761'),
    'storage_disk' => env('INVOICES_STORAGE_DISK', 'public'),
    'pdf_storage_path' => 'vendor-invoices',

    /*
    | Stub wysyłki e-Faktur do KSeF (outbound). Import KSeF pozostaje osobnym flow.
    */
    'ksef_outbound_enabled' => (bool) env('INVOICES_KSEF_OUTBOUND_ENABLED', false),
];
