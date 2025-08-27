<?php

namespace App\Filament\Resources\Downloads;

use Filament\Schemas\Schema;
use App\Filament\Resources\Downloads\Pages\ListDownloads;
use App\Filament\Resources\DownloadResource\Pages;
use App\Models\Download;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DownloadResource extends Resource
{
    protected static ?string $model = Download::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static string | \UnitEnum | null $navigationGroup = 'Media';
    protected static ?string $modelLabel = 'Download';
    protected static ?string $pluralModelLabel = 'Downloads';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('downloaded_at', 'desc')
            ->columns([
                TextColumn::make('assignment.id')
                    ->label('Assignment ID')
                    ->sortable(),
                TextColumn::make('assignment.status')
                    ->label('Status')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('ip')
                    ->url(function (Download $download) {
                        return sprintf('https://utrace.me/?query=%s', $download->getAttribute('ip'));
                    }, true)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('assignment.channel.name')
                    ->label('Channel')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('assignment.video.original_name')
                    ->url(function (Download $download) {
                        return $download->getAttribute('assignment')->getAttribute('video')->getAttribute('preview_url');
                    }, true)
                    ->label('Video')
                    ->sortable(),
                TextColumn::make('downloaded_at')
                    ->label('Downloaded at')
                    ->dateTime()
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDownloads::route('/'),
        ];
    }
}
