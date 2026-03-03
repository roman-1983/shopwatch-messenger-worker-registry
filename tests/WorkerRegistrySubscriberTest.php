<?php

namespace ShopWatch\MessengerWorkerRegistry\Tests;

use PHPUnit\Framework\TestCase;
use ShopWatch\MessengerWorkerRegistry\EventSubscriber\WorkerRegistrySubscriber;
use ShopWatch\MessengerWorkerRegistry\WorkerRegistry;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Messenger\WorkerMetadata;

class WorkerRegistrySubscriberTest extends TestCase
{
    private WorkerRegistry $registry;
    private WorkerRegistrySubscriber $subscriber;

    protected function setUp(): void
    {
        $this->registry = new WorkerRegistry(new ArrayAdapter());
        $this->subscriber = new WorkerRegistrySubscriber($this->registry);
    }

    private function createWorkerMock(array $transports = ['high']): Worker
    {
        $metadata = new WorkerMetadata(['transportNames' => $transports]);

        $worker = $this->createStub(Worker::class);
        $worker->method('getMetadata')->willReturn($metadata);

        return $worker;
    }

    private function simulateMessage(object $message, bool $fail = false): void
    {
        $envelope = new Envelope($message);
        $this->subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'high'));

        if ($fail) {
            $this->subscriber->onMessageFailed(new WorkerMessageFailedEvent($envelope, 'high', new \RuntimeException('test')));
        } else {
            $this->subscriber->onMessageHandled(new WorkerMessageHandledEvent($envelope, 'high'));
        }
    }

    public function testWorkerStartedRegistersEntry(): void
    {
        $worker = $this->createWorkerMock(['high', 'medium']);
        $this->subscriber->onWorkerStarted(new WorkerStartedEvent($worker));

        $entries = $this->registry->getAll();
        $this->assertCount(1, $entries);
        $this->assertSame(['high', 'medium'], $entries[0]->transportNames);
    }

    public function testWorkerStoppedUnregistersEntry(): void
    {
        $worker = $this->createWorkerMock();
        $this->subscriber->onWorkerStarted(new WorkerStartedEvent($worker));
        $this->assertCount(1, $this->registry->getAll());

        $this->subscriber->onWorkerStopped(new WorkerStoppedEvent($worker));
        $this->assertCount(0, $this->registry->getAll());
    }

    public function testMessageHandledIncrementsCounter(): void
    {
        $worker = $this->createWorkerMock();
        $this->subscriber->onWorkerStarted(new WorkerStartedEvent($worker));

        $this->simulateMessage(new \stdClass());
        $this->simulateMessage(new \stdClass());

        $entries = $this->registry->getAll();
        $this->assertSame(2, $entries[0]->messagesHandled);
        $this->assertSame(0, $entries[0]->messagesFailed);
    }

    public function testMessageFailedIncrementsCounter(): void
    {
        $worker = $this->createWorkerMock();
        $this->subscriber->onWorkerStarted(new WorkerStartedEvent($worker));

        $this->simulateMessage(new \stdClass(), fail: true);

        $entries = $this->registry->getAll();
        $this->assertSame(0, $entries[0]->messagesHandled);
        $this->assertSame(1, $entries[0]->messagesFailed);
    }

    public function testMessageStatsTracksPerType(): void
    {
        $worker = $this->createWorkerMock();
        $this->subscriber->onWorkerStarted(new WorkerStartedEvent($worker));

        $this->simulateMessage(new FakeMessageA());
        $this->simulateMessage(new FakeMessageA());
        $this->simulateMessage(new FakeMessageB());
        $this->simulateMessage(new FakeMessageB(), fail: true);

        $entries = $this->registry->getAll();
        $stats = $entries[0]->messageStats;

        $this->assertArrayHasKey('FakeMessageA', $stats);
        $this->assertArrayHasKey('FakeMessageB', $stats);

        $this->assertSame(2, $stats['FakeMessageA']['count']);
        $this->assertSame(0, $stats['FakeMessageA']['failed']);

        $this->assertSame(2, $stats['FakeMessageB']['count']);
        $this->assertSame(1, $stats['FakeMessageB']['failed']);
    }

    public function testMessageStatsTracksProcessingTime(): void
    {
        $worker = $this->createWorkerMock();
        $this->subscriber->onWorkerStarted(new WorkerStartedEvent($worker));

        $this->simulateMessage(new FakeMessageA());

        $entries = $this->registry->getAll();
        $stats = $entries[0]->messageStats;

        $this->assertArrayHasKey('FakeMessageA', $stats);
        $this->assertGreaterThanOrEqual(0.0, $stats['FakeMessageA']['totalMs']);
    }

    public function testRunningBeforeStartIsNoop(): void
    {
        $worker = $this->createWorkerMock();
        $this->subscriber->onWorkerRunning(new WorkerRunningEvent($worker, false));

        $this->assertCount(0, $this->registry->getAll());
    }

    public function testStoppedBeforeStartIsNoop(): void
    {
        $worker = $this->createWorkerMock();
        $this->subscriber->onWorkerStopped(new WorkerStoppedEvent($worker));

        $this->assertCount(0, $this->registry->getAll());
    }

    public function testMessageHandledBeforeStartIsNoop(): void
    {
        $envelope = new Envelope(new \stdClass());
        $this->subscriber->onMessageHandled(new WorkerMessageHandledEvent($envelope, 'high'));

        $this->assertCount(0, $this->registry->getAll());
    }
}

class FakeMessageA {}
class FakeMessageB {}
