<?php

namespace App\Http\Controllers;

use App\Models\{Batch, Channel};
use App\Services\AssignmentService;
use App\Services\LinkService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OfferController extends Controller
{
    public function __construct(private AssignmentService $assignments)
    {
    }

    public function show(Request $req, Batch $batch, Channel $channel)
    {
        $this->ensureValidSignature($req);

        $items = $this->assignments
            ->fetchPending($batch, $channel)
            ->loadMissing('video.clips');

        foreach ($items as $assignment) {
            $assignment->temp_url = $this->assignments->prepareDownload
            (assignment: $assignment,
                skipTracking: Filament::auth()?->check() === true
            );
        }

        /**
         * @var LinkService $linkService
         */
        $linkService = app(LinkService::class);
        $zipPostUrl = $linkService->getZipSelectedUrl($batch, $channel, now()->addHours(6));

        return view('offer.show', compact('batch', 'channel', 'items', 'zipPostUrl'));
    }

    public function showUnused(Request $req, Batch $batch, Channel $channel)
    {
        /**
         * @var LinkService $linkService
         */
        $linkService = app(LinkService::class);
        $this->ensureValidSignature($req);
        $items = $this->assignments->fetchPickedUp($batch, $channel);
        $postUrl = $linkService->getStoreUnusedUrl($batch, $channel, now()->addHours(6));

        return view('offer.unused', compact('batch', 'channel', 'items', 'postUrl'));
    }

    public function storeUnused(Request $req, Batch $batch, Channel $channel): RedirectResponse
    {
        $this->ensureValidSignature($req);
        $validated = $req->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
        ]);

        /**
         * @var Collection $collection
         */
        $collection = collect($validated['assignment_ids'])
            ->filter(static fn($v) => ctype_digit((string)$v))
            ->map(static fn($v) => (int)$v);

        $ids = $collection->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['nothing' => 'Bitte wähle mindestens ein Video aus.']);
        }

        if ($this->assignments->markUnused($batch, $channel, $ids)) {
            return back()->with('success', 'Die ausgewählten Videos wurden wieder freigegeben.');
        }

        return back()->with('error', 'Fehler: Die ausgewählten Videos konnten nicht freigegeben werden.');
    }

    private function ensureValidSignature(Request $req): void
    {
        abort_unless($req->hasValidSignature(), 403);
    }

}
