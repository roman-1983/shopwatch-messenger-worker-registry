<?php

namespace ShopWatch\MessengerWorkerRegistry\Tests;

use PHPUnit\Framework\TestCase;
use ShopWatch\MessengerWorkerRegistry\WorkerEntry;
use ShopWatch\MessengerWorkerRegistry\WorkerRegistry;
use ShopWatch\MessengerWorkerRegistry\WorkerStatus;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class WorkerRegistryTest extends TestCase
{
    private function createRegistry(int $ttl = 120): WorkerRegistry
    {
        return new WorkerRegistry(new ArrayAdapter(), $ttl);
    }

    private function createEntry(string $id = 'abc123', array $transports = ['high']): WorkerEntry
    {
        $now = new \DateTimeImmutable();

        return new WorkerEntry(
            id: $id,
            transportNames: $transports,
            startedAt: $now,
            lastActiveAt: $now,
        );
    }

    public function testRegisterAndGetAll(): void
    {
        $registry = $this->createRegistry();
        $entry = $this->createEntry('w1', ['high', 'medium']);

        $registry->register($entry);

        $all = $registry->getAll();
        $this->assertCount(1, $all);
        $this->assertSame('w1', $all[0]->id);
        $this->assertSame(['high', 'medium'], $all[0]->transportNames);
    }

    public function testRegisterMultipleWorkers(): void
    {
        $registry = $this->createRegistry();

        $registry->register($this->createEntry('w1', ['high']));
        $registry->register($this->createEntry('w2', ['low']));
        $registry->register($this->createEntry('w3', ['low']));

        $this->assertCount(3, $registry->getAll());
    }

    public function testUnregisterMarksAsStopped(): void
    {
        $registry = $this->createRegistry();

        $registry->register($this->createEntry('w1'));
        $registry->register($this->createEntry('w2'));
        $registry->unregister('w1');

        $all = $registry->getAll();
        $this->assertCount(2, $all);

        $w1 = array_values(array_filter($all, fn (WorkerEntry $e) => $e->id === 'w1'))[0];
        $this->assertNotNull($w1->stoppedAt);
        $this->assertSame(WorkerStatus::Stopped, $w1->getStatus(120));
    }

    public function testHeartbeatUpdatesLastActiveAt(): void
    {
        $registry = $this->createRegistry();
        $entry = $this->createEntry('w1');
        $originalTime = $entry->lastActiveAt;

        $registry->register($entry);

        usleep(10000); // 10ms
        $registry->heartbeat('w1');

        $all = $registry->getAll();
        $this->assertGreaterThan($originalTime, $all[0]->lastActiveAt);
    }

    public function testHeartbeatOnNonExistentWorkerIsNoop(): void
    {
        $registry = $this->createRegistry();
        $registry->heartbeat('nonexistent');

        $this->assertCount(0, $registry->getAll());
    }

    public function testUpdateCounters(): void
    {
        $registry = $this->createRegistry();
        $registry->register($this->createEntry('w1'));

        $registry->update('w1', 42, 3);

        $all = $registry->getAll();
        $this->assertSame(42, $all[0]->messagesHandled);
        $this->assertSame(3, $all[0]->messagesFailed);
    }

    public function testUpdateOnNonExistentWorkerIsNoop(): void
    {
        $registry = $this->createRegistry();
        $registry->update('nonexistent', 1, 0);

        $this->assertCount(0, $registry->getAll());
    }

    public function testGetAllReturnsEmptyWhenNoWorkers(): void
    {
        $registry = $this->createRegistry();
        $this->assertSame([], $registry->getAll());
    }

    public function testUnregisterNonExistentWorkerIsNoop(): void
    {
        $registry = $this->createRegistry();
        $registry->unregister('nonexistent');

        $this->assertCount(0, $registry->getAll());
    }

    public function testDuplicateRegisterDoesNotDuplicateIndex(): void
    {
        $registry = $this->createRegistry();
        $entry = $this->createEntry('w1');

        $registry->register($entry);
        $registry->register($entry);

        $this->assertCount(1, $registry->getAll());
    }

    public function testGetTtlSeconds(): void
    {
        $registry = $this->createRegistry(60);
        $this->assertSame(60, $registry->getTtlSeconds());
    }

    public function testDefaultTtl(): void
    {
        $registry = new WorkerRegistry(new ArrayAdapter());
        $this->assertSame(120, $registry->getTtlSeconds());
    }

    public function testWorkerStatusRunning(): void
    {
        $registry = $this->createRegistry();
        $registry->register($this->createEntry('w1'));

        $all = $registry->getAll();
        $this->assertSame(WorkerStatus::Running, $all[0]->getStatus(120));
    }

    public function testWorkerStatusDead(): void
    {
        $now = new \DateTimeImmutable();
        $pastTime = $now->modify('-200 seconds');

        $entry = new WorkerEntry(
            id: 'w1',
            transportNames: ['high'],
            startedAt: $pastTime,
            lastActiveAt: $pastTime,
        );

        $this->assertSame(WorkerStatus::Dead, $entry->getStatus(120, $now));
    }

    public function testWorkerStatusStopped(): void
    {
        $now = new \DateTimeImmutable();

        $entry = new WorkerEntry(
            id: 'w1',
            transportNames: ['high'],
            startedAt: $now,
            lastActiveAt: $now,
            stoppedAt: $now,
        );

        $this->assertSame(WorkerStatus::Stopped, $entry->getStatus(120, $now));
    }

    public function testHostnamePreserved(): void
    {
        $registry = $this->createRegistry();
        $now = new \DateTimeImmutable();

        $entry = new WorkerEntry(
            id: 'w1',
            transportNames: ['high'],
            startedAt: $now,
            lastActiveAt: $now,
            hostname: 'web-01',
        );

        $registry->register($entry);

        $all = $registry->getAll();
        $this->assertSame('web-01', $all[0]->hostname);
    }
}
