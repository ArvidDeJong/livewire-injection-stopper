# Security policy

This package exists to keep bots and manipulated Livewire payloads out of an application, so a way around its checks counts as a security issue.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/livewire-injection-stopper/security/advisories/new), or
- by email to info@arvid.nl.

Include the package version, Laravel and Livewire versions, and the request or payload that gets past the middleware, or the component the audit command should have flagged.

You will get a reply within a week. Once a fix is released, the advisory is published and you are credited, unless you prefer not to be.

## Out of scope

The User-Agent check matches strings that a client chooses to send, so a bot that sends a browser User-Agent is not blocked. The payload inspection only sees arrays sent to properties that look scalar; it does not validate types for every property, that is what `#[Locked]` and typed properties are for. The audit command is a text scan of `app/Livewire` and `app/Traits`, not static analysis, and it flags names, not data flow. These are known limits, described in [How it works](https://arviddejong.github.io/livewire-injection-stopper/how-it-works.html#what-it-does-not-stop), not vulnerabilities.
