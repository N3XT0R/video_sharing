<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoResource\Pages;
use App\Filament\Resources\VideoResource\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\VideoResource\RelationManagers\ClipsRelationManager;
use App\Models\Video;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';
    protected static ?string $navigationGroup = 'Media';
    protected static ?string $modelLabel = 'Video';
    protected static ?string $pluralModelLabel = 'Videos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('preview_url')
                    ->label('Preview')
                    // Show just "Open" as the link text (instead of the full URL)
                    ->formatStateUsing(fn() => 'Open')
                    ->url(fn(Video $video) => (string)$video->getAttribute('preview_url'))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('original_name')
                    ->label('Dateiname')
                    ->searchable()
                    ->wrap(false)
                    ->limit(40),

                Tables\Columns\TextColumn::make('ext')
                    ->badge()
                    ->sortable()
                    ->label('Ext'),

                Tables\Columns\TextColumn::make('bytes')
                    ->label('Größe')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ? Number::fileSize((int)$state) : '–'),

                Tables\Columns\TextColumn::make('disk')
                    ->sortable()
                    ->toggleable()
                    ->label('Disk'),

                Tables\Columns\TextColumn::make('path')
                    ->label('Pfad')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->limit(60),

                Tables\Columns\TextColumn::make('assignments_count')
                    ->counts('assignments')
                    ->label('Assignments'),

                Tables\Columns\TextColumn::make('clips_count')
                    ->counts('clips')
                    ->label('Clips'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->sortable()
                    ->label('Erstellt'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('disk')
                    ->label('Disk')
                    ->options(fn() => Video::query()
                        ->select('disk')->whereNotNull('disk')->distinct()->pluck('disk', 'disk')->toArray()),

                Tables\Filters\SelectFilter::make('ext')
                    ->label('Ext')
                    ->options(fn() => Video::query()
                        ->select('ext')->whereNotNull('ext')->distinct()->pluck('ext', 'ext')->toArray()),

                Tables\Filters\SelectFilter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('von'),
                        DatePicker::make('until')->label('bis'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-m-arrow-down-tray'),
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-m-play')
                    ->url(fn(Video $video) => (string)$video->getAttribute('preview_url'))
                    ->openUrlInNewTab()
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListVideos::route('/'),
            'view' => Pages\ViewVideo::route('/{record}'),
            // 'create' => Pages\CreateVideo::route('/create'),
            // 'edit'   => Pages\EditVideo::route('/{record}/edit'),
        ];
    }
}
