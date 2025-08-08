<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChannelAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Channel $channel, public array $links)
    {
    }

    public function build()
    {
        return $this->subject('Neue Videos für dich')
            ->view('emails.channel_assignments');
    }
}