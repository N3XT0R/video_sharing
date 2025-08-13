<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enum\ConfigTypeEnum;
use App\Support\ConfigCaster;
use Filament\Forms;
use Filament\Forms\Components\Component;

/**
 * Maps a ConfigTypeEnum (via normalized cast_type) to Filament components.
 */
class ConfigFilamentMapper
{
    /**
     * Return the form components for editing the "value" field,
     * based on the provided cast type (aliases resolved by ConfigCaster).
     *
     * @param  string|null  $castType
     * @return array<int, Component>
     */
    public static function valueFormComponents(?string $castType): array
    {
        return match (ConfigCaster::normalize($castType)) {
            ConfigTypeEnum::BOOL => [
                Forms\Components\Toggle::make('value')
                    ->label('Value')
                    ->required(),
            ],

            ConfigTypeEnum::INT, ConfigTypeEnum::FLOAT => [
                Forms\Components\TextInput::make('value')
                    ->numeric()
                    ->label('Value')
                    ->required(),
            ],

            ConfigTypeEnum::JSON => [
                Forms\Components\KeyValue::make('value')
                    ->label('Value')
                    ->required(),
            ],

            ConfigTypeEnum::STRING => [
                Forms\Components\Textarea::make('value')
                    ->label('Value')
                    ->required()
                    ->columnSpanFull(),
            ],
        };
    }

    /**
     * (Optional) Human-friendly label for the normalized type.
     */
    public static function typeLabel(?string $castType): string
    {
        return ucfirst(ConfigCaster::normalize($castType)->value);
    }
}
