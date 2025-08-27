<?php

namespace App\Filament\Resources\Assignments;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Filament\Resources\Assignments\Pages\ViewAssignment;
use App\Enum\StatusEnum;
use App\Filament\Resources\AssignmentResource\Pages;
use App\Models\Assignment;
use App\Services\LinkService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssignmentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'Media';
    protected static ?string $modelLabel = 'Assignment';
    protected static ?string $pluralModelLabel = 'Assignments';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()->schema([
                TextInput::make('id')->disabled(),
                TextInput::make('status')->disabled(),
                Select::make('video')->relationship('video', 'original_name')->disabled(),
                Select::make('channel')->relationship('channel', 'name')->disabled(),
                TextInput::make('batch_id')->disabled(),
                TextInput::make('attempts')->numeric()->disabled(),
                DateTimePicker::make('expires_at')->disabled(),
                DateTimePicker::make('last_notified_at')->disabled(),
                TextInput::make('download_token')->disabled()->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => fn($state) => $state === StatusEnum::PICKEDUP->value,
                        'warning' => fn($state) => $state === StatusEnum::QUEUED->value,
                        'info' => fn($state) => $state === StatusEnum::NOTIFIED->value,
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
                    ->schema([
                        DatePicker::make('from')->label('from'),
                        DatePicker::make('until')->label('until'),
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
            ->recordActions([
                ViewAction::make(),
                Action::make('offer')
                    ->label('Open Offer')
                    ->url(fn(Assignment $assignment): ?string => (
                        $assignment->batch && $assignment->channel
                    )
                        ? app(LinkService::class)->getOfferUrl(
                            $assignment->batch,
                            $assignment->channel,
                            Carbon::now()->addDay()
                        )
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        // No nested relations on the Assignment resource by default
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssignments::route('/'),
            'view' => ViewAssignment::route('/{record}'),

            // Enable if you want CRUD:
            // 'create' => Pages\CreateAssignment::route('/create'),
            // 'edit'   => Pages\EditAssignment::route('/{record}/edit'),
        ];
    }
}
