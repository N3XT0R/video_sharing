<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DownloadDelayChart extends ChartWidget
{
    protected ?string $heading = 'Download Delay';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'from' => now()->subMonth()->toDateString(),
            'to' => now()->toDateString(),
        ];

        parent::mount();
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('from')->label('Von')->required(),
                DatePicker::make('to')->label('Bis')->required(),
            ]);
    }

    protected function getData(): array
    {
        $from = $this->filters['from'] ?? now()->subMonth()->toDateString();
        $to = $this->filters['to'] ?? now()->toDateString();

        $rows = DB::table('downloads')
            ->join('assignments', 'downloads.assignment_id', '=', 'assignments.id')
            ->whereBetween('downloads.downloaded_at', [$from, $to])
            ->whereNotNull('assignments.last_notified_at')
            ->get(['downloads.downloaded_at', 'assignments.last_notified_at']);

        $diffs = [];
        foreach ($rows as $row) {
            $downloadedAt = Carbon::parse($row->downloaded_at);
            $notifiedAt = Carbon::parse($row->last_notified_at);
            $diffs[] = $notifiedAt->diffInMinutes($downloadedAt);
        }

        $avg = count($diffs) ? round(array_sum($diffs) / count($diffs), 2) : 0;
        sort($diffs);
        $median = 0;
        $count = count($diffs);
        if ($count) {
            $middle = intdiv($count, 2);
            if ($count % 2) {
                $median = $diffs[$middle];
            } else {
                $median = round(($diffs[$middle - 1] + $diffs[$middle]) / 2, 2);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Minutes',
                    'data' => [$avg, $median],
                    'borderColor' => ['#3b82f6', '#f59e0b'],
                ],
            ],
            'labels' => ['Average', 'Median'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
