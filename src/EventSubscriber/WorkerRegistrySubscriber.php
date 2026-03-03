<?php

namespace ShopWatch\MessengerWorkerRegistry\EventSubscriber;

use ShopWatch\MessengerWorkerRegistry\WorkerEntry;
use ShopWatch\MessengerWorkerRegistry\WorkerRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;

#[AsEventListener(event: WorkerStartedEvent::class, method: 'onWorkerStarted')]
#[AsEventListener(event: WorkerRunningEvent::class, method: 'onWorkerRunning')]
#[AsEventListener(event: WorkerStoppedEvent::class, method: 'onWorkerStopped')]
#[AsEventListener(event: WorkerMessageReceivedEvent::class, method: 'onMessageReceived')]
#[AsEventListener(event: WorkerMessageHandledEvent::class, method: 'onMessageHandled')]
#[AsEventListener(event: WorkerMessageFailedEvent::class, method: 'onMessageFailed')]
final class WorkerRegistrySubscriber
{
    private const HEARTBEAT_INTERVAL_SECONDS = 30;

    private ?string $workerId = null;
    private int $messagesHandled = 0;
    private int $messagesFailed = 0;
    /** @var array<string, array{count: int, failed: int, totalMs: float}> */
    private array $messageStats = [];
    private float $lastHeartbeat = 0;
    private ?float $messageReceivedAt = null;
    private ?string $currentMessageClass = null;

    public function __construct(
        private readonly WorkerRegistry $registry,
    ) {
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $this->workerId = bin2hex(random_bytes(8));
        $now = new \DateTimeImmutable();

        $entry = new WorkerEntry(
            id: $this->workerId,
            transportNames: $event->getWorker()->getMetadata()->getTransportNames(),
            startedAt: $now,
            lastActiveAt: $now,
            hostname: (string) gethostname(),
        );

        $this->registry->register($entry);
        $this->lastHeartbeat = microtime(true);
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->workerId === null) {
            return;
        }

        $elapsed = microtime(true) - $this->lastHeartbeat;

        if ($elapsed >= self::HEARTBEAT_INTERVAL_SECONDS) {
            $this->registry->heartbeat($this->workerId);
            $this->lastHeartbeat = microtime(true);
        }
    }

    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        if ($this->workerId === null) {
            return;
        }

        $this->registry->unregister($this->workerId);
        $this->workerId = null;
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->messageReceivedAt = microtime(true);
        $this->currentMessageClass = $event->getEnvelope()->getMessage()::class;
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        if ($this->workerId === null) {
            return;
        }

        ++$this->messagesHandled;
        $this->recordMessageStats(false);
        $this->flushToRegistry();
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($this->workerId === null) {
            return;
        }

        ++$this->messagesFailed;
        $this->recordMessageStats(true);
        $this->flushToRegistry();
    }

    private function recordMessageStats(bool $failed): void
    {
        $class = $this->currentMessageClass;

        if ($class === null) {
            return;
        }

        // Use short class name for readability
        $shortName = substr($class, (int) strrpos($class, '\\') + 1);

        if (!isset($this->messageStats[$shortName])) {
            $this->messageStats[$shortName] = ['count' => 0, 'failed' => 0, 'totalMs' => 0.0];
        }

        ++$this->messageStats[$shortName]['count'];

        if ($failed) {
            ++$this->messageStats[$shortName]['failed'];
        }

        if ($this->messageReceivedAt !== null) {
            $durationMs = (microtime(true) - $this->messageReceivedAt) * 1000;
            $this->messageStats[$shortName]['totalMs'] += $durationMs;
        }

        $this->messageReceivedAt = null;
        $this->currentMessageClass = null;
    }

    private function flushToRegistry(): void
    {
        $this->registry->update(
            $this->workerId,
            $this->messagesHandled,
            $this->messagesFailed,
            $this->messageStats,
        );
        $this->lastHeartbeat = microtime(true);
    }
}
