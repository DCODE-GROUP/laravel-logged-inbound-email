# Changelog

All notable changes to `laravel-logged-inbound-email` will be documented in this file.

## Unreleased

- Added plus-addressing as a second tenant discovery strategy (`tenant_plus_addressing_enabled`), parsing `{tenant_identifier}+{process}@domain` from the inbound email's recipient address. Independently enable-able alongside `organization_in_route`; the route-derived value wins when both are enabled and disagree.
- Webhook requests that fail provider signature/verification now persist as an `InboundEmail` row with `status === Failed` and `error === "Verification failed"`, instead of being dropped with no database trace. Tenant-policy rejections (unrecognized provider, disallowed tenant) are unaffected and still create no row.
- Gated the `tenant_id` column/index and `InboundEmail::tenant()` relationship behind a new `multi_tenant_enabled` config key (default `false`), evaluated at migration time. Previously `tenant_id` was always present on the table with no relationship method.
- Rebuilt the package on `spatie/package-skeleton-laravel` conventions: service provider now extends `Spatie\LaravelPackageTools\PackageServiceProvider`, a Testbench `workbench/` app was added for local development, and CI was reshaped to the skeleton's workflow layout.
- Renamed the composer package to `dcodegroup/laravel-logged-inbound-email` and the PHP namespace to `Dcodegroup\LaravelLoggedInboundEmail`.
