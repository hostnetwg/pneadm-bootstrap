<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Porzucona płatność online (Etap 1–4, synchronizacja z pnedu)
    |--------------------------------------------------------------------------
    |
    | Po ilu minutach awaiting_payment uznajemy zamówienie online za porzucone
    | (badge / filtr w liście form-orders). Ta sama wartość co w pnedu
    | (ORDER_FORM_ONLINE_ABANDONMENT_MINUTES).
    |
    */
    'online_abandonment_minutes' => (int) env('ORDER_FORM_ONLINE_ABANDONMENT_MINUTES', 60),

    /*
    | Limit uczestników na jednym zamówieniu (create/edit w panelu).
    | Ten sam limit co publiczny formularz pnedu.pl.
    */
    'max_participants' => (int) env('ORDER_FORM_MAX_PARTICIPANTS', 50),

];
