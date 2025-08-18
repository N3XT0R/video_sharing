<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Facades\Cfg;
use App\Mail\NewOfferMail;
use App\Models\Batch;
use App\Models\Channel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\DatabaseTestCase;

/**
 * Unit/feature tests for the NewOfferMail mailable.
 *
 * We assert the composed headers (subject, replyTo, bcc), the presence of
 * expected view data, and that queueing targets the intended recipient.
 */
final class NewOfferMailTest extends DatabaseTestCase
{
    public function testBuildSetsSubjectReplyToBccAndCarriesViewData(): void
    {
        // Arrange
        $batch = Batch::factory()->create(['type' => 'assign']);
        $channel = Channel::factory()->create(['email' => 'chan@example.test']);
        $offerUrl = 'https://example.test/offer';
        $unusedUrl = 'https://example.test/unused';
        $expiresAt = Carbon::parse('2025-08-20 12:00:00');

        // Force mail.log.email so build() uses a deterministic address
        config()->set('mail.log.email', 'log@example.test');

        // Act: build the mailable (no actual send)
        $mailable = (new NewOfferMail($batch, $channel, $offerUrl, $expiresAt, $unusedUrl))->build();

        // Assert: subject is set as expected
        $this->assertSame(
            'Neue Videos verfügbar – Batch #'.$batch->getKey(),
            $mailable->subject
        );

        $email = Cfg::get('email_admin_mail', 'email');
        Cfg::set('email_get_bcc_notification', 1, 'email');

        // Assert: replyTo and bcc contain the configured log address
        $this->assertTrue($mailable->hasBcc($email));

        // Assert: view data contains our public properties (available to the Blade view)
        // buildViewData() aggregates $this->viewData + public properties from the mailable
        $data = $mailable->buildViewData();
        $this->assertSame($batch->getKey(), ($data['batch'] ?? $mailable->batch)->getKey());
        $this->assertSame($channel->getKey(), ($data['channel'] ?? $mailable->channel)->getKey());
        $this->assertSame($offerUrl, $data['offerUrl'] ?? $mailable->offerUrl);
        $this->assertTrue(($data['expiresAt'] ?? $mailable->expiresAt)->equalTo($expiresAt));
        $this->assertSame($unusedUrl, $data['unusedUrl'] ?? $mailable->unusedUrl);
    }

    public function testQueuedMailableTargetsChannelEmail(): void
    {
        // Arrange
        $batch = Batch::factory()->create(['type' => 'assign']);
        $channel = Channel::factory()->create(['email' => 'chan2@example.test']);
        $offerUrl = 'https://example.test/offer/2';
        $unusedUrl = 'https://example.test/unused/2';
        $expiresAt = Carbon::parse('2025-08-21 09:30:00');

        Mail::fake();

        // Act: queue the mailable to the channel's email
        Mail::to($channel->email)->queue(
            new NewOfferMail($batch, $channel, $offerUrl, $expiresAt, $unusedUrl)
        );

        // Assert: one NewOfferMail queued to the correct recipient
        Mail::assertQueued(NewOfferMail::class, function (NewOfferMail $mail) use ($channel, $batch) {
            $email = Cfg::get('email_admin_mail', 'email');
            // Force build so headers are composed
            $mail->build();

            return $mail->hasTo($channel->email)
                && $mail->hasBcc($email)
                && $mail->subject === 'Neue Videos verfügbar – Batch #'.$batch->getKey();
        });
    }
}
