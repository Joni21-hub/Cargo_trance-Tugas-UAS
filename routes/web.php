<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('cargotrace');
});

Route::get('/login', function () {
    return view('cargotrace');
});

Route::get('/track/{tracking_number?}', function ($tracking_number = null) {
    return view('cargotrace', ['tracking_number' => $tracking_number]);
});

Route::get('/dashboard', function () {
    return view('cargotrace');
});

Route::get('/shipper', function () {
    return view('cargotrace');
});

Route::get('/operator', function () {
    return view('cargotrace');
});

Route::get('/buyer', function () {
    return view('cargotrace');
});
