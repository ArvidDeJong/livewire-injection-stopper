<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Tests\Feature;

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Darvis\LivewireInjectionStopper\Tests\TestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Throwable;

/**
 * The silencing switch is read when the package boots, so this needs its own application.
 */
final class SilencingDisabledTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('livewire-injection-stopper.silence_locked_property_exceptions', false);
    }

    public function test_locked_property_exceptions_are_rendered_and_reported_when_silencing_is_disabled(): void
    {
        Event::fake([RequestBlocked::class]);
        Route::middleware(BlockInjectionAttempts::class)->get('/locked', fn () => throw new CannotUpdateLockedPropertyException('isAdmin'));

        // Laravel renders a 500, Livewire 4 renders its own 419 outside debug mode; neither is the block response.
        $response = $this->get('/locked');

        $this->assertNotSame(403, $response->getStatusCode());
        $response->assertDontSee('Access Denied');
        Event::assertNotDispatched(RequestBlocked::class);

        $seen = [];
        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$seen): void {
            $seen[] = $e;
        });

        $locked = new CannotUpdateLockedPropertyException('isAdmin');
        $this->app->make(ExceptionHandler::class)->report($locked);

        $this->assertSame([$locked], $seen);
    }
}
