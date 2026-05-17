<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function sendToUser(User $user, string $title, string $body): void
    {
        if (!$this->isConfigured()) {
            Log::warning('Web push skipped: missing VAPID configuration.');
            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('services.webpush.subject'),
                'publicKey' => (string) config('services.webpush.public_key'),
                'privateKey' => (string) config('services.webpush.private_key'),
            ],
        ]);

        foreach ($subscriptions as $subscription) {
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => '/vite.svg',
                'badge' => '/vite.svg',
                'url' => '/',
            ]);

            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                $payload
            );
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            $dbSub = $subscriptions->firstWhere('endpoint', $endpoint);

            if ($report->isSuccess()) {
                if ($dbSub) {
                    $dbSub->update(['last_used_at' => now()]);
                }

                continue;
            }

            Log::warning('Web push failed', [
                'endpoint' => $endpoint,
                'reason' => $report->getReason(),
            ]);

            if ($dbSub && $report->isSubscriptionExpired()) {
                $dbSub->delete();
            }
        }
    }

    protected function isConfigured(): bool
    {
        return (bool) config('services.webpush.public_key')
            && (bool) config('services.webpush.private_key')
            && (bool) config('services.webpush.subject');
    }
}
