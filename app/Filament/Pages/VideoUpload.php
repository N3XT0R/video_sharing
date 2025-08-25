<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessUploadedVideo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class VideoUpload extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Video Upload';
    protected static ?string $navigationGroup = 'Media';
    protected static ?string $title = 'Video Upload';
    protected static string $view = 'filament.pages.video-upload';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Repeater::make('clips')
                    ->label('Videos')
                    ->defaultItems(1)
                    ->schema([
                        Forms\Components\FileUpload::make('file')
                            ->label('Video')
                            ->required()
                            ->acceptedFileTypes(['video/*'])
                            ->storeFiles(false),
                        Forms\Components\View::make('clip')
                            ->view('filament.forms.components.clip-selector')
                            ->label('Ausschnitt')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('note')->label('Notiz'),
                        Forms\Components\TextInput::make('bundle_key')->label('Bundle ID'),
                        Forms\Components\TextInput::make('role')->label('Rolle'),
                        Forms\Components\Hidden::make('start_sec')->default(0),
                        Forms\Components\Hidden::make('end_sec')->default(0),
                    ])
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        $user = Auth::user()?->name;

        foreach ($state['clips'] ?? [] as $clip) {
            /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file */
            $file = $clip['file'];
            $stored = $file->store('uploads/tmp');

            ProcessUploadedVideo::dispatch(
                path: storage_path('app/' . $stored),
                originalName: $file->getClientOriginalName(),
                ext: $file->getClientOriginalExtension(),
                start: (int)($clip['start_sec'] ?? 0),
                end: (int)($clip['end_sec'] ?? 0),
                submittedBy: $user,
                note: $clip['note'] ?? null,
                bundleKey: $clip['bundle_key'] ?? null,
                role: $clip['role'] ?? null,
            );
        }

        Notification::make()
            ->title('Videos werden verarbeitet')
            ->success()
            ->send();

        $this->form->fill();
    }
}
