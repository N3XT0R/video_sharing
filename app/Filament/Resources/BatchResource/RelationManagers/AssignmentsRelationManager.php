<?php

namespace App\Filament\Resources\BatchResource\RelationManagers;

use App\Filament\Resources\AssignmentResource;
use App\Models\Assignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';
    protected static ?string $title = 'Assignments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('attempts')->numeric()->sortable(),
                TextColumn::make('expires_at')->dateTime()->since()->sortable(),
                TextColumn::make('last_notified_at')->dateTime()->since()->sortable()->toggleable(),
                TextColumn::make('video.preview_url')
                    ->label('Preview')
                    ->formatStateUsing(fn() => 'Open')
                    ->url(fn(Assignment $assignment) => $assignment->video ? (string)$assignment->video->getAttribute('preview_url') : null)
                    ->openUrlInNewTab(),
                TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Assignment $assignment) => AssignmentResource::getUrl('view', ['record' => $assignment]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('preview')
                    ->label('Open preview')
                    ->icon('heroicon-m-play')
                    ->url(fn (Assignment $assignment) => $assignment->video ? (string)$assignment->video->getAttribute('preview_url') : null)
                    ->visible(fn(Assignment $assignment) => $assignment->video && filled($assignment->video->getAttribute('preview_url')))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }
}
