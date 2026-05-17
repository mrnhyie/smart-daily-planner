<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ReminderGateway
{
    public function sendSms(string $to, string $message): void
    {
        $apiKey = env('AGOO_SMS_API_KEY');
        $senderId = env('AGOO_SMS_SENDER_ID', 'SDPLANNER');

        if (empty($apiKey)) {
            // Fallback to local microservice if API key is not configured
            $response = Http::timeout(15)->post($this->baseUrl().'/send-sms', [
                'to' => $to,
                'message' => $message,
            ]);
            $response->throw();
            return;
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.agoosms.com/v1/sms/send', [
                'to' => $to,
                'message' => $message,
                'sender_id' => $senderId,
            ]);

        $response->throw();
    }

    public function sendWhatsapp(string $to, string $message): void
    {
        $response = Http::timeout(15)->post($this->baseUrl().'/send-whatsapp', [
            'to' => $to,
            'message' => $message,
        ]);

        $response->throw();
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.reminders.base_url'), '/');
    }
}
