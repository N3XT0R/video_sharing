<?php

namespace App\Filament\Resources\Batches;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use App\Filament\Resources\Batches\Pages\ListBatches;
use App\Filament\Resources\Batches\Pages\ViewBatch;
use App\Filament\Resources\BatchResource\Pages;
use App\Filament\Resources\Batches\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Batches\RelationManagers\ChannelsRelationManager;
use App\Filament\Resources\Batches\RelationManagers\ClipsRelationManager;
use App\Models\Batch;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-queue-list';
    protected static string | \UnitEnum | null $navigationGroup = 'System';
    protected static ?string $modelLabel = 'Batch';
    protected static ?string $pluralModelLabel = 'Batches';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('id')->disabled(),
            TextInput::make('type')->disabled(),
            DateTimePicker::make('started_at')->disabled(),
            DateTimePicker::make('finished_at')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('stats')->formatStateUsing(function (Batch $record) {
                    $stats = (array)($record->getAttribute('stats') ?? []);
                    $lines = collect($stats)->map(function ($val, $key) {
                        if (is_bool($val)) {
                            $val = $val ? 'true' : 'false';
                        }
                        if ($val === null) {
                            $val = 'null';
                        }
                        return $key.': '.(string)$val;
                    })->implode(', ');
                    return $lines;
                }),
                TextColumn::make('started_at')->dateTime()->since()->sortable(),
                TextColumn::make('finished_at')->dateTime()->since()->sortable()->toggleable(),
                TextColumn::make('assignments_count')
                    ->counts('assignments')
                    ->label('Assignments'),
            ])
            ->filters([]);
    }

    public static function getRelations(): array
    {
        return [
            AssignmentsRelationManager::class,
            ClipsRelationManager::class,
            ChannelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatches::route('/'),
            'view' => ViewBatch::route('/{record}'),
        ];
    }
}
