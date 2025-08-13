<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfigResource\Pages;
use App\Models\Config;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConfigResource extends Resource
{
    protected static ?string $model = Config::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $modelLabel = 'Config';
    protected static ?string $pluralModelLabel = 'Configs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->disabled(),
            Forms\Components\Hidden::make('cast_type')
                ->dehydrated(false),
            Forms\Components\Placeholder::make('cast_type_display')
                ->label('Cast Type')
                ->content(fn(Get $get) => ucfirst((string)$get('cast_type'))),
            Forms\Components\Group::make()
                ->schema(fn(Get $get) => match ($get('cast_type')) {
                    'boolean' => [Forms\Components\Toggle::make('value')->label('Value')->required()],
                    'integer', 'float' => [Forms\Components\TextInput::make('value')->numeric()->label('Value')->required()],
                    'array' => [Forms\Components\KeyValue::make('value')->label('Value')->required()],
                    default => [Forms\Components\Textarea::make('value')->label('Value')->required()->columnSpanFull()],
                })
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('key_label')
                    ->state(fn(Config $record) => __('configs.keys.'.$record->getAttribute('key')))
                    ->label(__('configs.labels.description')),
                Tables\Columns\TextColumn::make('value')
                    ->limit(50)
                    ->formatStateUsing(fn($state) => is_array($state) ? json_encode($state) : (string)$state)
                    ->searchable(),
            ])
            ->filters([
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConfigs::route('/'),
            'edit' => Pages\EditConfig::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_visible', true);
    }
}
