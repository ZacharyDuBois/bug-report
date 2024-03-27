<?php

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

Route::get('/', function () {
    return view('welcome');
});

// This route will demonstrate a successful HTTP request.
Route::get('/successful-http-request', function () {
    // Not that this is how I am actually doing this event listener, this is more to demonstrate that this event is
    // never fired.
    // Issue in both Laravel 10 and 11.
    Event::listen(ResponseReceived::class, function (ResponseReceived $received) {
        // Event is never received.
        dump($received);
    });

    $client = Http::buildClient();

    // Multiple synchronous requests to check cookie persistence.
    $res1 = Http::setClient($client)->get(URL::route('cookie-test'));
    $res2 = Http::setClient($client)->get(URL::route('cookie-test'));
    $res3 = Http::setClient($client)->get(URL::route('cookie-test'));

    // Cookies persisted (as intended).
    return [$res1->json('id'), $res2->json('id'), $res3->json('id')];
});

// Now lets demonstrate that this behaviour is related to the setClient() call by removing it from the requests.
Route::get('/successful-http-request-without-shared', function () {
    Event::listen(ResponseReceived::class, function (ResponseReceived $received) {
        // Event is fired x3.
        dump($received);
    });


    // Multiple synchronous requests to check cookie persistence.
    $res1 = Http::get(URL::route('cookie-test'));
    $res2 = Http::get(URL::route('cookie-test'));
    $res3 = Http::get(URL::route('cookie-test'));

    // Cookies not persisted, the problem I was trying to tackle.
    return [$res1->json('id'), $res2->json('id'), $res3->json('id')];
});

// Demonstrate in Laravel 10, the ConnectionFailed event does not fire but does fire in Laravel 11.
Route::get('/bad-host', function () {
    // This works in Laravel 11, does not work in Laravel 10
    Event::listen(ConnectionFailed::class, function (ConnectionFailed $failed) {
        // Event is fired in Laravel 11, not in Laravel 10.
        dump($failed);
    });

    $client = Http::buildClient();

    // Assume 9999 does not exist.
    $res1 = Http::setClient($client)->connectTimeout(1)->get('http://127.0.0.1:9999');
    $res2 = Http::setClient($client)->connectTimeout(1)->get('http://127.0.0.1:9999');
    $res3 = Http::setClient($client)->connectTimeout(1)->get('http://127.0.0.1:9999');

    // Unreachable as an exception will be thrown before getting here.
    return [$res1->json('id'), $res2->json('id'), $res3->json('id')];
});

// Same as above, but again, without the shared client set by setClient().
Route::get('/bad-host-without-shared', function () {
    // Event is fired in Laravel 11 and Laravel 10.
    Event::listen(ConnectionFailed::class, function (ConnectionFailed $failed) {
        // Fired x1 in Laravel 11 and Laravel 10.
        dump($failed);
    });

    $res1 = Http::connectTimeout(1)->get('http://127.0.0.1:9999');
    $res2 = Http::connectTimeout(1)->get('http://127.0.0.1:9999');
    $res3 = Http::connectTimeout(1)->get('http://127.0.0.1:9999');

    // Unreachable as an exception will be thrown before getting here.
    return [$res1->json('id'), $res2->json('id'), $res3->json('id')];
});

// Ignore, just giving a method to test all in one spot.
Route::name('cookie-test')->get('/cookie-test', function (Request $request) {
    // Simply return the session ID.
    return ['id' => session()->getId()];
});
