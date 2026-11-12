<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gateway settlement files — FR-FIN-02 (Week 11 Day 4)
|--------------------------------------------------------------------------
| Columns are found by their header, compared in lower case with spaces, dashes and underscores
| removed ("Txn Ref No" = "txn_ref_no" = "txnrefno"). When the real JazzCash / Easypaisa merchant
| reports arrive, add their header names here — no code change needed.
*/

return [

    'max_kb' => 5120,

    'max_rows' => 20000,

    'columns' => [
        // Our payment reference, sent to the gateway when the payment started.
        'reference' => ['reference', 'ourreference', 'merchantreference', 'pptxnrefno', 'txnrefno', 'txnreference',
            'orderid', 'orderrefnum', 'orderrefno', 'orderreference', 'billreference', 'ppbillreference'],
        // The gateway's own transaction id.
        'gateway_ref' => ['gatewayref', 'gatewayreference', 'transactionid', 'txnid', 'tid', 'rrn',
            'ppretreivalreferenceno', 'ppretrievalreferenceno', 'retrievalreferenceno', 'transactionreference',
            'easypaisatransactionid', 'jazzcashtransactionid'],
        // What the donor paid.
        'amount' => ['amount', 'grossamount', 'transactionamount', 'txnamount', 'ppamount', 'orderamount', 'gross'],
        'fee' => ['fee', 'fees', 'commission', 'mdr', 'charges', 'servicecharges', 'transactionfee', 'merchantfee'],
        // What reaches the bank account.
        'net' => ['net', 'netamount', 'settledamount', 'settlementamount', 'payableamount', 'creditamount'],
        'date' => ['date', 'transactiondate', 'txndate', 'txndatetime', 'pptxndatetime', 'transactiondatetime', 'datetime', 'createdat'],
        'status' => ['status', 'transactionstatus', 'txnstatus', 'ppresponsecode', 'responsecode', 'state'],
    ],

    // Per gateway: amounts sent in paisa (× 100) instead of rupees.
    'amounts_in_paisa' => [
        'jazzcash' => false,
        'easypaisa' => false,
    ],

    // A row whose status is not one of these is listed but ignored (failed, reversed…).
    // Rows without a status column are all treated as settled.
    'settled_statuses' => ['success', 'successful', 'succeeded', 'paid', 'settled', 'completed', 'complete',
        'approved', '000', '0000', '00', '121'],
];
