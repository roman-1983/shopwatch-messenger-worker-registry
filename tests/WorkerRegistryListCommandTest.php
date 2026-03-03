<?php

namespace ShopWatch\MessengerWorkerRegistry\Tests;

use PHPUnit\Framework\TestCase;
use ShopWatch\MessengerWorkerRegistry\Command\WorkerRegistryListCommand;
use ShopWatch\MessengerWorkerRegistry\WorkerEntry;
use ShopWatch\MessengerWorkerRegistry\WorkerRegistry;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandTester;

class WorkerRegistryListCommandTest extends TestCase
{
    private WorkerRegistry $registry;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->registry = new WorkerRegistry(new ArrayAdapter());
        $command = new WorkerRegistryListCommand($this->registry);
        $this->tester = new CommandTester($command);
    }

    public function testEmptyRegistryShowsInfoMessage(): void
    {
        $this->tester->execute([]);

        $this->assertStringContainsString('No workers are currently registered', $this->tester->getDisplay());
    }

    public function testListShowsRegisteredWorkers(): void
    {
        $now = new \DateTimeImmutable();
        $this->registry->register(new WorkerEntry('abcd1234ef', ['high', 'medium'], $now, $now, 42, 1));
        $this->registry->register(new WorkerEntry('5678abcdef', ['low'], $now, $now, 8, 0));

        $this->tester->execute([]);
        $display = $this->tester->getDisplay();

        $this->assertStringContainsString('abcd1234', $display);
        $this->assertStringContainsString('high, medium', $display);
        $this->assertStringContainsString('42', $display);
        $this->assertStringContainsString('2 worker(s) active', $display);
    }

    public function testJsonFormat(): void
    {
        $now = new \DateTimeImmutable();
        $this->registry->register(new WorkerEntry('w1', ['high'], $now, $now, 10, 2));

        $this->tester->execute(['--format' => 'json']);
        $output = json_decode($this->tester->getDisplay(), true);

        $this->assertCount(1, $output);
        $this->assertSame('w1', $output[0]['id']);
        $this->assertSame(['high'], $output[0]['transports']);
        $this->assertSame(10, $output[0]['messages_handled']);
        $this->assertSame(2, $output[0]['messages_failed']);
        $this->assertArrayNotHasKey('message_stats', $output[0]);
    }

    public function testDetailShowsMessageStats(): void
    {
        $now = new \DateTimeImmutable();
        $entry = new WorkerEntry('w1', ['high'], $now, $now, 15, 2, [
            'CheckUptimeMessage' => ['count' => 10, 'failed' => 1, 'totalMs' => 5000.0],
            'FetchHtmlMessage' => ['count' => 5, 'failed' => 1, 'totalMs' => 12000.0],
        ]);
        $this->registry->register($entry);

        $this->tester->execute(['--detail' => true]);
        $display = $this->tester->getDisplay();

        $this->assertStringContainsString('CheckUptimeMessage', $display);
        $this->assertStringContainsString('FetchHtmlMessage', $display);
        $this->assertStringContainsString('500ms', $display); // avg 5000/10
        $this->assertStringContainsString('2.4s', $display);  // avg 12000/5
    }

    public function testJsonDetailIncludesMessageStats(): void
    {
        $now = new \DateTimeImmutable();
        $entry = new WorkerEntry('w1', ['high'], $now, $now, 5, 0, [
            'CheckUptimeMessage' => ['count' => 5, 'failed' => 0, 'totalMs' => 2500.0],
        ]);
        $this->registry->register($entry);

        $this->tester->execute(['--format' => 'json', '--detail' => true]);
        $output = json_decode($this->tester->getDisplay(), true);

        $this->assertArrayHasKey('message_stats', $output[0]);
        $stats = $output[0]['message_stats']['CheckUptimeMessage'];
        $this->assertSame(5, $stats['count']);
        $this->assertEquals(500.0, $stats['avg_ms']);
        $this->assertEquals(2500.0, $stats['total_ms']);
    }
}
