<?php

namespace ShopWatch\MessengerWorkerRegistry;

use Psr\Cache\CacheItemPoolInterface;

final class WorkerRegistry
{
    private const INDEX_KEY = 'messenger_worker_index';
    private const ENTRY_PREFIX = 'messenger_worker_';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $ttlSeconds = 120,
    ) {
    }

    public function getTtlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    public function register(WorkerEntry $entry): void
    {
        $item = $this->cache->getItem(self::ENTRY_PREFIX . $entry->id);
        $item->set($entry);
        $item->expiresAfter($this->ttlSeconds * 2);
        $this->cache->save($item);

        $this->addToIndex($entry->id);
    }

    public function heartbeat(string $workerId): void
    {
        $item = $this->cache->getItem(self::ENTRY_PREFIX . $workerId);

        if (!$item->isHit()) {
            return;
        }

        $entry = $item->get();
        $entry->lastActiveAt = new \DateTimeImmutable();

        $item->set($entry);
        $item->expiresAfter($this->ttlSeconds * 2);
        $this->cache->save($item);
    }

    /**
     * @param array<string, array{count: int, failed: int, totalMs: float}> $messageStats
     */
    public function update(string $workerId, int $handled, int $failed, array $messageStats = []): void
    {
        $item = $this->cache->getItem(self::ENTRY_PREFIX . $workerId);

        if (!$item->isHit()) {
            return;
        }

        $entry = $item->get();
        $entry->messagesHandled = $handled;
        $entry->messagesFailed = $failed;
        $entry->messageStats = $messageStats;
        $entry->lastActiveAt = new \DateTimeImmutable();

        $item->set($entry);
        $item->expiresAfter($this->ttlSeconds * 2);
        $this->cache->save($item);
    }

    public function unregister(string $workerId): void
    {
        $item = $this->cache->getItem(self::ENTRY_PREFIX . $workerId);

        if (!$item->isHit()) {
            return;
        }

        $entry = $item->get();
        $entry->stoppedAt = new \DateTimeImmutable();

        $item->set($entry);
        $item->expiresAfter($this->ttlSeconds);
        $this->cache->save($item);
    }

    /**
     * @return WorkerEntry[]
     */
    public function getAll(): array
    {
        $indexItem = $this->cache->getItem(self::INDEX_KEY);
        $ids = $indexItem->isHit() ? $indexItem->get() : [];

        $entries = [];
        $activeIds = [];

        foreach ($ids as $id) {
            $item = $this->cache->getItem(self::ENTRY_PREFIX . $id);

            if ($item->isHit()) {
                $entries[] = $item->get();
                $activeIds[] = $id;
            }
        }

        // Clean up stale IDs from index
        if (\count($activeIds) !== \count($ids)) {
            $indexItem->set($activeIds);
            $indexItem->expiresAfter(null);
            $this->cache->save($indexItem);
        }

        return $entries;
    }

    private function addToIndex(string $workerId): void
    {
        $item = $this->cache->getItem(self::INDEX_KEY);
        $ids = $item->isHit() ? $item->get() : [];

        if (!\in_array($workerId, $ids, true)) {
            $ids[] = $workerId;
        }

        $item->set($ids);
        $item->expiresAfter(null);
        $this->cache->save($item);
    }
}
