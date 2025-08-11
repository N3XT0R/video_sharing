<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssignmentResource\Pages;
use App\Models\Assignment;
use App\Models\Video;
use App\Services\LinkService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssignmentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Media';
    protected static ?string $modelLabel = 'Assignment';
    protected static ?string $pluralModelLabel = 'Assignments';

    public static function form(Form $form): Form
    {
        // Read-only form for the "view" page
        return $form->schema([
            Forms\Components\Group::make()->schema([
                Forms\Components\TextInput::make('id')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\TextInput::make('video_id')->disabled(),
                Forms\Components\TextInput::make('channel_id')->disabled(),
                Forms\Components\TextInput::make('batch_id')->disabled(),
                Forms\Components\TextInput::make('attempts')->numeric()->disabled(),
                Forms\Components\DateTimePicker::make('expires_at')->disabled(),
                Forms\Components\DateTimePicker::make('last_notified_at')->disabled(),
                Forms\Components\TextInput::make('download_token')->disabled()->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),

                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->sortable()
                    ->searchable(),

                // Show related video name if you have it; fallback to ID if not.
                TextColumn::make('video.original_name')
                    ->label('Video')
                    ->toggleable()
                    ->limit(40)
                    ->url(function (Assignment $assignment) {
                        $video = $assignment->video;
                        return $video ? VideoResource::getUrl('view', ['record' => $video]) : null;
                    })
                    ->openUrlInNewTab(),

                TextColumn::make('video.preview_url')
                    ->label('Preview')
                    ->formatStateUsing(fn() => 'Open')
                    ->url(fn(Assignment $assignment
                    ) => $assignment->video ? (string)$assignment->video->getAttribute('preview_url') : null)
                    ->openUrlInNewTab(),

                TextColumn::make('expires_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => fn($state) => in_array($state, ['done', 'completed', 'finished'], true),
                        'warning' => fn($state) => in_array($state, ['pending', 'queued'], true),
                        'info' => fn($state) => in_array($state, ['processing', 'running'], true),
                        'danger' => fn($state) => in_array($state, ['failed', 'error', 'expired'], true),
                    ])
                    ->sortable()
                    ->searchable(),

                TextColumn::make('attempts')
                    ->label('Attempts')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('last_notified_at')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('offer_url')
                    ->label('Offer')
                    ->formatStateUsing(fn() => 'Link')
                    ->url(fn(Assignment $assignment): ?string => (
                        $assignment->batch && $assignment->channel && $assignment->expires_at
                    )
                        ? app(LinkService::class)->getOfferUrl(
                            $assignment->batch,
                            $assignment->channel,
                            $assignment->expires_at
                        )
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('download_token')
                    ->label('Token')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                // Distinct status filter
                SelectFilter::make('status')
                    ->options(fn() => Assignment::query()
                        ->whereNotNull('status')
                        ->distinct()
                        ->pluck('status', 'status')
                        ->toArray()),

                // Date range by created_at
                Filter::make('created_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('from'),
                        Forms\Components\DatePicker::make('until')->label('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),

                // Quick "expired" filter
                Filter::make('expired')
                    ->label('Expired')
                    ->query(fn($q) => $q->whereNotNull('expires_at')->where('expires_at', '<', now())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Minimal preview: open the video's preview_url if present
                Tables\Actions\Action::make('preview')
                    ->label('Open preview')
                    ->icon('heroicon-m-play')
                    ->url(function (Assignment $assignment) {
                        $video = $assignment->video;
                        return $video ? (string)$video->getAttribute('preview_url') : null;
                    })
                    ->visible(fn(Assignment $assignment
                    ) => $assignment->video && filled($assignment->video->getAttribute('preview_url'))
                    )
                    ->openUrlInNewTab(),

                // Optional: Download via the video's disk/path using Video::getDisk()
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->url(function (Assignment $assignment) {
                        /**
                         * @var Video $video
                         */
                        $video = $assignment->video;
                        if (!$video) {
                            return null;
                        }

                        $disk = $video->getDisk();
                        $path = (string)$video->getAttribute('path');
                        $ext = (string)$video->getAttribute('ext');
                        $hash = (string)$video->getAttribute('hash');
                        $origin = (string)$video->getAttribute('original_name');
                        $name = $origin !== '' ? $origin : ($hash.($ext !== '' ? '.'.$ext : ''));

                        if ($video->getAttribute('disk') !== 'dropbox') {
                            return $disk->temporaryUrl($path, now()->addMinutes(10), [
                                'ResponseContentDisposition' => 'attachment; filename="'.$name.'"',
                            ]);
                        }

                        return $disk->url($path);
                    })
                    ->visible(fn(Assignment $assignment) => $assignment->video &&
                        filled($assignment->video->getAttribute('path')) &&
                        filled($assignment->video->getAttribute('disk'))
                    )
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        // No nested relations on the Assignment resource by default
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignments::route('/'),
            'view' => Pages\ViewAssignment::route('/{record}'),

            // Enable if you want CRUD:
            // 'create' => Pages\CreateAssignment::route('/create'),
            // 'edit'   => Pages\EditAssignment::route('/{record}/edit'),
        ];
    }
}
