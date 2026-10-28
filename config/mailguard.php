<?php

return [

    /*
    | Test mode for outgoing mail. While enabled (the default, so a forgotten
    | setting never mails real clients) mail is delivered only to the
    | addresses below; anything else is dropped and logged.
    | Switch off with MAIL_SAFE_MODE=false once the app goes live.
    */
    'safe_mode' => (bool) env('MAIL_SAFE_MODE', true),

    'allowed_recipients' => array_values(array_filter(array_map(
        fn ($address) => mb_strtolower(trim($address)),
        explode(',', (string) env('MAIL_ALLOWED_RECIPIENTS', ''))
    ))),

];
