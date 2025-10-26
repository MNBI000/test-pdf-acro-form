<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-1', function () {
    return view('test_y1');
});
Route::get('/test-y3', function () {
    return view('test_y3');
});
Route::get('/test-2', function () {
    return view('test_a1');
});
Route::get('/test-chat', function () {
    return view('test-chat');
});
