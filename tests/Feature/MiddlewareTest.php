<?php

declare(strict_types=1);

use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(BlockInjectionAttempts::class)->get('/test', function () {
        return response()->json(['success' => true]);
    });

    Route::middleware(BlockInjectionAttempts::class)->get('/api/webhooks/test', function () {
        return response()->json(['webhook' => true]);
    });
});

it('allows normal requests', function () {
    $response = $this->get('/test', [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
});

it('blocks python requests', function () {
    $response = $this->get('/test', [
        'User-Agent' => 'python-requests/2.28.0',
    ]);

    $response->assertStatus(403);
});

it('blocks curl requests', function () {
    $response = $this->get('/test', [
        'User-Agent' => 'curl/7.68.0',
    ]);

    $response->assertStatus(403);
});

it('blocks wget requests', function () {
    $response = $this->get('/test', [
        'User-Agent' => 'Wget/1.20.3',
    ]);

    $response->assertStatus(403);
});

it('blocks bot user agents', function () {
    $response = $this->get('/test', [
        'User-Agent' => 'SomeBot/1.0',
    ]);

    $response->assertStatus(403);
});

it('blocks configured ip addresses', function () {
    $response = $this->call('GET', '/test', [], [], [], [
        'REMOTE_ADDR' => '192.168.1.100',
        'HTTP_USER_AGENT' => 'Mozilla/5.0',
    ]);

    $response->assertStatus(403);
});

it('allows whitelisted routes', function () {
    $response = $this->get('/api/webhooks/test', [
        'User-Agent' => 'python-requests/2.28.0',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['webhook' => true]);
});

it('is case insensitive', function () {
    $response = $this->get('/test', [
        'User-Agent' => 'PYTHON-REQUESTS/2.28.0',
    ]);

    $response->assertStatus(403);
});

it('allows requests without user agent', function () {
    $response = $this->get('/test');

    $response->assertStatus(200);
});
