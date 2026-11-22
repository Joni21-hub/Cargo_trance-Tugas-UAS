<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('cargotrace', ['initialPage' => 'home', 'trackingNumber' => null]));
Route::get('/login', fn () => view('cargotrace', ['initialPage' => 'login', 'trackingNumber' => null]));
Route::get('/register', fn () => view('cargotrace', ['initialPage' => 'register', 'trackingNumber' => null]));
Route::get('/track/{tracking_number?}', fn ($tracking_number = null) => view('cargotrace', ['initialPage' => 'track', 'trackingNumber' => $tracking_number]));

Route::get('/dashboard', fn () => view('cargotrace', ['initialPage' => 'dashboard', 'trackingNumber' => null]));
Route::get('/admin', fn () => view('cargotrace', ['initialPage' => 'admin', 'trackingNumber' => null]));
Route::get('/operator', fn () => view('cargotrace', ['initialPage' => 'operator', 'trackingNumber' => null]));
Route::get('/shipper', fn () => view('cargotrace', ['initialPage' => 'shipper', 'trackingNumber' => null]));
Route::get('/buyer', fn () => view('cargotrace', ['initialPage' => 'buyer', 'trackingNumber' => null]));
