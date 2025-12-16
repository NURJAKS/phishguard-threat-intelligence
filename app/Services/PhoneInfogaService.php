<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PhoneInfogaService
{
    private string $apiUrl;
    
    public function __construct()
    {
        // PhoneInfoga REST API URL (должен быть запущен локально)
        $this->apiUrl = env('PHONEINFOGA_API_URL', 'http://localhost:5000/api');
    }

    /**
     * Анализ номера телефона через PhoneInfoga REST API
     */
    public function analyzePhone(string $phone): array
    {
        // Очистка номера для PhoneInfoga API (нужен формат без +)
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        
        // PhoneInfoga API требует номер без плюса
        $apiPhoneNumber = preg_replace('/[^0-9]/', '', $cleanPhone);
        if (strpos($apiPhoneNumber, '7') === 0 && strlen($apiPhoneNumber) === 11) {
            // Убираем первую 7 для казахстанских номеров, если нужно
        }
        
        // E164 формат для отображения
        $e164Format = $cleanPhone;
        if (strpos($e164Format, '+') !== 0) {
            $e164Format = '+' . preg_replace('/^0/', '', preg_replace('/[^0-9]/', '', $cleanPhone));
        }

        try {
            // Шаг 1: Получаем базовую информацию о номере
            $baseResponse = Http::timeout(10)->post("{$this->apiUrl}/v2/numbers", [
                'number' => $apiPhoneNumber
            ]);

            if (!$baseResponse->successful()) {
                Log::warning("PhoneInfoga base API error", [
                    'status' => $baseResponse->status(),
                    'body' => $baseResponse->body()
                ]);
                return $this->fallbackDockerScan($e164Format);
            }

            $baseData = $baseResponse->json();
            Log::info("PhoneInfoga base response for {$apiPhoneNumber}", ['data' => $baseData]);

            // Шаг 2: Запускаем сканер local для получения типа линии и оператора
            $localData = null;
            try {
                $localResponse = Http::timeout(10)->post("{$this->apiUrl}/v2/scanners/local/run", [
                    'number' => $apiPhoneNumber
                ]);
                
                if ($localResponse->successful()) {
                    $localResult = $localResponse->json();
                    $localData = $localResult['result'] ?? null;
                    Log::info("PhoneInfoga local scanner result", ['data' => $localData]);
                }
            } catch (\Exception $e) {
                Log::warning("PhoneInfoga local scanner error: " . $e->getMessage());
            }

            // Шаг 3: googlesearch сканер отключен
            // PhoneInfoga используется только для метаданных номера
            // Поиск в соцсетях выполняется через отдельный сервис Sherlock
            $osintLinks = [];

            // Объединяем все данные
            $combinedData = array_merge($baseData, [
                'local_scanner' => $localData,
                'osint_links' => $osintLinks
            ]);

            return $this->parsePhoneInfogaApiResponse($combinedData, $e164Format);
            
        } catch (\Exception $e) {
            Log::error("PhoneInfoga error: " . $e->getMessage());
            
            // Fallback: попробовать через Docker CLI
            return $this->fallbackDockerScan($e164Format, $e->getMessage());
        }
    }

    /**
     * Fallback: использование PhoneInfoga через Docker CLI
     */
    private function fallbackDockerScan(string $phone, string $error = ''): array
    {
        try {
            $command = "docker run --rm phoneinfoga scan -n " . escapeshellarg($phone) . " 2>&1";
            $output = shell_exec($command);
            
            Log::info("PhoneInfoga Docker output for {$phone}", ['output' => substr($output, 0, 500)]);
            
            return $this->parsePhoneInfogaOutput($output, $phone);
            
        } catch (\Exception $e) {
            Log::error("PhoneInfoga Docker fallback error: " . $e->getMessage());
            return $this->getDefaultResult($phone, "Ошибка OSINT сканирования: " . ($error ?: $e->getMessage()));
        }
    }

    /**
     * Парсинг ответа PhoneInfoga REST API v2
     */
    private function parsePhoneInfogaApiResponse(array $data, string $phone): array
    {
        // PhoneInfoga возвращает данные в формате с вложенными структурами
        $result = [
            'valid' => $data['valid'] ?? false,
            'country' => null,
            'region' => null,
            'carrier' => null,
            'line_type' => null,
            'format_e164' => $phone,
            'format_international' => null,
            'format_local' => null,
            'timezone' => null,
            'osint_links' => [],
            'raw_data' => $data
        ];

        // Базовые данные из /v2/numbers
        if (isset($data['country'])) {
            $countryCode = $data['country'];
            // Исправление для Казахстана (PhoneInfoga определяет как RU)
            if ($countryCode === 'RU' && (strpos($phone, '+77') === 0 || strpos($phone, '+76') === 0)) {
                $result['country'] = 'Kazakhstan';
            } else {
                $countryMap = ['RU' => 'Russia', 'KZ' => 'Kazakhstan', 'US' => 'United States'];
                $result['country'] = $countryMap[$countryCode] ?? $countryCode;
            }
        }
        
        if (isset($data['carrier']) && $data['carrier'] !== '') {
            $result['carrier'] = $data['carrier'];
        }
        
        if (isset($data['e164'])) {
            $result['format_e164'] = $data['e164'];
        }
        
        if (isset($data['international'])) {
            $result['format_international'] = $data['international'];
        }
        
        if (isset($data['local'])) {
            $result['format_local'] = $data['local'];
        }

        // Данные из local scanner (более детальная информация)
        if (isset($data['local_scanner']) && is_array($data['local_scanner'])) {
            $local = $data['local_scanner'];
            
            // Local scanner может вернуть больше информации
            if (isset($local['country']) && !$result['country']) {
                $countryCode = $local['country'];
                if ($countryCode === 'RU' && (strpos($phone, '+77') === 0 || strpos($phone, '+76') === 0)) {
                    $result['country'] = 'Kazakhstan';
                } else {
                    $result['country'] = $local['country'];
                }
            }
            
            if (isset($local['carrier']) && $local['carrier'] !== '' && !$result['carrier']) {
                $result['carrier'] = $local['carrier'];
            }
            
            if (isset($local['line_type'])) {
                $result['line_type'] = $local['line_type'];
            }
        }

        // OSINT ссылки из googlesearch сканера
        if (isset($data['osint_links']) && is_array($data['osint_links'])) {
            $result['osint_links'] = $data['osint_links'];
        }

        // Данные из local scanner (более детальная информация)
        if (isset($data['local_scanner']) && is_array($data['local_scanner'])) {
            $local = $data['local_scanner'];
            
            // Local scanner может вернуть больше информации
            if (isset($local['country']) && !$result['country']) {
                $countryCode = $local['country'];
                if ($countryCode === 'RU' && (strpos($phone, '+77') === 0 || strpos($phone, '+76') === 0)) {
                    $result['country'] = 'Kazakhstan';
                } else {
                    $result['country'] = $local['country'];
                }
            }
            
            if (isset($local['carrier']) && $local['carrier'] !== '' && !$result['carrier']) {
                $result['carrier'] = $local['carrier'];
            }
            
            if (isset($local['line_type'])) {
                $result['line_type'] = $local['line_type'];
            }
        }

        // OSINT ссылки из googlesearch сканера (уже собраны в analyzePhone)
        if (isset($data['osint_links']) && is_array($data['osint_links'])) {
            $result['osint_links'] = $data['osint_links'];
        }

        // Дополнительное исправление для Казахстана по префиксу номера
        if (strpos($phone, '+77') === 0 || strpos($phone, '+76') === 0) {
            $result['country'] = 'Kazakhstan';
        }
        
        // Если формат E164 не установлен, используем входной номер
        if (!$result['format_e164']) {
            $result['format_e164'] = $phone;
        }

        return $result;
    }

    private function parsePhoneInfogaOutput(string $output, string $phone): array
    {
        $result = [
            'valid' => true,
            'country' => 'Unknown',
            'region' => 'Unknown',
            'carrier' => 'Unknown',
            'line_type' => 'Unknown',
            'format_e164' => $phone,
            'format_international' => $phone,
            'format_local' => '',
            'timezone' => null,
            'osint_links' => [] // Это поисковые ссылки для ручной проверки, не подтверждение находок
        ];

        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Парсинг основных данных
            if (str_contains($line, 'Country:')) {
                $result['country'] = trim(str_replace('Country:', '', $line));
            } elseif (str_contains($line, 'Carrier:') || str_contains($line, 'Carrier name:')) {
                $result['carrier'] = trim(str_replace(['Carrier:', 'Carrier name:'], '', $line));
            } elseif (str_contains($line, 'Line type:') || str_contains($line, 'Type:')) {
                $result['line_type'] = trim(str_replace(['Line type:', 'Type:'], '', $line));
            } elseif (str_contains($line, 'Valid:')) {
                $valid = trim(str_replace('Valid:', '', $line));
                $result['valid'] = stripos($valid, 'true') !== false || stripos($valid, 'yes') !== false;
            } elseif (str_contains($line, 'E164:')) {
                $result['format_e164'] = trim(str_replace('E164:', '', $line));
            } elseif (str_contains($line, 'International:')) {
                $result['format_international'] = trim(str_replace('International:', '', $line));
            } elseif (str_contains($line, 'Local format:')) {
                $result['format_local'] = trim(str_replace('Local format:', '', $line));
            } elseif (str_contains($line, 'Timezone:')) {
                $result['timezone'] = trim(str_replace('Timezone:', '', $line));
            } elseif (str_contains($line, 'URL:')) {
                // Сбор OSINT ссылок (это ссылки на поиск, а не реальные находки)
                $url = trim(str_replace('URL:', '', $line));
                if (!empty($url)) {
                    $result['osint_links'][] = $url;
                    
                    // НЕ устанавливаем found_in_social/disposable_provider на основе поисковых ссылок
                    // Это просто ссылки для ручной проверки, а не подтверждение находок
                }
            }
        }

        // Исправление для Казахстана (PhoneInfoga иногда определяет как RU)
        if (strpos($result['format_e164'], '+77') === 0 || strpos($result['format_e164'], '+76') === 0) {
            $result['country'] = 'Kazakhstan';
        }

        return $result;
    }

    private function getDefaultResult(string $phone, string $error = ''): array
    {
        return [
            'valid' => false,
            'country' => 'Unknown',
            'region' => 'Unknown',
            'carrier' => 'Unknown',
            'line_type' => 'Unknown',
            'format_e164' => $phone,
            'format_international' => $phone,
            'format_local' => '',
            'timezone' => null,
            'osint_links' => [],
            'error' => $error
        ];
    }

    /**
     * Получить оператора по коду для Казахстана
     */
    public function getKazakhstanOperator(string $code): string
    {
        $operators = [
            '701' => 'Beeline',
            '702' => 'Beeline',
            '705' => 'Beeline',
            '707' => 'Tele2',
            '708' => 'Tele2',
            '747' => 'Tele2',
            '775' => 'Kcell',
            '776' => 'Kcell',
            '777' => 'Kcell',
            '778' => 'Altel'
        ];
        
        return $operators[$code] ?? 'Unknown';
    }
}

