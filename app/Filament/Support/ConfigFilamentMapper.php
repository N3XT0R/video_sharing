<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use App\Enum\ConfigTypeEnum;
use App\Support\ConfigCaster;
use Filament\Forms;

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
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function valueFormComponents(?string $castType): array
    {
        return match (ConfigCaster::normalize($castType)) {
            ConfigTypeEnum::BOOL => [
                Toggle::make('value')
                    ->label('Value')
                    ->required(),
            ],

            ConfigTypeEnum::INT, ConfigTypeEnum::FLOAT => [
                TextInput::make('value')
                    ->numeric()
                    ->label('Value')
                    ->required(),
            ],

            ConfigTypeEnum::JSON => [
                KeyValue::make('value')
                    ->label('Value')
                    ->required(),
            ],

            ConfigTypeEnum::STRING => [
                Textarea::make('value')
                    ->label('Value')
                    ->required()
                    ->columnSpanFull(),
            ],
        };
    }

    /**
     * Human-friendly label for the normalized type.
     */
    public static function typeLabel(?string $castType): string
    {
        return ucfirst(ConfigCaster::normalize($castType)->value);
    }
}
