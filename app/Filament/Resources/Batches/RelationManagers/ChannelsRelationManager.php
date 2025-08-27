<?php

namespace App\Filament\Resources\Batches\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use App\Models\Channel;
use App\Services\LinkService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChannelsRelationManager extends RelationManager
{
    protected static string $relationship = 'channels';
    protected static ?string $title = 'Channels';

    public function table(Table $table): Table
    {
        $linkCallback = function (Channel $channel): string {
            $batch = $this->getOwnerRecord();
            $assignment = $channel->assignments()
                ->where('batch_id', $batch->getKey())
                ->orderBy('expires_at')
                ->first();

            $expireAt = $assignment?->expires_at ?? now()->addDays(1);

            return app(LinkService::class)->getOfferUrl($batch, $channel, $expireAt);
        };

        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->select('channels.*')->distinct();
            })
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')
                    ->label('Channel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assignments_count')
                    ->counts('assignments')
                    ->label('Assignments'),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('offer_link')
                    ->label('Open Offer')
                    ->url($linkCallback)
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }
}
