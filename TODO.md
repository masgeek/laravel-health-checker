# Future implementation and improvements

## Test infrastructure

- [x] Add Pest as a development dependency, create a test bootstrap, and configure the package test environment.
- [x] Add a test case for the service provider: config merging, route registration, config publishing, and console command registration.
- [x] Add tests for the result contract: `status`, ISO-8601 `timestamp`, `checks`, HTTP status mapping, and command exit codes.
- [x] Add tests for every available check and disabled-check selection in `HealthCheckService::run()` using Laravel facade/container fakes or mocks.
- [x] Add focused tests for cache and storage cleanup, plus logging output behavior and failure paths.
- [x] Add SQLite database tests and migration status tests using the migrator repository contract.
- [x] Add tests for optional Redis, mail, and Loki checks with mocked services and bounded failure responses.
- [x] Add CI coverage for `composer validate`, `php -l`, and the Pest suite across supported PHP and Laravel versions.

## Correctness and reliability

- [x] Fix migration status so fully migrated applications report `UP` and pending migrations report `DOWN`.
- [x] Fix the PHP extension check to return a top-level `status` and extension details.
- [x] Make `health:check --json` return exit code `0` only for healthy results.
- [x] Use the configured database connection and driver-specific metadata instead of hard-coded `information_schema`.
- [x] Replace the interpolated migration-table SQL with Laravel migration repository APIs or safely quoted identifiers.
- [x] Align Loki configuration with `healthcheck.services.loki_url` and the service implementation.
- [x] Make the queue check perform a lightweight backend operation instead of only reading configuration.
- [x] Catch `Throwable` at individual check boundaries and return consistent `DOWN` results.
- [x] Make disk-space checking portable and configurable, with safe handling of unavailable metrics.
- [x] Use `try/finally` for cache/storage cleanup and check logging write results.
- [x] Normalize and validate health-check configuration groups and supported check keys.
- [x] Validate Redis response fields before indexing them.
- [x] Hide detailed check results and raw exception messages from public responses by default; allow explicit opt-in.

## Security and operations

- [x] Add configurable route middleware and throttling; provide a minimal public liveness response and allow trusted monitoring to opt into details.
- [x] Remove automatic PR approval; require explicit maintainer review.
- [x] Use versioned GitHub Action references and restrict workflow permissions.
- [x] Remove PAT usage from release automation and require a protected `release` environment.
- [x] Add a package validation workflow that installs dependencies, validates Composer, syntax-checks PHP, and runs Pest when configured.
