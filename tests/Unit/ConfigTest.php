<?php

namespace Darvis\LivewireInjectionStopper\Tests\Unit;

use Darvis\LivewireInjectionStopper\Tests\TestCase;

class ConfigTest extends TestCase
{
    /** @test */
    public function it_loads_default_configuration()
    {
        $this->assertIsArray(config('livewire-injection-stopper.blocked_user_agents'));
        $this->assertIsArray(config('livewire-injection-stopper.blocked_ips'));
        $this->assertIsArray(config('livewire-injection-stopper.whitelist_routes'));
        $this->assertIsInt(config('livewire-injection-stopper.response_status'));
        $this->assertIsString(config('livewire-injection-stopper.response_message'));
        $this->assertIsBool(config('livewire-injection-stopper.log_blocked_requests'));
    }

    /** @test */
    public function it_has_correct_default_blocked_user_agents()
    {
        $blockedAgents = config('livewire-injection-stopper.blocked_user_agents');

        $this->assertContains('python-requests', $blockedAgents);
        $this->assertContains('curl', $blockedAgents);
        $this->assertContains('wget', $blockedAgents);
        $this->assertContains('bot', $blockedAgents);
    }

    /** @test */
    public function it_allows_custom_configuration()
    {
        config()->set('livewire-injection-stopper.blocked_user_agents', ['custom-bot']);
        
        $this->assertEquals(['custom-bot'], config('livewire-injection-stopper.blocked_user_agents'));
    }

    /** @test */
    public function it_has_default_response_status_403()
    {
        $this->assertEquals(403, config('livewire-injection-stopper.response_status'));
    }

    /** @test */
    public function it_has_logging_enabled_by_default()
    {
        $this->assertTrue(config('livewire-injection-stopper.log_blocked_requests'));
    }
}
