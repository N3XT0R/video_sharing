<?php

namespace App\Filament\Resources\Batches\RelationManagers;

use Filament\Actions\Action;
use App\Filament\Resources\Assignments\AssignmentResource;
use App\Models\Assignment;
use App\Services\LinkService;
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
        $linkCallback = function (Assignment $assignment): string {
            $batch = $this->getOwnerRecord();
            $expireAt = $assignment?->expires_at ?? now()->addDays(1);
            $channel = $assignment->getAttribute('channel');

            return app(LinkService::class)->getOfferUrl($batch, $channel, $expireAt);
        };

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
                    ->url(fn(Assignment $assignment
                    ) => $assignment->video ? (string)$assignment->video->getAttribute('preview_url') : null)
                    ->openUrlInNewTab(),
                TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn(Assignment $assignment) => AssignmentResource::getUrl('view', ['record' => $assignment]))
                    ->openUrlInNewTab(),
                Action::make('offer_link')
                    ->label('Open Offer')
                    ->url($linkCallback)
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }
}
