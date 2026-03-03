# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-03-04

### Added

- Worker status tracking: workers now show as `running`, `stopped`, or `dead`
- `WorkerStatus` enum for programmatic status access
- `stoppedAt` timestamp on `WorkerEntry` for graceful shutdown tracking
- `getStatus()` method on `WorkerEntry` for status resolution
- `hostname` field on `WorkerEntry` — automatically set via `gethostname()`
- Host column in `messenger:worker:list` table output
- Status column in `messenger:worker:list` table output with colored indicators
- `status` and `hostname` fields in JSON output
- Configurable TTL via bundle configuration (`messenger_worker_registry.ttl`)
- Symfony DependencyInjection Extension and Configuration classes
- `getTtlSeconds()` getter on `WorkerRegistry`

### Changed

- Graceful worker stop now marks entry as "stopped" instead of deleting it
- Cache TTL extended to 2x configured value; workers become "dead" after 1x TTL
- Stopped workers expire from cache after 1x TTL
- Console summary line now shows status breakdown when mixed statuses exist

## [1.0.0] - 2026-03-03

### Added

- Worker registration via `WorkerStartedEvent` with auto-generated unique ID
- Automatic unregistration via `WorkerStoppedEvent`
- Heartbeat mechanism (every 30s) to keep worker entries alive in cache
- TTL-based auto-expiry (120s) for crashed workers that don't send a stop event
- Message handled/failed counters per worker
- Per-message-type statistics: count, failure count, and total processing time
- `messenger:worker:list` console command with table output
- `--format=json` option for programmatic consumption
- `--detail` / `-d` option for per-message-type breakdown with average processing times
- Stale index cleanup during `getAll()` reads
- Support for Symfony 7.x and 8.x
- 24 unit tests covering registry, event subscriber, and console command
