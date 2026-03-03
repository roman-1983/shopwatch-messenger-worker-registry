# Messenger Worker Registry

A Symfony bundle that provides real-time visibility into your Symfony Messenger workers. Track running workers, their transports, message throughput, failure rates, and per-message-type performance — all via a simple console command.

## The Problem

Symfony Messenger has no built-in way to see which workers are currently running. When you start `messenger:consume`, there's no registry, no dashboard, no way to answer: _How many workers are active? Which transports are being consumed? How many messages has each worker processed?_

This bundle solves that by hooking into Messenger's event system and maintaining a worker registry in Symfony's cache.

## Features

- **Zero configuration** — install the bundle and it just works
- **Automatic registration** — workers register themselves on startup via event listeners
- **Heartbeat with TTL** — crashed workers automatically disappear after 120 seconds
- **Message counters** — track handled and failed messages per worker
- **Per-message-type stats** — see count, failure rate, and average processing time per message class
- **Console command** — `messenger:worker:list` with table and JSON output
- **No external dependencies** — uses Symfony's built-in PSR-6 cache (filesystem, Redis, APCu — whatever you have configured)

## Requirements

- PHP 8.2+
- Symfony 7.0+ or 8.0+

## Installation

```bash
composer require roman-1983/messenger-worker-registry
```

If you're not using Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    ShopWatch\MessengerWorkerRegistry\MessengerWorkerRegistryBundle::class => ['all' => true],
];
```

That's it. No configuration needed.

## Usage

### List Running Workers

```bash
bin/console messenger:worker:list
```

```
 Worker Registry
 ===============

 ---------- ------------------------------------- --------- ----------- --------- --------
  ID         Transports                            Started   Last Active  Handled   Failed
 ---------- ------------------------------------- --------- ----------- --------- --------
  a3f21b8e   scheduler_uptime, high, medium        2min ago  5s ago       142       0
  b7c1d9e4   low                                   2min ago  12s ago      8         1
  d9e4f2a1   low                                   2min ago  12s ago      7         0
 ---------- ------------------------------------- --------- ----------- --------- --------

 3 worker(s) active
```

### Detailed View with Per-Message Stats

```bash
bin/console messenger:worker:list --detail
```

Shows an additional breakdown per worker:

```
 Worker a3f21b8e — Message Stats:
 ---------------------- ------- -------- ---------- ------------
  Message                Count   Failed   Avg Time   Total Time
 ---------------------- ------- -------- ---------- ------------
  CheckUptimeMessage     120     0        198ms      23.8s
  FetchHtmlMessage       22      1        1.2s       26.4s
 ---------------------- ------- -------- ---------- ------------
```

### JSON Output

```bash
bin/console messenger:worker:list --format=json
bin/console messenger:worker:list --format=json --detail
```

Returns a JSON array for programmatic consumption:

```json
[
  {
    "id": "a3f21b8e",
    "transports": ["scheduler_uptime", "high", "medium"],
    "started_at": "2026-03-03T14:22:10+00:00",
    "last_active_at": "2026-03-03T14:24:55+00:00",
    "messages_handled": 142,
    "messages_failed": 0,
    "message_stats": {
      "CheckUptimeMessage": {
        "count": 120,
        "failed": 0,
        "avg_ms": 198.3,
        "total_ms": 23796.0
      }
    }
  }
]
```

The `message_stats` key is only included when using `--detail`.

## How It Works

The bundle listens to Symfony Messenger's built-in worker events:

| Event | Action |
|---|---|
| `WorkerStartedEvent` | Registers the worker with a generated ID and its transport list |
| `WorkerRunningEvent` | Sends a heartbeat every 30s to extend the cache TTL |
| `WorkerStoppedEvent` | Removes the worker from the registry |
| `WorkerMessageReceivedEvent` | Starts a timer for processing duration |
| `WorkerMessageHandledEvent` | Increments handled counter, records per-type stats |
| `WorkerMessageFailedEvent` | Increments failed counter, records per-type stats |

### TTL-Based Cleanup

Each worker entry is stored with a **120-second TTL**. The heartbeat (fired every 30 seconds during `WorkerRunningEvent`) extends this TTL. If a worker crashes without triggering `WorkerStoppedEvent`, its entry simply expires from the cache — no manual cleanup needed.

### Storage

The bundle uses Symfony's `cache.app` pool by default. This means it works out of the box with whatever cache adapter you have configured (filesystem, Redis, Memcached, APCu). For multi-server setups, make sure your cache adapter is shared (e.g., Redis).

## Configuration

The bundle works without any configuration. If you want to use a dedicated cache pool instead of `cache.app`, override the service definition:

```yaml
# config/services.yaml
services:
    ShopWatch\MessengerWorkerRegistry\WorkerRegistry:
        arguments:
            $cache: '@cache.pool.worker_registry'
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT License. See [LICENSE](LICENSE) for details.
