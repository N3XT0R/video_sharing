<?php

declare(strict_types=1);

namespace App\Filament\Resources\Videos\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ClipsRelationManager extends RelationManager
{
    protected static string $relationship = 'clips';
    protected static ?string $title = 'Clips';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('video.original_name')
                    ->label('Video')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('start_sec')->label('Start'),
                TextColumn::make('end_sec')->label('End'),
                TextColumn::make('submitted_by')->label('Submitted By'),
                TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->headerActions([])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}