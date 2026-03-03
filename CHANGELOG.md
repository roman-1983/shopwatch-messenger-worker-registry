# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

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
