<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-1', function () {
    return view('test_y1');
});
Route::get('/test-2', function () {
    return view('test_a1');
});