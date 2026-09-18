# Contributing

Contributions are welcome: bug reports, fixes, documentation and ideas.

## Before you start

- **Bugs:** open an [issue](https://github.com/ArvidDeJong/livewire-injection-stopper/issues/new/choose) with the request or the component that reproduces it.
- **Features:** open an issue first. This package stays small on purpose, so let's agree a feature fits before you build it.
- **Security issues:** don't open an issue; see [SECURITY.md](SECURITY.md).

## Development

```bash
git clone https://github.com/ArvidDeJong/livewire-injection-stopper.git
cd livewire-injection-stopper
composer install

composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 8
```

CI runs the tests on PHP 8.2-8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.

## Pull requests

- Add or update tests for every change in behaviour. The tests use the package defaults; don't override the config in `TestCase`, set it in the test that needs it.
- Keep the public API compatible within 1.x: the `livewire-injection-stopper` middleware alias and its place in the `web` group, the public methods of `LivewireInjectionStopperManager` and the `LivewireInjectionStopper` facade, `SilentExceptionHandler::shouldSilence()` and `getDontReport()`, the `RequestBlocked` event with its reason constants, the `livewire-injection-stopper:audit` command and its exit codes, the config keys, and the log messages.
- A change a site owner notices (a new default pattern, a different response, another log message) is a minor release, not a patch.
- Write code, comments and messages in English.
- Update `docs/`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.
- The documentation in `docs/` is also the website. Don't write `{{ }}` or `{% %}` there; Jekyll would render it.

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
