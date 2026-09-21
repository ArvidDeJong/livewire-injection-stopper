---
title: "Payload injection"
nav_order: 6
description: "The exact rules by which an array sent to a Livewire property is rejected, how to keep array properties working, and which two Livewire exceptions are silenced."
---

# Payload injection

A Livewire update request carries, per component, a list of property names with their new values. A browser sends what `wire:model` bound. A script can replay the request with anything: an array where the component expects a string, or a new value for a property that was never meant to be edited.

The package does two separate things about that. It rejects some array values before Livewire sees them, and it silences two exceptions that Livewire or PHP throw for manipulated requests.

## Which updates are rejected

On Livewire's update endpoint, and only there, the middleware decodes the JSON body and looks at every property update in `components.*.updates` and in a top-level `updates` key. An update is rejected when its value is an **array** and the property name looks **scalar** (a single value: a string, number or boolean). A name looks scalar when one of these holds:

1. The name starts with `is_`, `has_`, `show_`, `can_`, `should_`, `enable`, `disable`, `active`, `visible` or `hidden`. The comparison is case-insensitive, and the list is fixed in the code.
2. The lowercased name is in `scalar_properties`. The 33 defaults are `style`, `class`, `id`, `name`, `title`, `label`, `value`, `text`, `content`, `description`, `placeholder`, `type`, `status`, `state`, `mode`, `color`, `size`, `width`, `height`, `url`, `href`, `src`, `alt`, `icon`, `image`, `email`, `phone`, `address`, `message`, `subject`, `body`, `slug` and `path`.
3. `block_all_array_injections` is true (the default) and the name has no dot, so it is a top-level property.

Rule 3 makes the default strict: **every array sent to a top-level property is rejected.**

What passes:

- A value that is not an array. Strings, numbers, booleans and null are never inspected.
- An array under a name with a dot, such as `form.tags` or `filters.categories`, unless that name starts with a prefix from rule 1 (`activeFilters.tags` is rejected).
- A body that is empty or not JSON.
- Everything, when `check_payload_injection` is false.

Rules 1 and 2 compare the whole name. `email` in `scalar_properties` matches the property `email`, not `form.email`. Write the entries of `scalar_properties` in lowercase: the property name is lowercased before the comparison and the entries are not, so `firstName` in the list never matches.

The endpoint is recognised by its route name, which ends in `livewire.update` in both Livewire 3 (`/livewire/update`) and Livewire 4 (`/livewire-<hash>/update`), or by a path that contains `livewire/update`. A custom update route registered with `Livewire::setUpdateRoute()` gets such a name from Livewire, so it is inspected too.

### Components with array properties

A component that binds an array at the top level, for example checkboxes or a multi-select with `wire:model="tags"` on `public array $tags = []`, can send that array as one update. Under the default settings that request gets the block response. Two ways out:

- Bind the property under a nested key: a Livewire form object (`form.tags`) or an array property (`filters.tags`).
- Set `block_all_array_injections` to false. Then only rules 1 and 2 apply. Trim `scalar_properties` to the names your components use, and check that no array property starts with a prefix from rule 1.

```php
// config/livewire-injection-stopper.php

'block_all_array_injections' => false,
'scalar_properties' => ['title', 'email', 'status'],
```

With this config an array sent to `tags` passes, and an array sent to `title`, `email`, `status` or `is_admin` is still rejected.

## The two silenced exceptions

Two exceptions are typical for a manipulated payload that got past the request filter:

- `Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException`, thrown by Livewire when a request tries to change a `#[Locked]` property.
- A `TypeError` whose message contains `Cannot assign array to property`, raised while Livewire assigns a payload value to a typed property.

With `silence_locked_property_exceptions` enabled (the default), for those two cases:

- The package registers the exception as not reportable on Laravel's exception handler, through `dontReport()` for the Livewire exception and a `reportable()` callback that returns `false` for both. An error tracker that receives exceptions through Laravel's reporting, for example Sentry registered in `withExceptions()`, does not get it. Every other exception is reported as usual.
- The middleware replaces the rendered error response with the block response (`response_status`, `response_message`), writes the warning `[LivewireInjectionStopper] Blocked Livewire property manipulation attempt` and dispatches `RequestBlocked` with the reason `locked_property` and the exception attached.

The second point needs the middleware on the route. Livewire's update route is in the `web` group, so that is the case by default.

A `TypeError` counts only when its message matches **and** its stack trace passes through a class in the `Livewire\` namespace or a file under `vendor/livewire/livewire`. A `TypeError` from your own code is reported as usual.

### What Livewire 4 already answers itself

This was checked against Livewire 4.4. Outside debug mode, that version catches the wrong-type `TypeError` itself and answers with `abort(419)`. No `TypeError` reaches Laravel, so the package does nothing: the 419 response passes through unchanged, without a log line or event. In debug mode Livewire rethrows the `TypeError`; when it reaches Laravel's exception handler, the rule above applies.

Livewire 4's `CannotUpdateLockedPropertyException` renders itself as an empty 419 outside debug mode. The middleware replaces that response too, so a locked-property attempt gets the block response in both modes.

### Custom exception handlers

The package works through `dontReport()` and `reportable()` on Laravel's exception handler. That covers the `withExceptions()` callback in `bootstrap/app.php` and an `app/Exceptions/Handler.php` that extends the framework handler.

If your handler overrides `report()` and calls the error tracker directly, skip the call when the package would silence the exception:

```php
<?php
// app/Exceptions/Handler.php

use Darvis\LivewireInjectionStopper\Exceptions\SilentExceptionHandler;
use Throwable;

public function report(Throwable $e): void
{
    if (SilentExceptionHandler::shouldSilence($e)) {
        return;
    }

    parent::report($e);
}
```

`SilentExceptionHandler::shouldSilence()` returns true for the two cases above. `SilentExceptionHandler::getDontReport()` returns the exception classes the package adds to `dontReport`, which is `CannotUpdateLockedPropertyException` only.

## Checking a payload yourself

`LivewireInjectionStopper::hasSuspiciousPayload($request)` runs the same rules on the JSON body of any request. It does not check that the request goes to Livewire's endpoint; `LivewireInjectionStopper::isLivewireUpdateRequest($request)` does that.
