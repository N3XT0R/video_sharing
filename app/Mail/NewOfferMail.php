<?php

namespace App\Mail;

use App\Models\{Batch, Channel};
use Carbon\Carbon;
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
        public Carbon $expiresAt,
        public string $unusedUrl,
    ) {
    }

    public function build(): NewOfferMail
    {
        $mailTo = (string)config('mail.log.email');
        return $this->subject('Neue Videos verfügbar – Batch #'.$this->batch->getKey())
            ->replyTo($mailTo)
            ->bcc($mailTo)
            ->view('emails.new-offer');
    }
}
