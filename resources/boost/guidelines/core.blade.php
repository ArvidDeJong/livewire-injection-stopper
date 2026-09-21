## darvis/livewire-injection-stopper

Blocks scripted clients and manipulated Livewire payloads before they reach a component, answers two bot-driven Livewire exceptions with the block response (403 by default) instead of reporting them, and audits Livewire components for public properties that need `#[Locked]`.

- Nothing to wire up: `Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts` is pushed onto the `web` group at boot. For another group or a single route, use the alias `livewire-injection-stopper`. All checks live in `LivewireInjectionStopperManager` (`check()`, `reject()`), which is the only place that reads `config('livewire-injection-stopper.*')`. To change a check, extend that manager and bind the subclass (`$this->app->singleton(LivewireInjectionStopperManager::class, MyManager::class)`); extending the middleware does not work, its checks all live in the manager.
- Run `php artisan livewire-injection-stopper:audit` after adding or changing a Livewire component. It exits with code 1 for public `bool`, `int`, `string` or nullable class properties with a default value whose name or type looks sensitive (admin, role, permission, max, limit, user, cart, ...) and that lack `#[Locked]` on the line directly above. Fix it by adding `#[Locked]`, never by renaming the property to dodge the scan. A clean result is not proof: the scan skips components outside `app/Livewire`, classes that do not contain the text `extends Component`, and properties without a default value.
- Never add generic words like `bot`, `spider` or `crawler` to `blocked_user_agents` without first putting the uptime monitor and the search engines in `allowed_user_agents`: Googlebot, bingbot and UptimeRobot all contain "bot". The default list names specific crawlers on purpose.
- Every array sent to a top-level Livewire property is rejected while `block_all_array_injections` is true (the default). A multi-select or checkbox group bound with `wire:model="tags"` therefore fails with a 403. Bind it under a nested key (`form.tags`) or set `block_all_array_injections` to false. A name with a dot passes unless it starts with a fixed prefix (`is_`, `has_`, `show_`, `can_`, `should_`, `enable`, `disable`, `active`, `visible`, `hidden`). Entries in `scalar_properties` must be lowercase.
- Routes that a service calls with a scripted client (webhooks) go into `whitelist_routes`, matched with `fnmatch` against the path without a leading slash (`api/webhooks/*`). A whitelisted path skips every check.
- `CannotUpdateLockedPropertyException` and a `TypeError` raised while Livewire assigns an array to a typed property get the block response and are not reported. If the app's exception handler overrides `report()` and calls Sentry itself, return early when `SilentExceptionHandler::shouldSilence($e)` is true.
- To log or count blocked attempts, listen for `Darvis\LivewireInjectionStopper\Events\RequestBlocked` (`reason`, `ip`, `userAgent`, `url`, `exception`). The reasons are the class constants `BLOCKED_IP`, `BLOCKED_USER_AGENT`, `SUSPICIOUS_PAYLOAD` and `LOCKED_PROPERTY`.
- In tests, Laravel's HTTP client sends the User-Agent `Symfony`, which is not blocked, and `Livewire::test()` bypasses the middleware. To test a block, pass a `User-Agent` header and `Event::fake([RequestBlocked::class])`; to bypass, `withoutMiddleware(BlockInjectionAttempts::class)`. Every option except `silence_locked_property_exceptions` can be changed with `config([...])` inside a test.

@verbatim
<code-snippet name="Count blocked requests" lang="php">
use Darvis\LivewireInjectionStopper\Events\RequestBlocked;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(RequestBlocked::class, function (RequestBlocked $event) {
    if ($event->reason === RequestBlocked::SUSPICIOUS_PAYLOAD) {
        Log::info('Livewire payload injection blocked', ['ip' => $event->ip, 'url' => $event->url]);
    }
});
</code-snippet>
@endverbatim
