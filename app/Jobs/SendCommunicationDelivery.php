<?php

namespace App\Jobs;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationRule;
use App\Models\PlatformSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCommunicationDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('communications');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $delivery = CommunicationDelivery::find($this->deliveryId);
        if (! $delivery || ! in_array($delivery->status, ['queued', 'retrying'], true)) return;

        $rule = CommunicationRule::where('event_key', $delivery->event_key)->first();
        if (PlatformSetting::valueFor('communications.email_enabled', '0') !== '1'
            || ! $rule?->is_enabled || ! $rule?->send_email) {
            $delivery->update(['status' => 'skipped', 'last_error' => 'Email disabled by platform configuration']);
            return;
        }

        // A later switch to test mode must never release previously queued
        // production-recipient mail. Apply the current allowlist at send time.
        if (PlatformSetting::valueFor('communications.test_mode', '1') === '1') {
            $allowed = array_filter(array_map('trim', explode(',',
                PlatformSetting::valueFor('communications.test_recipients', ''))));
            if (! in_array($delivery->recipient_email, $allowed, true)) {
                $delivery->update([
                    'status' => 'skipped',
                    'last_error' => 'Recipient is outside the current test allowlist',
                ]);
                return;
            }
        }

        $changed = CommunicationDelivery::whereKey($delivery->id)
            ->whereIn('status', ['queued', 'retrying'])
            ->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1]);
        if (! $changed) return;
        $delivery->refresh();

        try {
            // Mail transport reports acceptance, not mailbox delivery.
            Mail::html($delivery->body_html, function ($message) use ($delivery) {
                $message->to($delivery->recipient_email)->subject($delivery->subject);
            });
            $mailer = (string) config('mail.default', 'log');
            $delivery->update([
                'status' => $mailer === 'log' || $mailer === 'array' ? 'logged' : 'accepted',
                'transport' => $mailer,
                'sent_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $delivery->update([
                'status' => 'retrying',
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        CommunicationDelivery::whereKey($this->deliveryId)
            ->whereIn('status', ['queued','retrying','sending'])
            ->update([
                'status' => 'failed',
                'last_error' => mb_substr((string) $exception?->getMessage(), 0, 1000),
            ]);
    }
}
