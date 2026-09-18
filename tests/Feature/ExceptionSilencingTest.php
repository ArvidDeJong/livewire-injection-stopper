<?php

declare(strict_types=1);

use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Darvis\LivewireInjectionStopper\Exceptions\SilentExceptionHandler;
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

class ArrayAssigningComponent extends Component
{
    public string $title = '';

    public function assignArray(): void
    {
        // What Livewire's hydration does with a manipulated payload: an array into a typed property.
        $this->title = ['not', 'a', 'string'];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

/**
 * A reportable callback registered after the package's own; it collects what still gets reported.
 *
 * @param  array<int, Throwable>  $seen
 */
function spyOnReporting(ExceptionHandler $handler, array &$seen): void
{
    $handler->reportable(function (Throwable $e) use (&$seen): void {
        $seen[] = $e;
    });
}

/**
 * Exceptions that reach a reportable callback registered after the package's own.
 *
 * @return array<int, Throwable>
 */
function reportedExceptions(Throwable ...$exceptions): array
{
    $seen = [];
    spyOnReporting(app(ExceptionHandler::class), $seen);

    foreach ($exceptions as $exception) {
        app(ExceptionHandler::class)->report($exception);
    }

    return $seen;
}

it('answers a locked property exception with the block response, whoever rendered it', function (bool $debug) {
    config(['app.debug' => $debug]);
    Event::fake([RequestBlocked::class]);
    Route::middleware(BlockInjectionAttempts::class)->get('/locked', fn () => throw new CannotUpdateLockedPropertyException('isAdmin'));

    $this->get('/locked')->assertForbidden()->assertSee('Access Denied');

    Event::assertDispatched(RequestBlocked::class, fn (RequestBlocked $event) => $event->reason === RequestBlocked::LOCKED_PROPERTY
        && $event->exception instanceof CannotUpdateLockedPropertyException);
})->with(['debug on' => true, 'debug off' => false]);

it('keeps the locked property exception out of the reporting pipeline', function () {
    $locked = new CannotUpdateLockedPropertyException('isAdmin');
    $other = new RuntimeException('Something else');

    expect(reportedExceptions($locked, $other))->toBe([$other]);
});

it('silences a TypeError raised while Livewire assigns an array to a typed property', function () {
    // Livewire 3's test harness rebinds the exception handler to an anonymous class, so keep the real one.
    $handler = app(ExceptionHandler::class);
    $seen = [];
    spyOnReporting($handler, $seen);

    try {
        Livewire::test(ArrayAssigningComponent::class)->call('assignArray');
    } catch (TypeError $e) {
        expect($e->getMessage())->toContain('Cannot assign array to property');
        expect(SilentExceptionHandler::shouldSilence($e))->toBeTrue();

        $handler->report($e);
        expect($seen)->toBe([]);

        return;
    }

    $this->fail('Expected a TypeError from the array assignment.');
});

it('still reports a TypeError that has nothing to do with Livewire', function () {
    $outside = new TypeError('Cannot assign array to property App\Models\User::$name of type string');

    expect(SilentExceptionHandler::shouldSilence($outside))->toBeFalse();
    expect(SilentExceptionHandler::shouldSilence(new RuntimeException('Cannot assign array to property')))->toBeFalse();
    expect(reportedExceptions($outside))->toBe([$outside]);

    Route::middleware(BlockInjectionAttempts::class)->get('/boom', fn () => throw new TypeError('Something else'));

    $this->get('/boom')->assertStatus(500);
});

it('lists the locked property exception for dontReport', function () {
    expect(SilentExceptionHandler::getDontReport())->toBe([CannotUpdateLockedPropertyException::class]);
});
