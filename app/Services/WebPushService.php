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
            throw new \Exception('VAPID push server keys are not configured on the backend.');
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            throw new \Exception('No active browser push subscription registered for this device. Please enable Push Notifications.');
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
                'icon' => url('/SDP-logo.png'),
                'badge' => url('/SDP-logo.png'),
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

        $successCount = 0;
        $failureReasons = [];

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            $dbSub = $subscriptions->firstWhere('endpoint', $endpoint);

            if ($report->isSuccess()) {
                $successCount++;
                if ($dbSub) {
                    $dbSub->update(['last_used_at' => now()]);
                }
                continue;
            }

            $reason = $report->getReason();
            $failureReasons[] = $reason;
            Log::warning('Web push failed', [
                'endpoint' => $endpoint,
                'reason' => $reason,
            ]);

            if ($dbSub && ($report->isSubscriptionExpired() || str_contains($reason, 'VapidPkHashMismatch') || str_contains($reason, 'Bad Request') || str_contains($reason, '400') || str_contains($reason, '403'))) {
                $dbSub->delete();
                Log::info('Purged invalid/mismatched push subscription', ['endpoint' => $endpoint, 'reason' => $reason]);
            }
        }

        if ($successCount === 0) {
            throw new \Exception('Push delivery failed across endpoints: ' . implode('; ', $failureReasons));
        }
    }

    public function sendToAllSubscriptions(string $title, string $body, string $target = 'ALL'): array
    {
        if (!$this->isConfigured()) {
            Log::warning('Web push skipped: missing VAPID configuration.');
            return ['success' => false, 'error' => 'Missing VAPID configuration on backend'];
        }

        $query = PushSubscription::query();
        if ($target === 'PREMIUM') {
            $query->whereHas('user', function ($q) {
                $q->where('subscribed', true);
            });
        } elseif ($target === 'FREE') {
            $query->where(function ($q) {
                $q->whereNull('user_id')->orWhereHas('user', function ($q2) {
                    $q2->where('subscribed', false);
                });
            });
        }

        $subscriptions = $query->get();
        if ($subscriptions->isEmpty()) {
            return ['success' => true, 'dispatched_count' => 0, 'message' => 'No matching push subscriptions found for this target segment.'];
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
                'body'  => $body,
                'icon'  => url('/SDP-logo.png'),
                'badge' => url('/SDP-logo.png'),
                'url'   => '/planner',
            ]);

            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $subscription->endpoint,
                    'publicKey'       => $subscription->public_key,
                    'authToken'       => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                $payload
            );
        }

        $successCount = 0;
        $failureReasons = [];
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            $dbSub = $subscriptions->firstWhere('endpoint', $endpoint);

            if ($report->isSuccess()) {
                $successCount++;
                if ($dbSub) {
                    $dbSub->update(['last_used_at' => now()]);
                }
                continue;
            }

            $reason = $report->getReason();
            $failureReasons[] = $reason;
            Log::warning('Web push broadcast failed', [
                'endpoint' => $endpoint,
                'reason'   => $reason,
            ]);

            if ($dbSub && ($report->isSubscriptionExpired() || str_contains($reason, 'VapidPkHashMismatch') || str_contains($reason, 'Bad Request') || str_contains($reason, '400') || str_contains($reason, '403'))) {
                $dbSub->delete();
            }
        }

        return [
            'success'          => $successCount > 0 || empty($failureReasons),
            'dispatched_count' => $successCount,
            'total_targeted'   => $subscriptions->count(),
            'errors'           => $failureReasons
        ];
    }

    protected function isConfigured(): bool
    {
        return (bool) config('services.webpush.public_key')
            && (bool) config('services.webpush.private_key')
            && (bool) config('services.webpush.subject');
    }
}
