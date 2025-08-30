<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    protected $except = [
        'payment/cancel',
        'payment/success',
        'payment/fail',
        'api/payment/ipn'
    ];
}
