---
title: Payload injection
nav_order: 4
description: How darvis/livewire-injection-stopper rejects arrays sent to scalar Livewire properties, what that means for array properties, and how it keeps the resulting Livewire exceptions out of Sentry.
---

# Payload injection

A Livewire update request carries, per component, an `updates` map of property names to new values. A browser sends what `wire:model` bound. A bot replays the request with whatever it likes: an array where the component expects a string, or a new value for a property the component never exposed for editing.

## The payload rules

On Livewire's update endpoint, and only there, the middleware reads the JSON body and looks at every property update. An update is rejected when its value is an **array** and the property name looks **scalar**:

1. The name starts with `is_`, `has_`, `show_`, `can_`, `should_`, `enable`, `disable`, `active`, `visible` or `hidden`.
2. The name is in `scalar_properties`. The default list holds common form and display names: `title`, `name`, `email`, `status`, `content`, `url` and others.
3. `block_all_array_injections` is true (the default) and the name has no dot, so it is a top-level property.

Rule 3 makes the check strict: every array sent to a top-level property is rejected. Nested keys such as `form.tags` or `filters.categories` are never blocked by that rule, because that is how array properties are usually bound. Values that are not arrays are never inspected.

The endpoint is recognised by its route name, which ends in `livewire.update` in both Livewire 3 (`/livewire/update`) and Livewire 4 (`/livewire-<hash>/update`), or by the path `livewire/update`. A custom update URI configured in Livewire is covered too.

### Components with array properties

If a component binds an array at the top level, for example a multi-select with `wire:model="tags"`, that update is rejected under the default settings. Two ways out:

- Bind the property under a nested key, such as a form object (`form.tags`) or an array property (`filters.tags`).
- Set `block_all_array_injections` to false. Then only rules 1 and 2 apply, and you can trim `scalar_properties` to the names your components use.

```php
'block_all_array_injections' => false,
'scalar_properties' => ['title', 'email', 'status'],
```

Turning off `check_payload_injection` disables the whole inspection.

## Exception silencing

Two exceptions are the fingerprint of a manipulated payload that got past the middleware:

- `Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException`, thrown when a request tries to change a `#[Locked]` property.
- A `TypeError` with the message `Cannot assign array to property …`, raised inside Livewire while it assigns a payload value to a typed property.

With `silence_locked_property_exceptions` enabled (the default), for those two cases:

- the package marks the exception as not reportable on Laravel's exception handler, so Sentry, Flare and other trackers that hook into the reporting pipeline never see it. Every other exception is reported as usual;
- the middleware replaces the rendered error response with the block response (`response_status`, `response_message`), writes the usual warning to the log and dispatches `RequestBlocked` with reason `locked_property` and the exception attached. Laravel renders a controller exception inside the route pipeline and hands the response back through the middleware, so this works whoever rendered it, including Livewire 4's own `render()` on the locked-property exception, which answers with an empty 419 outside debug mode.

A `TypeError` counts only when its message matches and its stack trace passes through a `Livewire\` class or the `vendor/livewire/livewire` directory. A `TypeError` from your own code is reported as usual. Livewire 4 rejects wrong-type values itself with a 419 before a `TypeError` can occur; that response passes through unchanged.

### Custom exception handlers

The package works through `dontReport()` and `reportable()` on Laravel's exception handler, so it covers the `withExceptions()` callback in `bootstrap/app.php` and an `app/Exceptions/Handler.php` that extends the framework handler. Its `reportable()` callback stops the reporting of the silenced exceptions only; every other exception continues to the callbacks and the log.

If your handler overrides `report()` and calls Sentry directly, skip the call when the package would silence the exception:

```php
use Darvis\LivewireInjectionStopper\Exceptions\SilentExceptionHandler;

public function report(Throwable $e): void
{
    if (SilentExceptionHandler::shouldSilence($e)) {
        return;
    }

    parent::report($e);
}
```

`SilentExceptionHandler::getDontReport()` returns the exception classes the package adds to `dontReport`.

## Checking the payload yourself

`LivewireInjectionStopper::hasSuspiciousPayload($request)` runs the same rules on any request, for example in a custom middleware for an endpoint that is not Livewire's.
