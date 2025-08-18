<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BatchResource\Pages;
use App\Filament\Resources\BatchResource\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\BatchResource\RelationManagers\ChannelsRelationManager;
use App\Filament\Resources\BatchResource\RelationManagers\ClipsRelationManager;
use App\Models\Batch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $modelLabel = 'Batch';
    protected static ?string $pluralModelLabel = 'Batches';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('id')->disabled(),
            Forms\Components\TextInput::make('type')->disabled(),
            Forms\Components\DateTimePicker::make('started_at')->disabled(),
            Forms\Components\DateTimePicker::make('finished_at')->disabled(),
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
                    return collect($record->getAttribute('stats') ?? [])
                        ->map(fn($val, $key) => $key.': '.$val)
                        ->values()
                        ->all();
                })->listWithLineBreaks(),
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
            'index' => Pages\ListBatches::route('/'),
            'view' => Pages\ViewBatch::route('/{record}'),
        ];
    }
}
