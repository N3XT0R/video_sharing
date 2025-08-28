<?php

namespace App\Filament\Widgets;

use App\Models\Clip;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class UploadStatsChart extends ChartWidget
{
    protected ?string $heading = 'Uploads per Submitter';

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

        $rows = Clip::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('submitted_by', DB::raw('count(*) as count'))
            ->groupBy('submitted_by')
            ->orderByDesc('count')
            ->get();

        $labels = $rows->map(fn($row) => $row->submitted_by ?? 'Unknown')->all();
        $data = $rows->map(fn($row) => (int) $row->count)->all();

        return [
            'datasets' => [
                [
                    'label' => 'Uploads',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
