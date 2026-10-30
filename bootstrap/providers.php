<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\MailTransportServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    MailTransportServiceProvider::class,
];
