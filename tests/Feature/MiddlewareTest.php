<?php

namespace Darvis\LivewireInjectionStopper\Tests\Feature;

use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Darvis\LivewireInjectionStopper\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(BlockInjectionAttempts::class)->get('/test', function () {
            return response()->json(['success' => true]);
        });

        Route::middleware(BlockInjectionAttempts::class)->get('/api/webhooks/test', function () {
            return response()->json(['webhook' => true]);
        });
    }

    /** @test */
    public function it_allows_normal_requests()
    {
        $response = $this->get('/test', [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function it_blocks_python_requests()
    {
        $response = $this->get('/test', [
            'User-Agent' => 'python-requests/2.28.0',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_blocks_curl_requests()
    {
        $response = $this->get('/test', [
            'User-Agent' => 'curl/7.68.0',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_blocks_wget_requests()
    {
        $response = $this->get('/test', [
            'User-Agent' => 'Wget/1.20.3',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_blocks_bot_user_agents()
    {
        $response = $this->get('/test', [
            'User-Agent' => 'SomeBot/1.0',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_blocks_configured_ip_addresses()
    {
        $response = $this->call('GET', '/test', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_allows_whitelisted_routes()
    {
        $response = $this->get('/api/webhooks/test', [
            'User-Agent' => 'python-requests/2.28.0',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['webhook' => true]);
    }

    /** @test */
    public function it_is_case_insensitive()
    {
        $response = $this->get('/test', [
            'User-Agent' => 'PYTHON-REQUESTS/2.28.0',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_allows_requests_without_user_agent()
    {
        $response = $this->get('/test');

        $response->assertStatus(200);
    }
}
