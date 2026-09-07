# Changelog

All notable changes to `laravel-logged-inbound-email` will be documented in this file.

## Unreleased

- Webhook requests that fail provider signature/verification now persist as an `InboundEmail` row with `status === Failed` and `error === "Verification failed"`, instead of being dropped with no database trace. Tenant-policy rejections (unrecognized provider, disallowed tenant) are unaffected and still create no row.
- Rebuilt the package on `spatie/package-skeleton-laravel` conventions: service provider now extends `Spatie\LaravelPackageTools\PackageServiceProvider`, a Testbench `workbench/` app was added for local development, and CI was reshaped to the skeleton's workflow layout.
- Renamed the composer package to `dcodegroup/laravel-logged-inbound-email` and the PHP namespace to `Dcodegroup\LaravelLoggedInboundEmail`.
