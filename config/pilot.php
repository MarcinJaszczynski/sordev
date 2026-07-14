<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dni pełnego dostępu pilota po zakończeniu imprezy
    |--------------------------------------------------------------------------
    |
    | Po upływie tego okresu od daty zakończenia imprezy pilot widzi wycieczkę
    | w historii (nazwa + termin), ale bez szczegółów, rozliczenia i dokumentów.
    |
    */
    'full_access_days_after_end' => (int) env('PILOT_FULL_ACCESS_DAYS_AFTER_END', 14),
];
