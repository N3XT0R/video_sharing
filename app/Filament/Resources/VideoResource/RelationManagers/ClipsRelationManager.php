<?php

declare(strict_types=1);

namespace App\Filament\Resources\VideoResource\RelationManagers;

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
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('video.original_name')
                    ->label('Video')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('start_sec')->label('Start'),
                Tables\Columns\TextColumn::make('end_sec')->label('End'),
                Tables\Columns\TextColumn::make('submitted_by')->label('Submitted By'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->headerActions([])
            ->actions([Tables\Actions\ViewAction::make()])
            ->bulkActions([]);
    }
}