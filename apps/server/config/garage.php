<?php

return [
    'invoice_storage' => [
        'max_documents' => (int) env('INVOICE_STORAGE_MAX_DOCUMENTS', 100),
        'max_bytes' => (int) env('INVOICE_STORAGE_MAX_BYTES', 100 * 1024 * 1024),
    ],
];
