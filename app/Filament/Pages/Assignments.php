<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enum\StatusEnum;
use App\Filament\Resources\VideoResource;
use App\Models\Assignment;
use App\Services\LinkService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class Assignments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Media';
    protected static ?string $title = 'Assignments';
    protected static ?string $navigationLabel = 'Assignments';
    protected static string $view = 'filament.admin.pages.assignments';

    public function table(Table $table): Table
    {
        return $table
            ->query(Assignment::query()->with(['video', 'channel', 'batch']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => fn($state) => $state === StatusEnum::PICKEDUP,
                        'warning' => fn($state) => $state === StatusEnum::QUEUED,
                        'info' => fn($state) => $state === StatusEnum::NOTIFIED,
                    ])
                    ->sortable()
                    ->searchable(),

                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('video.original_name')
                    ->label('Video')
                    ->limit(40)
                    ->url(function (Assignment $assignment) {
                        $video = $assignment->video;
                        return $video ? VideoResource::getUrl('view', ['record' => $video]) : null;
                    })
                    ->openUrlInNewTab(),

                TextColumn::make('offer_url')
                    ->label('Offer')
                    ->formatStateUsing(fn() => 'Link')
                    ->url(function (Assignment $assignment): ?string {
                        if ($assignment->batch && $assignment->channel && $assignment->expires_at) {
                            return app(LinkService::class)->getOfferUrl(
                                $assignment->batch,
                                $assignment->channel,
                                Carbon::now()->addYears(10),
                            );
                        }

                        return null;
                    })
                    ->openUrlInNewTab(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(fn() => Assignment::query()
                        ->whereNotNull('status')
                        ->distinct()
                        ->pluck('status', 'status')
                        ->toArray()),
            ]);
    }
}
