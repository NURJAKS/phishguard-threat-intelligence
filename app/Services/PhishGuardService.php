<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhishGuardService
{
    private string $apiUrl;
    private bool $isConfigured;

    public function __construct()
    {
        // Теперь используем Python скрипты напрямую, не нужен отдельный сервер
        // Проверяем наличие Python скрипта
        $scriptPath = base_path('python_scripts/phishguard_audit.py');
        $this->isConfigured = file_exists($scriptPath);
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Проверка URL через PhishGuard API
     */
    public function checkUrl(string $url): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PhishGuard API не настроен. Укажите PHISHGUARD_API_URL в .env');
        }

        try {
            Log::info("PhishGuard: Checking URL: {$url}");

            $response = Http::timeout(30)->post("{$this->apiUrl}/v1/check/url", [
                'url' => $url,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("PhishGuard: Response received", ['action' => $data['action'] ?? 'unknown']);
                return $data;
            }

            throw new \Exception("PhishGuard API error: HTTP {$response->status()}");
        } catch (\Exception $e) {
            Log::error("PhishGuard check error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * AI анализ URL через PhishGuard
     */
    public function aiScan(array $urls): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PhishGuard API не настроен');
        }

        try {
            Log::info("PhishGuard AI: Scanning URLs", ['count' => count($urls)]);

            $response = Http::timeout(60)->post("{$this->apiUrl}/v1/ai/scan", [
                'urls' => array_slice($urls, 0, 5), // Limit to 5 URLs
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("PhishGuard AI API error: HTTP {$response->status()}");
        } catch (\Exception $e) {
            Log::error("PhishGuard AI scan error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Сканирование секретов в JavaScript файлах
     */
    public function scanSecrets(string $url, bool $usePinkerton = true): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PhishGuard API не настроен');
        }

        try {
            Log::info("PhishGuard Secrets: Scanning URL: {$url}");

            $response = Http::timeout(30)->post("{$this->apiUrl}/v1/scan/secrets", [
                'url' => $url,
                'use_pinkerton' => $usePinkerton,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Secrets scan error: HTTP {$response->status()}");
        } catch (\Exception $e) {
            Log::error("Secrets scan error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Полный аудит URL - использует Python скрипт напрямую
     */
    public function fullAudit(string $url): array
    {
        try {
            Log::info("PhishGuard Full Audit: Starting for URL: {$url} (direct Python call)");

            // Путь к Python скрипту
            $scriptPath = base_path('python_scripts/phishguard_audit.py');
            
            if (!file_exists($scriptPath)) {
                Log::error("PhishGuard script not found: {$scriptPath}");
                return ['error' => 'PhishGuard скрипт не найден'];
            }

            // Получаем OpenRouter API ключ из config или .env
            $apiKey = config('services.openrouter.key') ?? config('services.openrouter.api_key') ?? env('OPENROUTER_API_KEY', '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');
            if (empty($apiKey)) {
                Log::error("OPENROUTER_API_KEY not set");
                return ['error' => 'OPENROUTER_API_KEY не настроен'];
            }

            // Экранируем URL и API ключ для безопасности
            $escapedUrl = escapeshellarg($url);
            $escapedApiKey = escapeshellarg($apiKey);

            // Определяем Python интерпретатор (проверяем venv сначала)
            $pythonCmd = 'python3';
            $venvPython = base_path('venv/bin/python3');
            if (file_exists($venvPython)) {
                $pythonCmd = $venvPython;
            }

            // Выполняем Python скрипт (stderr перенаправляем отдельно)
            $command = "{$pythonCmd} {$scriptPath} {$escapedUrl} {$escapedApiKey} 2>/dev/null";
            Log::debug("Executing: {$command}");
            
            $output = shell_exec($command);
            $exitCode = 0;
            
            // Если shell_exec вернул null, пробуем через exec
            if ($output === null) {
                exec("{$pythonCmd} {$scriptPath} {$escapedUrl} {$escapedApiKey} 2>/dev/null", $outputLines, $exitCode);
                $output = implode("\n", $outputLines);
            }

            if (empty($output)) {
                Log::error("PhishGuard script returned empty output");
                return ['error' => 'PhishGuard скрипт вернул пустой ответ'];
            }

            // Убираем пустые строки и пробелы
            $output = trim($output);
            
            // Парсим JSON ответ
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Failed to parse JSON from PhishGuard: " . json_last_error_msg() . ", output: " . substr($output, 0, 500));
                return ['error' => 'Ошибка парсинга ответа от PhishGuard: ' . json_last_error_msg()];
            }

            // Проверяем наличие ошибки
            if (isset($result['error'])) {
                $errorMsg = $this->parseGeminiError($result['error']);
                Log::warning("PhishGuard Full Audit returned error: " . $errorMsg);
                return ['error' => $errorMsg];
            }

            // Успешный ответ
            Log::info("PhishGuard Full Audit: Success, verdict: " . ($result['verdict'] ?? 'N/A'));
            return $result;

        } catch (\Exception $e) {
            Log::error("PhishGuard Full Audit error: " . $e->getMessage());
            return ['error' => 'Ошибка при выполнении анализа: ' . $e->getMessage()];
        }
    }

    /**
     * Парсинг ошибок AI API для более понятного отображения
     */
    private function parseGeminiError($error): string
    {
        $errorStr = is_string($error) ? $error : json_encode($error);
        
        // Обработка ошибки 503 (модель перегружена)
        if (strpos($errorStr, '503') !== false || strpos($errorStr, 'UNAVAILABLE') !== false || strpos($errorStr, 'overloaded') !== false) {
            // Извлекаем время ожидания, если есть
            if (preg_match('/try again in ([\d.]+)s/i', $errorStr, $matches) || preg_match('/(\d+)\s*секунд/i', $errorStr, $matches)) {
                $waitTime = round((float)$matches[1]);
                return "AI модель временно перегружена. Пожалуйста, попробуйте через {$waitTime} секунд.";
            }
            return "AI модель временно перегружена. Пожалуйста, попробуйте через несколько секунд.";
        }
        
        // Обработка ошибки 429 (превышен лимит квоты) - Gemini или другой API
        if (strpos($errorStr, '429') !== false || strpos($errorStr, 'RESOURCE_EXHAUSTED') !== false || strpos($errorStr, 'quota') !== false) {
            // Извлекаем время ожидания, если есть (может быть в разных форматах)
            $waitTime = null;
            if (preg_match('/retry in ([\d.]+)s/i', $errorStr, $matches) || 
                preg_match('/Попробуйте через (\d+)/i', $errorStr, $matches) ||
                preg_match('/(\d+)\s*секунд/i', $errorStr, $matches)) {
                $waitTime = round((float)$matches[1]);
            }
            
            if ($waitTime) {
                return "Превышен лимит запросов к Google AI API (20 запросов/день для бесплатного тарифа). Попробуйте повторить через {$waitTime} секунд. Система автоматически переключится на Groq API если он настроен.";
            }
            return "Превышен лимит запросов к Google AI API (20 запросов/день для бесплатного тарифа). Система автоматически переключится на Groq API (1000 запросов/день бесплатно) если он настроен в PhishGuard backend. Или подождите и попробуйте снова.";
        }
        
        // Обработка других ошибок API
        if (strpos($errorStr, 'API key') !== false || strpos($errorStr, 'authentication') !== false) {
            return "Ошибка аутентификации AI API. Проверьте API ключи в настройках PhishGuard backend (GOOGLE_API_KEY или GROQ_API_KEY).";
        }
        
        // Если это JSON с детальной ошибкой
        if (is_array($error) && isset($error['message'])) {
            return $this->parseGeminiError($error['message']);
        }
        
        // Возвращаем оригинальное сообщение, но обрезаем слишком длинные
        if (strlen($errorStr) > 500) {
            return substr($errorStr, 0, 500) . '...';
        }
        
        return $errorStr;
    }

    /**
     * Сканирование директорий через Gobuster
     */
    public function gobusterScan(string $url, ?string $wordlist = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PhishGuard API не настроен');
        }

        try {
            Log::info("PhishGuard Gobuster: Scanning URL: {$url}");

            $payload = ['url' => $url];
            if ($wordlist) {
                $payload['wordlist'] = $wordlist;
            }

            $response = Http::timeout(120)->post("{$this->apiUrl}/v1/vuln/gobuster", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Gobuster scan error: HTTP {$response->status()}");
        } catch (\Exception $e) {
            Log::error("Gobuster scan error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Сканирование уязвимостей через Nikto
     */
    public function niktoScan(string $url): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PhishGuard API не настроен');
        }

        try {
            Log::info("PhishGuard Nikto: Scanning URL: {$url}");

            $response = Http::timeout(60)->post("{$this->apiUrl}/v1/vuln/nikto", [
                'url' => $url,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Nikto scan error: HTTP {$response->status()}");
        } catch (\Exception $e) {
            Log::error("Nikto scan error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Проверка здоровья API
     */
    public function healthCheck(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(5)->get("{$this->apiUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Классификация результата PhishGuard
     */
    public function classify(array $result): array
    {
        $action = $result['action'] ?? 'unknown';
        $score = (float)($result['score'] ?? 0.0);
        $reason = $result['reason'] ?? '';

        // Определяем вердикт на основе action и score
        if ($action === 'block' || $score >= 0.8) {
            $verdict = 'danger';
            $label = 'Опасный сайт';
        } elseif ($action === 'warn' || $score >= 0.4) {
            $verdict = 'warning';
            $label = 'Подозрительный сайт';
        } else {
            $verdict = 'safe';
            $label = 'Безопасный сайт';
        }

        return [
            'verdict' => $verdict,
            'label' => $label,
            'action' => $action,
            'score' => $score,
            'reason' => $reason,
        ];
    }
}

