<?php

namespace App\Filament\Resources\Configs;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Group;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use App\Filament\Resources\Configs\Pages\ListConfigs;
use App\Filament\Resources\Configs\Pages\EditConfig;
use App\Filament\Resources\ConfigResource\Pages;
use App\Filament\Support\ConfigFilamentMapper;
use App\Models\Config;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConfigResource extends Resource
{
    protected static ?string $model = Config::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $modelLabel = 'Config';
    protected static ?string $pluralModelLabel = 'Configs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->disabled(),
            Hidden::make('cast_type')
                ->default('string')
                ->reactive()
                ->dehydrated(false),
            Hidden::make('selectable')
                ->default(fn(?Config $record) => $record?->selectable)
                ->dehydrated(false),
            Placeholder::make('cast_type_display')
                ->label('Cast Type')
                ->content(fn(Get $get) => ConfigFilamentMapper::typeLabel($get('cast_type'))),
            Group::make()
                ->schema(fn(Get $get) => ConfigFilamentMapper::valueFormComponents($get('cast_type'), $get('selectable')))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('key_label')
                    ->state(fn(Config $record) => __('configs.keys.'.$record->getAttribute('key')))
                    ->label(__('configs.labels.description')),
                TextColumn::make('value')
                    ->limit(50)
                    ->formatStateUsing(fn($state) => is_array($state) ? json_encode($state) : (string)$state)
                    ->searchable(),
            ])
            ->filters([
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConfigs::route('/'),
            'edit' => EditConfig::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_visible', true);
    }
}
