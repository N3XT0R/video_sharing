<?php

namespace App\Filament\Resources\Videos;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Filament\Resources\Videos\Pages\ViewVideo;
use App\Filament\Resources\VideoResource\Pages;
use App\Filament\Resources\Videos\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Videos\RelationManagers\ClipsRelationManager;
use App\Models\Video;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-film';
    protected static string | \UnitEnum | null $navigationGroup = 'Media';
    protected static ?string $modelLabel = 'Video';
    protected static ?string $pluralModelLabel = 'Videos';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('original_name')
                    ->label('Dateiname')
                    ->searchable()
                    ->wrap(false)
                    ->limit(40),

                TextColumn::make('ext')
                    ->badge()
                    ->sortable()
                    ->label('Ext'),

                TextColumn::make('bytes')
                    ->label('Größe')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ? Number::fileSize((int)$state) : '–'),

                TextColumn::make('disk')
                    ->sortable()
                    ->toggleable()
                    ->label('Disk'),
                TextColumn::make('assignments_count')
                    ->counts('assignments')
                    ->label('Assignments'),

                TextColumn::make('clips_count')
                    ->counts('clips')
                    ->label('Clips'),

                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->sortable()
                    ->label('Erstellt'),
            ])
            ->filters([
                SelectFilter::make('disk')
                    ->label('Disk')
                    ->options(fn() => Video::query()
                        ->select('disk')->whereNotNull('disk')->distinct()->pluck('disk', 'disk')->toArray()),

                SelectFilter::make('ext')
                    ->label('Ext')
                    ->options(fn() => Video::query()
                        ->select('ext')->whereNotNull('ext')->distinct()->pluck('ext', 'ext')->toArray()),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('von'),
                        DatePicker::make('until')->label('bis'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-m-play')
                    ->url(fn(Video $video) => (string)$video->getAttribute('preview_url'))
                    ->openUrlInNewTab()
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            AssignmentsRelationManager::class,
            ClipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideos::route('/'),
            'view' => ViewVideo::route('/{record}'),
            // 'create' => Pages\CreateVideo::route('/create'),
            // 'edit'   => Pages\EditVideo::route('/{record}/edit'),
        ];
    }
}
