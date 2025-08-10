<?php

declare(strict_types=1);

namespace App\Filament\Resources\VideoResource\RelationManagers;

use App\Filament\Resources\AssignmentResource;
use App\Models\Assignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    // Eloquent relationship name on Video model
    protected static string $relationship = 'assignments';
    protected static ?string $title = 'Assignments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('attempts')->numeric()->sortable(),
                TextColumn::make('expires_at')->dateTime()->since()->sortable(),
                TextColumn::make('last_notified_at')->dateTime()->since()->sortable()->toggleable(),
                TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->headerActions([]) // read-only
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn(Assignment $assignment) => AssignmentResource::getUrl('view', ['record' => $assignment]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('preview')
                    ->label('Open preview')
                    ->icon('heroicon-m-play')
                    ->url(function (Assignment $assignment) {
                        $video = $assignment->video;
                        return $video ? (string)$video->getAttribute('preview_url') : null;
                    })
                    ->visible(
                        fn(Assignment $assignment
                        ) => $assignment->video && filled($assignment->video->getAttribute('preview_url'))
                    )
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }
}