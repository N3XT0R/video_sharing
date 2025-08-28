<?php

namespace App\Filament\Widgets;

use App\Models\Assignment;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AssignmentStatusChart extends ChartWidget
{
    protected ?string $heading = 'Assignments by Channel';

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

        $rows = Assignment::query()
            ->join('channels', 'assignments.channel_id', '=', 'channels.id')
            ->whereBetween('assignments.created_at', [$from, $to])
            ->select('channels.name as channel', DB::raw('status, count(*) as count'))
            ->groupBy('channels.name', 'status')
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[$row->channel][$row->status] = (int)$row->count;
            $stats[$row->channel]['total'] = ($stats[$row->channel]['total'] ?? 0) + (int)$row->count;
        }

        $labels = array_keys($stats);

        $colors = [
            'picked_up' => '#10b981',
            'notified' => '#3b82f6',
            'rejected' => '#ef4444',
        ];

        $datasets = [];
        foreach (['picked_up', 'notified', 'rejected'] as $status) {
            $color = $colors[$status];
            $datasets[] = [
                'label' => ucfirst(str_replace('_', ' ', $status)),
                'data' => array_map(fn ($channel) => $stats[$channel][$status] ?? 0, $labels),
                'backgroundColor' => $color,
                'borderColor' => $color,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
