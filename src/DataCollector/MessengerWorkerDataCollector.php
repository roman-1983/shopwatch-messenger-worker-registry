<?php

namespace ShopWatch\MessengerWorkerRegistry\DataCollector;

use ShopWatch\MessengerWorkerRegistry\WorkerEntry;
use ShopWatch\MessengerWorkerRegistry\WorkerRegistry;
use ShopWatch\MessengerWorkerRegistry\WorkerStatus;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MessengerWorkerDataCollector extends AbstractDataCollector
{
    public function __construct(
        private readonly WorkerRegistry $registry,
    ) {
    }

    public static function getTemplate(): ?string
    {
        return '@MessengerWorkerRegistry/data_collector/messenger_workers.html.twig';
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $ttl = $this->registry->getTtlSeconds();
        $workers = $this->registry->getAll();

        $workerData = [];
        $running = 0;
        $stopped = 0;
        $dead = 0;
        $totalHandled = 0;
        $totalFailed = 0;

        foreach ($workers as $entry) {
            $status = $entry->getStatus($ttl);

            match ($status) {
                WorkerStatus::Running => $running++,
                WorkerStatus::Stopped => $stopped++,
                WorkerStatus::Dead => $dead++,
            };

            $totalHandled += $entry->messagesHandled;
            $totalFailed += $entry->messagesFailed;

            $workerData[] = [
                'id' => $entry->id,
                'status' => $status->value,
                'hostname' => $entry->hostname,
                'transports' => $entry->transportNames,
                'startedAt' => $entry->startedAt->getTimestamp(),
                'lastActiveAt' => $entry->lastActiveAt->getTimestamp(),
                'stoppedAt' => $entry->stoppedAt?->getTimestamp(),
                'messagesHandled' => $entry->messagesHandled,
                'messagesFailed' => $entry->messagesFailed,
                'messageStats' => $entry->messageStats,
            ];
        }

        $this->data = [
            'workers' => $workerData,
            'running' => $running,
            'stopped' => $stopped,
            'dead' => $dead,
            'total' => \count($workers),
            'totalHandled' => $totalHandled,
            'totalFailed' => $totalFailed,
            'ttl' => $ttl,
        ];
    }

    public function getName(): string
    {
        return 'messenger_workers';
    }

    public function getWorkers(): array
    {
        return $this->data['workers'] ?? [];
    }

    public function getRunning(): int
    {
        return $this->data['running'] ?? 0;
    }

    public function getStopped(): int
    {
        return $this->data['stopped'] ?? 0;
    }

    public function getDead(): int
    {
        return $this->data['dead'] ?? 0;
    }

    public function getTotal(): int
    {
        return $this->data['total'] ?? 0;
    }

    public function getTotalHandled(): int
    {
        return $this->data['totalHandled'] ?? 0;
    }

    public function getTotalFailed(): int
    {
        return $this->data['totalFailed'] ?? 0;
    }

    public function getTtl(): int
    {
        return $this->data['ttl'] ?? 120;
    }
}
