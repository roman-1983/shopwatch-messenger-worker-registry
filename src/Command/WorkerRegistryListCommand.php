<?php

namespace ShopWatch\MessengerWorkerRegistry\Command;

use ShopWatch\MessengerWorkerRegistry\WorkerEntry;
use ShopWatch\MessengerWorkerRegistry\WorkerRegistry;
use ShopWatch\MessengerWorkerRegistry\WorkerStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'messenger:worker:list',
    description: 'List all registered Messenger workers',
)]
final class WorkerRegistryListCommand extends Command
{
    public function __construct(
        private readonly WorkerRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json)', 'table')
            ->addOption('detail', 'd', InputOption::VALUE_NONE, 'Show per-message-type statistics');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entries = $this->registry->getAll();
        $format = $input->getOption('format');
        $detail = $input->getOption('detail');

        if ($format === 'json') {
            return $this->renderJson($output, $entries, $detail);
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Worker Registry');

        if (\count($entries) === 0) {
            $io->info('No workers are currently registered.');
            $io->text('Using Docker? Workers in separate containers need a shared cache volume.');
            $io->text('See: https://github.com/roman-1983/messenger-worker-registry#storage');

            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable();
        $ttl = $this->registry->getTtlSeconds();

        $this->renderTable($io, $entries, $now, $ttl);

        if ($detail) {
            $this->renderDetail($io, $entries);
        }

        $running = \count(array_filter($entries, fn (WorkerEntry $e) => $e->getStatus($ttl, $now) === WorkerStatus::Running));
        $total = \count($entries);

        if ($running === $total) {
            $io->success(sprintf('%d worker(s) running', $total));
        } else {
            $io->success(sprintf('%d worker(s) registered (%d running, %d stopped/dead)', $total, $running, $total - $running));
        }

        return Command::SUCCESS;
    }

    /**
     * @param WorkerEntry[] $entries
     */
    private function renderTable(SymfonyStyle $io, array $entries, \DateTimeImmutable $now, int $ttl): void
    {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                substr($entry->id, 0, 8),
                $this->formatStatus($entry->getStatus($ttl, $now)),
                $entry->hostname ?: '-',
                implode(', ', $entry->transportNames),
                $this->formatRelativeTime($entry->startedAt, $now),
                $this->formatRelativeTime($entry->lastActiveAt, $now),
                $entry->messagesHandled,
                $entry->messagesFailed,
            ];
        }

        $io->table(
            ['ID', 'Status', 'Host', 'Transports', 'Started', 'Last Active', 'Handled', 'Failed'],
            $rows,
        );
    }

    /**
     * @param WorkerEntry[] $entries
     */
    private function renderDetail(SymfonyStyle $io, array $entries): void
    {
        foreach ($entries as $entry) {
            if ($entry->messageStats === []) {
                continue;
            }

            $io->section('Worker ' . substr($entry->id, 0, 8) . ' — Message Stats');

            $rows = [];
            foreach ($entry->messageStats as $type => $stats) {
                $avgMs = $stats['count'] > 0 ? $stats['totalMs'] / $stats['count'] : 0;

                $rows[] = [
                    $type,
                    $stats['count'],
                    $stats['failed'],
                    $this->formatDuration($avgMs),
                    $this->formatDuration($stats['totalMs']),
                ];
            }

            // Sort by count descending
            usort($rows, fn (array $a, array $b) => $b[1] <=> $a[1]);

            $io->table(
                ['Message', 'Count', 'Failed', 'Avg Time', 'Total Time'],
                $rows,
            );
        }
    }

    /**
     * @param WorkerEntry[] $entries
     */
    private function renderJson(OutputInterface $output, array $entries, bool $detail): int
    {
        $ttl = $this->registry->getTtlSeconds();
        $now = new \DateTimeImmutable();

        $data = array_map(function (WorkerEntry $e) use ($detail, $ttl, $now) {
            $item = [
                'id' => $e->id,
                'status' => $e->getStatus($ttl, $now)->value,
                'hostname' => $e->hostname,
                'transports' => $e->transportNames,
                'started_at' => $e->startedAt->format('c'),
                'last_active_at' => $e->lastActiveAt->format('c'),
                'messages_handled' => $e->messagesHandled,
                'messages_failed' => $e->messagesFailed,
            ];

            if ($detail) {
                $stats = [];
                foreach ($e->messageStats as $type => $s) {
                    $stats[$type] = [
                        'count' => $s['count'],
                        'failed' => $s['failed'],
                        'avg_ms' => $s['count'] > 0 ? round($s['totalMs'] / $s['count'], 1) : 0,
                        'total_ms' => round($s['totalMs'], 1),
                    ];
                }
                $item['message_stats'] = $stats;
            }

            return $item;
        }, $entries);

        $output->writeln(json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    private function formatStatus(WorkerStatus $status): string
    {
        return match ($status) {
            WorkerStatus::Running => '<fg=green>running</>',
            WorkerStatus::Stopped => '<fg=yellow>stopped</>',
            WorkerStatus::Dead => '<fg=red>dead</>',
        };
    }

    private function formatRelativeTime(\DateTimeImmutable $time, \DateTimeImmutable $now): string
    {
        $diff = $now->getTimestamp() - $time->getTimestamp();

        if ($diff < 1) {
            return 'just now';
        }

        if ($diff < 60) {
            return $diff . 's ago';
        }

        if ($diff < 3600) {
            return (int) ($diff / 60) . ' min ago';
        }

        if ($diff < 86400) {
            $hours = (int) ($diff / 3600);
            $minutes = (int) (($diff % 3600) / 60);

            return $hours . 'h ' . $minutes . 'min ago';
        }

        return $time->format('Y-m-d H:i');
    }

    private function formatDuration(float $ms): string
    {
        if ($ms < 1) {
            return '<1ms';
        }

        if ($ms < 1000) {
            return round($ms) . 'ms';
        }

        if ($ms < 60000) {
            return number_format($ms / 1000, 1) . 's';
        }

        $minutes = (int) ($ms / 60000);
        $seconds = ($ms % 60000) / 1000;

        return $minutes . 'min ' . number_format($seconds, 0) . 's';
    }
}
