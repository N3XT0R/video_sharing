<?php

namespace App\Mail;

use App\Models\{Batch, Channel};
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewOfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Batch $batch,
        public Channel $channel,
        public string $offerUrl,
        public \Carbon\Carbon $expiresAt,
    ) {
    }

    public function build()
    {
        return $this->subject('Neue Videos verfügbar – Batch #'.$this->batch->id)
            ->view('emails.new-offer');
    }
}
