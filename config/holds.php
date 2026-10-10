<?php

// Vehicle holds (SRS-TOC-PAPI section 7): every channel must win a hold in
// Toco's database before taking a buyer's money, so no car is sold twice.

return [
    // Website checkout, bank transfer and proforma invoices claim holds only
    // when this is on. Admin holds and the "Under offer" display always work.
    'enforced' => (bool) env('HOLDS_ENFORCED', false),

    // Default hold length per channel, in hours. Partners use their own
    // setting (24 to 96 h). A paid order keeps its hold until staff confirm
    // the sale (no expiry).
    'hours' => [
        'web_paypal' => 3,   // PayPal approval links lapse after about 3 hours
        'web_tt' => 48,
        'proforma' => 48,
        'admin' => 48,
        'partner' => 48,
    ],

    // Extend choices offered in the admin (UI brief section 3).
    'extend_options' => [12, 24, 48],
];
