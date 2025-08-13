<?php

namespace App\Mail;

use App\Facades\Cfg;
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
        $mailTo = (string)Cfg::get('email_admin_mail');
        $offerMail = $this->subject('Neue Videos verfügbar – Batch #'.$this->batch->getKey())
            ->view('emails.new-offer');

        if (!empty($mailTo)) {
            $offerMail = $offerMail
                ->replyTo($mailTo)
                ->bcc($mailTo);
        }

        dd($offerMail);

        return $offerMail;
    }
}
