<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/websocket-demo', function () {
    return view('websocket-demo');
});

Route::get('/simple-websocket-demo', function () {
    return view('simple-websocket-demo');
});

Route::get('/websocket-test', function () {
    return view('websocket-test');
});
