<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Odoo 18 invoicing
    |--------------------------------------------------------------------------
    |
    | Odoo is used for exactly one thing: creating the customer invoice once a
    | job's actual (post-waste) quantity is confirmed. Leave ODOO_FAKE=true
    | until a sandbox database is available.
    |
    */

    'url' => env('ODOO_URL'),
    'db' => env('ODOO_DB'),
    'username' => env('ODOO_USERNAME'),
    'api_key' => env('ODOO_API_KEY'),
    'timeout' => (int) env('ODOO_TIMEOUT', 15),

    // Use the in-memory fake client instead of calling Odoo.
    'fake' => (bool) env('ODOO_FAKE', true),

    // account.tax id to apply on the invoice line (e.g. 14% VAT). Null = none.
    'tax_id' => env('ODOO_TAX_ID') ? (int) env('ODOO_TAX_ID') : null,

    // Sales journal id. Null = Odoo's default sales journal.
    'journal_id' => env('ODOO_JOURNAL_ID') ? (int) env('ODOO_JOURNAL_ID') : null,

    // Post (confirm) the invoice right away, or leave it as a draft in Odoo.
    'auto_post' => (bool) env('ODOO_AUTO_POST', false),

    // Queue the invoice automatically when a job is marked completed.
    'auto_invoice_on_complete' => (bool) env('ODOO_AUTO_INVOICE', true),

];
