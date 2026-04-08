<?php

namespace ShopWatch\MessengerWorkerRegistry\Tests;

use PHPUnit\Framework\TestCase;
use ShopWatch\MessengerWorkerRegistry\DataCollector\MessengerWorkerDataCollector;
use ShopWatch\MessengerWorkerRegistry\WorkerEntry;
use ShopWatch\MessengerWorkerRegistry\WorkerRegistry;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MessengerWorkerDataCollectorTest extends TestCase
{
    private function createCollector(int $ttl = 120): array
    {
        $registry = new WorkerRegistry(new ArrayAdapter(), $ttl);
        $collector = new MessengerWorkerDataCollector($registry);

        return [$collector, $registry];
    }

    private function createEntry(
        string $id = 'w1',
        array $transports = ['high'],
        ?string $hostname = 'web-01',
        int $handled = 0,
        int $failed = 0,
        array $messageStats = [],
        ?\DateTimeImmutable $stoppedAt = null,
    ): WorkerEntry {
        $now = new \DateTimeImmutable();

        return new WorkerEntry(
            id: $id,
            transportNames: $transports,
            startedAt: $now,
            lastActiveAt: $now,
            messagesHandled: $handled,
            messagesFailed: $failed,
            messageStats: $messageStats,
            stoppedAt: $stoppedAt,
            hostname: $hostname ?? '',
        );
    }

    private function collect(MessengerWorkerDataCollector $collector): void
    {
        $collector->collect(new Request(), new Response());
    }

    public function testNameReturnsCorrectIdentifier(): void
    {
        [$collector] = $this->createCollector();

        $this->assertSame('messenger_workers', $collector->getName());
    }

    public function testTemplateReturnsCorrectPath(): void
    {
        $this->assertSame(
            '@MessengerWorkerRegistry/data_collector/messenger_workers.html.twig',
            MessengerWorkerDataCollector::getTemplate(),
        );
    }

    public function testEmptyRegistryReturnsZeros(): void
    {
        [$collector] = $this->createCollector();
        $this->collect($collector);

        $this->assertSame(0, $collector->getTotal());
        $this->assertSame(0, $collector->getRunning());
        $this->assertSame(0, $collector->getStopped());
        $this->assertSame(0, $collector->getDead());
        $this->assertSame(0, $collector->getTotalHandled());
        $this->assertSame(0, $collector->getTotalFailed());
        $this->assertSame([], $collector->getWorkers());
    }

    public function testRunningWorkersAreCounted(): void
    {
        [$collector, $registry] = $this->createCollector();

        $registry->register($this->createEntry('w1'));
        $registry->register($this->createEntry('w2'));
        $this->collect($collector);

        $this->assertSame(2, $collector->getTotal());
        $this->assertSame(2, $collector->getRunning());
        $this->assertSame(0, $collector->getStopped());
        $this->assertSame(0, $collector->getDead());
    }

    public function testStoppedWorkersAreCounted(): void
    {
        [$collector, $registry] = $this->createCollector();

        $registry->register($this->createEntry('w1'));
        $registry->register($this->createEntry('w2'));
        $registry->unregister('w1');
        $this->collect($collector);

        $this->assertSame(2, $collector->getTotal());
        $this->assertSame(1, $collector->getRunning());
        $this->assertSame(1, $collector->getStopped());
    }

    public function testDeadWorkersAreCounted(): void
    {
        [$collector, $registry] = $this->createCollector(60);

        $pastTime = new \DateTimeImmutable('-120 seconds');
        $deadEntry = new WorkerEntry(
            id: 'dead1',
            transportNames: ['high'],
            startedAt: $pastTime,
            lastActiveAt: $pastTime,
            hostname: 'web-01',
        );

        $registry->register($deadEntry);
        $registry->register($this->createEntry('w1'));
        $this->collect($collector);

        $this->assertSame(2, $collector->getTotal());
        $this->assertSame(1, $collector->getRunning());
        $this->assertSame(1, $collector->getDead());
    }

    public function testMessageCountsAreAggregated(): void
    {
        [$collector, $registry] = $this->createCollector();

        $registry->register($this->createEntry('w1', handled: 10, failed: 2));
        $registry->register($this->createEntry('w2', handled: 30, failed: 1));
        $this->collect($collector);

        $this->assertSame(40, $collector->getTotalHandled());
        $this->assertSame(3, $collector->getTotalFailed());
    }

    public function testWorkerDataContainsExpectedFields(): void
    {
        [$collector, $registry] = $this->createCollector();

        $registry->register($this->createEntry(
            id: 'w1',
            transports: ['high', 'medium'],
            hostname: 'web-01',
            handled: 5,
            failed: 1,
            messageStats: ['App\Message\TestMessage' => ['count' => 5, 'failed' => 1, 'totalMs' => 250.0]],
        ));
        $this->collect($collector);

        $workers = $collector->getWorkers();
        $this->assertCount(1, $workers);

        $worker = $workers[0];
        $this->assertSame('w1', $worker['id']);
        $this->assertSame('running', $worker['status']);
        $this->assertSame('web-01', $worker['hostname']);
        $this->assertSame(['high', 'medium'], $worker['transports']);
        $this->assertSame(5, $worker['messagesHandled']);
        $this->assertSame(1, $worker['messagesFailed']);
        $this->assertArrayHasKey('App\Message\TestMessage', $worker['messageStats']);
    }

    public function testTimestampsAreIntegers(): void
    {
        [$collector, $registry] = $this->createCollector();

        $registry->register($this->createEntry('w1'));
        $this->collect($collector);

        $worker = $collector->getWorkers()[0];
        $this->assertIsInt($worker['startedAt']);
        $this->assertIsInt($worker['lastActiveAt']);
        $this->assertNull($worker['stoppedAt']);
    }

    public function testStoppedWorkerHasStoppedAtTimestamp(): void
    {
        [$collector, $registry] = $this->createCollector();

        $registry->register($this->createEntry('w1'));
        $registry->unregister('w1');
        $this->collect($collector);

        $worker = $collector->getWorkers()[0];
        $this->assertIsInt($worker['stoppedAt']);
        $this->assertSame('stopped', $worker['status']);
    }

    public function testTtlReflectsConfiguration(): void
    {
        [$collector] = $this->createCollector(60);
        $this->collect($collector);

        $this->assertSame(60, $collector->getTtl());
    }

    public function testDefaultTtl(): void
    {
        [$collector] = $this->createCollector();
        $this->collect($collector);

        $this->assertSame(120, $collector->getTtl());
    }

    public function testGettersReturnDefaultsBeforeCollect(): void
    {
        [$collector] = $this->createCollector();

        $this->assertSame(0, $collector->getTotal());
        $this->assertSame(0, $collector->getRunning());
        $this->assertSame(0, $collector->getStopped());
        $this->assertSame(0, $collector->getDead());
        $this->assertSame(0, $collector->getTotalHandled());
        $this->assertSame(0, $collector->getTotalFailed());
        $this->assertSame(120, $collector->getTtl());
        $this->assertSame([], $collector->getWorkers());
    }

    public function testMixedWorkerStatuses(): void
    {
        [$collector, $registry] = $this->createCollector(60);

        // Running worker
        $registry->register($this->createEntry('running1'));

        // Stopped worker
        $registry->register($this->createEntry('stopped1'));
        $registry->unregister('stopped1');

        // Dead worker
        $pastTime = new \DateTimeImmutable('-120 seconds');
        $registry->register(new WorkerEntry(
            id: 'dead1',
            transportNames: ['low'],
            startedAt: $pastTime,
            lastActiveAt: $pastTime,
            hostname: 'web-02',
        ));

        $this->collect($collector);

        $this->assertSame(3, $collector->getTotal());
        $this->assertSame(1, $collector->getRunning());
        $this->assertSame(1, $collector->getStopped());
        $this->assertSame(1, $collector->getDead());
    }
}
