<?php

declare(strict_types=1);

return [

    'pdf' => [

        /*
         * Seconds Browsershot waits on its child Node/Chromium process before
         * giving up with a catchable ProcessTimedOutException.
         *
         * Sized for a COLD start, which is the slow case: Chromium has to
         * launch and lay out a page carrying four base64-embedded font faces.
         * Measured on a Windows dev machine at ~20s cold versus ~7s once warm,
         * so a tight limit fails only on the first export after a restart —
         * the least convenient time to discover it.
         *
         * Keep PHP's own max_execution_time at or above this value, otherwise
         * PHP kills the request first with a bare FatalError instead of letting
         * Browsershot raise the exception this timeout exists to produce.
         */
        'timeout' => (int) env('EXPORT_PDF_TIMEOUT', 60),

    ],

];
