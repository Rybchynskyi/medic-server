<?php

return [

    /*
    | Signature of the invitation letters sent to clients from the client base.
    */
    'sender' => [
        'organization' => env('INVITATION_SENDER_ORGANIZATION', 'Центр «Медицина ВМ»'),
        'name' => env('INVITATION_SENDER_NAME', 'Миколайчук Сергій Іванович'),
        'position' => env('INVITATION_SENDER_POSITION', 'заступник директора Центру «Медицина ВМ»'),
        'phones' => env('INVITATION_SENDER_PHONES', '(097) 470-07-79, (099) 770-74-46'),
        'email' => env('INVITATION_SENDER_EMAIL', 'mail777@ukr.net'),
    ],

];
