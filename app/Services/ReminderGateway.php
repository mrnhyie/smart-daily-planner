<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ReminderGateway
{
    public function sendSms(string $to, string $message, ?string $scheduleTime = null): void
    {
        $normalizedTo = trim($to);
        if (str_starts_with($normalizedTo, '0')) {
            $normalizedTo = '+233' . substr($normalizedTo, 1);
        } elseif (!str_starts_with($normalizedTo, '+')) {
            if (str_starts_with($normalizedTo, '233')) {
                $normalizedTo = '+' . $normalizedTo;
            } else {
                $normalizedTo = '+233' . $normalizedTo;
            }
        }

        if (!str_starts_with($normalizedTo, '+233')) {
            Log::info("Skipping SMS delivery: Recipient [{$normalizedTo}] is not a Ghanaian (+233) number. Email & Push notifications active.");
            return;
        }

        $apiKey = env('AGOO_API_KEY') ?: env('AGOO_SMS_API_KEY') ?: env('AGOOSMS_API_KEY');
        $senderId = env('AGOO_SENDER_ID') ?: env('AGOO_SMS_SENDER_ID', 'SDPLANNER');

        if (empty($apiKey)) {
            // Fallback to local microservice if API key is not configured
            $response = Http::timeout(15)->post($this->baseUrl().'/send-sms', [
                'to'      => $normalizedTo,
                'message' => $message,
            ]);
            $response->throw();
            return;
        }

        $payload = [
            'to'        => $to,
            'message'   => $message,
            'senderId'  => $senderId,
            'sender_id' => $senderId,
        ];

        if ($scheduleTime) {
            $payload['schedule_time'] = $scheduleTime;
            $payload['send_at'] = $scheduleTime;
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'X-API-Key'    => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.agoosms.com/v1/sms/send', $payload);

        $response->throw();
    }

    public function sendEmail(string $to, string $subject, string $message): void
    {
        $fromAddress = config('mail.from.address', 'reminders@smartdailyplanner.com');
        $fromName    = config('mail.from.name', 'Smart Daily Planner');

        $htmlBody = $this->buildEmailHtml($subject, $message);

        Mail::html($htmlBody, function ($mail) use ($to, $subject, $fromAddress, $fromName) {
            $mail->to($to)
                 ->subject($subject)
                 ->from($fromAddress, $fromName);
        });
    }

    protected function buildEmailHtml(string $subject, string $message): string
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$safeSubject}</title>
  </head>
  <body style="margin:0;padding:0;background:#111;font-family:'Inter',Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#111;padding:40px 0;">
      <tr>
        <td align="center">
          <table width="560" cellpadding="0" cellspacing="0" style="background:#1a1a1a;border-radius:16px;border:1px solid rgba(255,214,0,0.25);overflow:hidden;">
            <tr>
              <td style="background:linear-gradient(135deg,#1a1a1a,#222);padding:32px 40px 24px;text-align:center;border-bottom:1px solid rgba(255,214,0,0.15);">
                <p style="margin:0 0 8px;font-size:11px;letter-spacing:0.2em;text-transform:uppercase;color:rgba(255,214,0,0.7);">Smart Daily Planner</p>
                <h1 style="margin:0;font-size:26px;font-weight:800;color:#FFD600;">&#9200; Task Reminder</h1>
              </td>
            </tr>
            <tr>
              <td style="padding:32px 40px;">
                <div style="background:rgba(255,214,0,0.07);border:1px solid rgba(255,214,0,0.2);border-radius:12px;padding:20px 24px;margin:0 0 24px;">
                  <p style="margin:0;font-size:16px;font-weight:600;color:#fff;line-height:1.6;">{$safeMessage}</p>
                </div>
                <p style="margin:0 0 8px;font-size:13px;color:rgba(255,255,255,0.45);line-height:1.6;">
                  Stay focused and make today count. Your plan is set &mdash; now execute it! &#128170;
                </p>
              </td>
            </tr>
            <tr>
              <td style="background:#111;padding:20px 40px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
                <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.25);">
                  Smart Daily Planner &middot; You're receiving this because you enabled email reminders.
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.reminders.base_url'), '/');
    }
}
