<?php

namespace ShopWatch\MessengerWorkerRegistry;

final class WorkerEntry
{
    /**
     * @param array<string, array{count: int, failed: int, totalMs: float}> $messageStats
     */
    public function __construct(
        public readonly string $id,
        public readonly array $transportNames,
        public readonly \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $lastActiveAt,
        public int $messagesHandled = 0,
        public int $messagesFailed = 0,
        public array $messageStats = [],
        public ?\DateTimeImmutable $stoppedAt = null,
        public readonly string $hostname = '',
    ) {
    }

    public function getStatus(int $ttlSeconds, ?\DateTimeImmutable $now = null): WorkerStatus
    {
        if ($this->stoppedAt !== null) {
            return WorkerStatus::Stopped;
        }

        $now ??= new \DateTimeImmutable();
        $elapsed = $now->getTimestamp() - $this->lastActiveAt->getTimestamp();

        if ($elapsed > $ttlSeconds) {
            return WorkerStatus::Dead;
        }

        return WorkerStatus::Running;
    }
}
