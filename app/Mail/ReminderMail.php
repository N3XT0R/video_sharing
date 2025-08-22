<?php

namespace App\Mail;

use App\Facades\Cfg;
use App\Models\Channel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Channel $channel,
        public string $offerUrl,
        public Carbon $expiresAt,
        public Collection $assignments,
    ) {
    }

    public function build(): ReminderMail
    {
        $mailTo = (string)Cfg::get('email_admin_mail', 'email');
        $notification = (bool)Cfg::get('email_get_bcc_notification', 'email');
        $reminder = $this->subject('Erinnerung: Angebote laufen bald ab')
            ->view('emails.reminder');

        if (true === $notification && !empty($mailTo)) {
            $reminder = $reminder->bcc($mailTo);
        }

        return $reminder;
    }
}
