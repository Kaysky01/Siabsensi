<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google reCAPTCHA v2 integration.
    | Get your keys from: https://www.google.com/recaptcha/admin/create
    |
    */

    'site_key' => env('RECAPTCHA_SITE_KEY'),

    'secret_key' => env('RECAPTCHA_SECRET_KEY'),

    'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',

];
