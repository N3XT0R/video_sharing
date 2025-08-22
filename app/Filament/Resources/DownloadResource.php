<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DownloadResource\Pages;
use App\Models\Download;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DownloadResource extends Resource
{
    protected static ?string $model = Download::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Media';
    protected static ?string $modelLabel = 'Download';
    protected static ?string $pluralModelLabel = 'Downloads';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
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
                    ->sortable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDownloads::route('/'),
        ];
    }
}
