<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UrlscanService
{
    private string $apiKey;
    private string $baseUrl = 'https://urlscan.io/api/v1';

    public function __construct()
    {
        $this->apiKey = (string) env('URLSCAN_API', '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Отправка URL на сканирование
     */
    public function submitScan(string $url, string $visibility = 'public'): array
    {
        $response = Http::withHeaders([
            'API-Key' => $this->apiKey,
        ])
        ->acceptJson()
        ->post($this->baseUrl . '/scan/', [
            'url' => $url,
            'visibility' => $visibility,
        ]);

        if (! $response->successful()) {
            Log::warning('Urlscan submit failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Urlscan: ошибка при отправке ссылки на сканирование');
        }

        return $response->json();
    }

    /**
     * Получение результата по UUID
     */
    public function getResult(string $uuid): array
    {
        $response = Http::withHeaders([
            'API-Key' => $this->apiKey,
        ])->acceptJson()->get($this->baseUrl . '/result/' . $uuid . '/');

        if (! $response->successful()) {
            Log::warning('Urlscan result failed', [
                'uuid' => $uuid,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Urlscan: ошибка при получении результата сканирования');
        }

        return $response->json();
    }

    /**
     * Высокоуровневый метод: отправить URL и подождать результат.
     * Вдохновлено примером из пакета `laravel-urlscan`:
     * см. пример startScan в README пакета [`xchimx/laravel-urlscan`](https://github.com/xchimx/laravel-urlscan).
     */
    public function scanAndWait(string $url, int $waitSeconds = 10, string $visibility = 'public'): array
    {
        $submit = $this->submitScan($url, $visibility);

        if (! isset($submit['uuid'])) {
            throw new \RuntimeException('Urlscan: не получен UUID сканирования');
        }

        $uuid = $submit['uuid'];
        $delay = 3;
        $maxWait = max(30, $waitSeconds); // минимум 30 секунд, можно больше по параметру
        $maxAttempts = (int) ceil($maxWait / $delay);

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($delay);
            $response = Http::withHeaders([
                'API-Key' => $this->apiKey,
            ])->acceptJson()->get($this->baseUrl . '/result/' . $uuid . '/');

            $status = $response->status();

            // 404/202 — результата ещё нет, ждём дальше
            if ($status === 404 || $status === 202) {
                continue;
            }

            // 429 — перегруз, подождём подольше
            if ($status === 429) {
                sleep(5);
                continue;
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Urlscan polling failed', [
                'uuid' => $uuid,
                'status' => $status,
                'body' => $response->body(),
            ]);
        }

        // Возвращаем метку pending, чтобы UI мог показать предупреждение и ссылку
        return [
            'pending' => true,
            'uuid' => $uuid,
            'result_url' => $submit['result'] ?? null,
            'api_url' => $submit['api'] ?? null,
        ];
    }

    /**
     * Простая интерпретация результата: безопасно / подозрительно.
     */
    public function classify(array $result): array
    {
        $verdict = 'unknown';
        $label = 'Недостаточно данных';

        $overall = $result['verdicts']['overall'] ?? [];
        $urlscanVerdict = $result['verdicts']['urlscan'] ?? [];
        $engines = $result['verdicts']['engines'] ?? [];

        // Определяем "опасно" только если есть явные признаки:
        // - overall.malicious = true
        // - overall.score > 0
        // - urlscan.score > 0
        // - хотя бы один движок сообщил malicious=true или score>0
        $engineMalicious = false;
        if (is_array($engines)) {
            foreach ($engines as $engine) {
                if (
                    (isset($engine['malicious']) && $engine['malicious'] === true) ||
                    (($engine['score'] ?? 0) > 0)
                ) {
                    $engineMalicious = true;
                    break;
                }
            }
        }

        $isMalicious = ($overall['malicious'] ?? false) === true
            || ($overall['score'] ?? 0) > 0
            || ($urlscanVerdict['score'] ?? 0) > 0
            || $engineMalicious;

        if ($isMalicious) {
            $verdict = 'danger';
            $label = 'Urlscan/движки отметили ресурс как потенциально вредоносный';
        } elseif (isset($overall['malicious']) && $overall['malicious'] === false) {
            $verdict = 'safe';
            $label = 'Urlscan не пометил ссылку как вредоносную';
        } else {
            $verdict = 'unknown';
            $label = 'Результат не классифицирован';
        }

        return [
            'verdict' => $verdict,
            'label' => $label,
        ];
    }
}


