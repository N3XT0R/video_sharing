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
        $mailTo = (string)Cfg::get('email_admin_mail', 'email');
        $notification = (bool)Cfg::get('email_get_bcc_notification', 'email');
        $offerMail = $this->subject('Neue Videos verfügbar – Batch #'.$this->batch->getKey())
            ->view('emails.new-offer');

        if (true === $notification && !empty($mailTo)) {
            $offerMail = $offerMail
                ->bcc($mailTo);
        }

        return $offerMail;
    }
}
