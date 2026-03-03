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
    ) {
    }
}
