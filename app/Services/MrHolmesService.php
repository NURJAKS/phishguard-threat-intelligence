<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Mr.Holmes Service
 * Integrates Mr.Holmes OSINT tool for phone number investigation
 */
class MrHolmesService
{
    private string $mrholmesPath;
    private string $pythonPath;

    public function __construct()
    {
        $this->mrholmesPath = env('MRHOLMES_PATH', '/home/nurbol/HACKATON_TURKISTAN/mrholmes');
        $this->pythonPath = env('MRHOLMES_PYTHON', "{$this->mrholmesPath}/venv/bin/python3");
    }

    /**
     * Search phone number using Mr.Holmes
     */
    public function searchPhone(string $phone): array
    {
        try {
            // Проверяем наличие Python скрипта
            $scriptPath = "{$this->mrholmesPath}/MrHolmes.py";
            if (!file_exists($scriptPath)) {
                Log::warning("Mr.Holmes script not found: {$scriptPath}");
                return $this->getDefaultResult($phone, 'Mr.Holmes script not found');
            }

            // Запускаем Mr.Holmes через Python
            $command = sprintf(
                'cd %s && %s MrHolmes.py --phone %s --output json 2>&1',
                escapeshellarg($this->mrholmesPath),
                escapeshellarg($this->pythonPath),
                escapeshellarg($phone)
            );

            $result = Process::timeout(60)->run($command);

            if ($result->successful() || strlen($result->output()) > 0) {
                $output = $result->output();
                
                // Попытка парсинга JSON ответа
                $jsonStart = strpos($output, '{');
                if ($jsonStart !== false) {
                    $json = substr($output, $jsonStart);
                    $data = json_decode($json, true);
                    if ($data) {
                        return $this->parseMrHolmesResponse($data, $phone);
                    }
                }

                // Если JSON не найден, парсим текстовый вывод
                return $this->parseTextOutput($output, $phone);
            }

            return $this->getDefaultResult($phone, 'Mr.Holmes не вернул результатов');
        } catch (\Exception $e) {
            Log::error("Mr.Holmes error: " . $e->getMessage());
            return $this->getDefaultResult($phone, $e->getMessage());
        }
    }

    /**
     * Parse Mr.Holmes JSON response
     */
    private function parseMrHolmesResponse(array $data, string $phone): array
    {
        $result = [
            'phone' => $phone,
            'found' => false,
            'sources' => [],
            'emails' => [],
            'social_media' => [],
            'leaks' => [],
            'breaches' => [],
        ];

        // Парсинг данных из Mr.Holmes
        if (isset($data['results'])) {
            $result['found'] = true;
            
            // Emails
            if (isset($data['results']['emails'])) {
                $result['emails'] = is_array($data['results']['emails']) 
                    ? $data['results']['emails'] 
                    : [$data['results']['emails']];
            }

            // Social media
            if (isset($data['results']['social_media'])) {
                $result['social_media'] = $data['results']['social_media'];
            }

            // Data breaches
            if (isset($data['results']['breaches'])) {
                $result['breaches'] = $data['results']['breaches'];
            }

            // Sources
            if (isset($data['sources'])) {
                $result['sources'] = is_array($data['sources']) ? $data['sources'] : [$data['sources']];
            }
        }

        return $result;
    }

    /**
     * Parse text output from Mr.Holmes
     */
    private function parseTextOutput(string $output, string $phone): array
    {
        $result = [
            'phone' => $phone,
            'found' => false,
            'raw_output' => $output,
        ];

        // Простой парсинг текстового вывода
        if (stripos($output, 'found') !== false || stripos($output, 'найдено') !== false) {
            $result['found'] = true;
        }

        return $result;
    }

    /**
     * Default result when service is unavailable
     */
    private function getDefaultResult(string $phone, string $reason): array
    {
        return [
            'phone' => $phone,
            'found' => false,
            'error' => $reason,
            'sources' => [],
        ];
    }
}

