<?php

namespace App\Services;

use App\Models\ProxyMessageLog;
use App\Models\ProxyRun;
use App\Models\TeacherProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsAppRoutineMessenger
{
    public function sendProxyRun(ProxyRun $proxyRun): array
    {
        return $this->sendPreparedMessages($proxyRun, $this->previewProxyRun($proxyRun));
    }

    public function previewProxyRun(ProxyRun $proxyRun): array
    {
        $proxyRun->load('routine');
        $routine = $proxyRun->routine;
        if (! $routine) {
            return [];
        }

        $day = (string) $proxyRun->day_label;
        $teacherSchedule = $proxyRun->proxy_teacher_schedule ?? [];
        $dayTeachers = collect($teacherSchedule[$day] ?? []);
        $teacherProfiles = TeacherProfile::query()
            ->with('user:id,settings')
            ->whereIn('id', $dayTeachers->pluck('id')->filter()->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy(fn (TeacherProfile $teacher) => (string) $teacher->id);

        return $dayTeachers
            ->filter(fn ($teacher) => $this->teacherHasClass($teacher))
            ->map(function ($teacher) use ($proxyRun, $routine, $teacherProfiles) {
                $teacherProfile = $teacherProfiles->get((string) ($teacher['id'] ?? ''));

                return [
                    'teacherId' => (string) ($teacher['id'] ?? ''),
                    'teacherProfileId' => $teacherProfile?->id,
                    'teacherName' => $teacher['name'] ?? $teacherProfile?->name ?? 'Teacher',
                    'whatsappNumber' => $this->normalizePhone($teacherProfile?->whatsapp_number),
                    'displayNumber' => $teacherProfile?->whatsapp_number,
                    'whatsappEnabled' => (bool) (($teacherProfile?->user?->settings ?? [])['whatsappRoutineUpdates'] ?? true),
                    'hasProxy' => $this->teacherHasProxy($teacher),
                    'message' => $this->buildMessage($proxyRun, $routine->periods ?? [], $teacher),
                ];
            })
            ->sortBy([
                ['hasProxy', 'desc'],
                ['teacherName', 'asc'],
            ])
            ->values()
            ->all();
    }

    public function sendPreparedMessages(ProxyRun $proxyRun, array $messages): array
    {
        $summary = ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'dryRun' => (bool) config('services.whatsapp.dry_run')];
        $batchId = (string) Str::uuid();

        foreach ($messages as $message) {
            $summary['total']++;
            $phone = $this->normalizePhone($message['whatsappNumber'] ?? $message['displayNumber'] ?? null);
            $body = trim((string) ($message['message'] ?? ''));

            $log = ProxyMessageLog::create([
                'proxy_run_id' => $proxyRun->id,
                'teacher_profile_id' => $message['teacherProfileId'] ?? null,
                'teacher_name' => $message['teacherName'] ?? 'Teacher',
                'whatsapp_number' => $phone,
                'status' => 'pending',
                'send_batch_id' => $batchId,
                'message_body' => $body,
            ]);

            if (! $phone) {
                $log->update(['status' => 'skipped', 'error_message' => 'No WhatsApp number is saved for this teacher.']);
                $summary['skipped']++;
                continue;
            }

            if (($message['whatsappEnabled'] ?? true) === false) {
                $log->update(['status' => 'skipped', 'error_message' => 'Teacher has turned off WhatsApp routine updates.']);
                $summary['skipped']++;
                continue;
            }

            if ($body === '') {
                $log->update(['status' => 'skipped', 'error_message' => 'Message body is empty.']);
                $summary['skipped']++;
                continue;
            }

            $result = $this->sendText($phone, $body);
            $log->update([
                'status' => $result['ok'] ? ($result['dryRun'] ? 'dry_run' : 'sent') : 'failed',
                'provider_message_id' => $result['messageId'] ?? null,
                'error_message' => $result['error'] ?? null,
                'sent_at' => $result['ok'] ? now() : null,
            ]);

            if ($result['ok']) {
                $summary[$result['dryRun'] ? 'skipped' : 'sent']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function teacherHasClass(array $teacher): bool
    {
        return collect($teacher['cells'] ?? [])
            ->contains(fn ($cell) => in_array($cell['type'] ?? '', ['class', 'proxy', 'unresolved'], true));
    }

    private function teacherHasProxy(array $teacher): bool
    {
        return collect($teacher['cells'] ?? [])
            ->contains(fn ($cell) => ! empty($cell['proxyChanged']) || ($cell['type'] ?? '') === 'proxy');
    }

    private function buildMessage(ProxyRun $proxyRun, array $periods, array $teacher): string
    {
        $dayLabel = trim(($proxyRun->day_label ?? '').($proxyRun->date ? ' - '.$proxyRun->date->format('d/m/y') : ''));
        $lines = [
            'Your schedule for the day:',
            $dayLabel,
            '',
        ];

        foreach ($periods as $period) {
            $key = (string) ($period['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $label = $period['label'] ?? strtoupper($key);
            $cell = $teacher['cells'][$key] ?? null;

            if (($period['type'] ?? 'class') !== 'class') {
                continue;
            }

            if (! $cell || ! in_array($cell['type'] ?? '', ['class', 'proxy', 'unresolved'], true)) {
                continue;
            }

            $class = $cell['classLabel'] ?? 'Class';
            $subject = $cell['subject'] ?? 'Subject';
            $proxySuffix = ! empty($cell['proxyChanged']) || ($cell['type'] ?? '') === 'proxy'
                ? ' (SUBSTITUTION CLASS)'
                : '';

            $lines[] = $label.': '.$class.' - '.$subject.$proxySuffix;
        }

        return implode("\n", $lines);
    }

    private function sendText(string $phone, string $message): array
    {
        if ((bool) config('services.whatsapp.dry_run')) {
            return ['ok' => true, 'dryRun' => true];
        }

        $token = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $apiVersion = config('services.whatsapp.api_version', 'v21.0');

        if (! $token || ! $phoneNumberId) {
            return ['ok' => false, 'dryRun' => false, 'error' => 'WhatsApp Cloud API is not configured.'];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(25)
                ->retry(2, 500, throw: false)
                ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]);
        } catch (ConnectionException $error) {
            return [
                'ok' => false,
                'dryRun' => false,
                'error' => 'Could not connect to WhatsApp Cloud API: '.$error->getMessage(),
            ];
        } catch (RequestException $error) {
            $response = $error->response;
            $apiError = $response?->json('error');

            return [
                'ok' => false,
                'dryRun' => false,
                'error' => $apiError
                    ? trim(($apiError['message'] ?? 'WhatsApp Cloud API request failed').' '.($apiError['code'] ?? '').' '.($apiError['type'] ?? ''))
                    : 'WhatsApp Cloud API request failed: '.$error->getMessage(),
            ];
        }

        if ($response->failed()) {
            $error = $response->json('error');

            return [
                'ok' => false,
                'dryRun' => false,
                'error' => $error
                    ? trim(($error['message'] ?? 'WhatsApp Cloud API request failed').' '.($error['code'] ?? '').' '.($error['type'] ?? ''))
                    : $response->body(),
            ];
        }

        return [
            'ok' => true,
            'dryRun' => false,
            'messageId' => $response->json('messages.0.id'),
        ];
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone ?? '');
        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '880'.substr($digits, 1);
        }

        return $digits;
    }
}
