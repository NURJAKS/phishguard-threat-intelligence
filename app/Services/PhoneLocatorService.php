<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phone Locator Service
 * Based on https://github.com/howardchn/location-to-phone-number
 * 
 * Определяет локацию номера телефона (регион, оператор)
 */
class PhoneLocatorService
{
    /**
     * Определение локации номера телефона
     * 
     * @param string $phone Номер телефона
     * @return array Массив с информацией о локации
     */
    public function getLocation(string $phone): array
    {
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Извлекаем первые 7 цифр для определения оператора и региона
        $digits = preg_replace('/[^0-9]/', '', $cleanPhone);
        
        if (strlen($digits) < 7) {
            return $this->getDefaultResult($phone, 'Недостаточно цифр для определения локации');
        }
        
        $prefix = substr($digits, 0, 7);
        
        // Определение страны по префиксу
        $country = $this->detectCountry($cleanPhone);
        
        if ($country === 'Kazakhstan') {
            return $this->analyzeKazakhstanNumber($digits, $phone);
        } elseif ($country === 'China') {
            // Для китайских номеров можно использовать оригинальный API
            return $this->analyzeChinaNumber($digits, $phone);
        } else {
            return $this->getDefaultResult($phone, "Страна: {$country}");
        }
    }
    
    /**
     * Определение страны по префиксу номера
     */
    private function detectCountry(string $phone): string
    {
        if (preg_match('/^\+7(7|6)/', $phone)) {
            return 'Kazakhstan';
        } elseif (preg_match('/^\+7[^76]/', $phone)) {
            return 'Russia';
        } elseif (preg_match('/^\+86/', $phone)) {
            return 'China';
        } elseif (preg_match('/^\+/', $phone)) {
            return 'Other';
        }
        
        // Если нет +, проверяем локальный формат
        if (preg_match('/^(77|76|70)/', $phone)) {
            return 'Kazakhstan';
        }
        
        return 'Unknown';
    }
    
    /**
     * Анализ казахстанского номера
     */
    private function analyzeKazakhstanNumber(string $digits, string $originalPhone): array
    {
        $prefix7 = substr($digits, 0, 7);
        $prefix3 = substr($digits, 0, 3);
        
        // Определение оператора по префиксу
        $operator = $this->detectKazakhstanOperator($prefix7, $prefix3);
        
        // Определение региона (основные города Казахстана)
        $region = $this->detectKazakhstanRegion($prefix7, $prefix3);
        
        // Тип номера (мобильный/городской)
        $type = $this->detectLineType($prefix3);
        
        return [
            'phone' => $originalPhone,
            'phone_valid' => true,
            'phone_type' => $type,
            'country' => 'Kazakhstan',
            'country_code' => '+7',
            'region' => $region,
            'city' => $region,
            'operator_name' => $operator,
            'operator_type' => 'mobile',
            'carrier_source' => 'kazakhstan_prefix_database',
            'timezone' => 'Asia/Almaty',
            'international_format' => '+7' . substr($digits, 1),
            'location_info' => [
                'province' => $region,
                'city' => $region,
                'operator' => $operator,
                'coordinates' => $this->getRegionCoordinates($region)
            ],
            'data_confidence' => [
                'level' => 'medium',
                'reason' => 'Определено по префиксу оператора'
            ]
        ];
    }
    
    /**
     * Определение оператора Казахстана по префиксу
     */
    private function detectKazakhstanOperator(string $prefix7, string $prefix3): string
    {
        // Операторы Казахстана по префиксам
        $operators = [
            // Beeline
            '705' => 'Beeline',
            '707' => 'Beeline',
            '777' => 'Beeline',
            '708' => 'Beeline',
            
            // Kcell/Activ
            '701' => 'Kcell/Activ',
            '702' => 'Kcell/Activ',
            '775' => 'Kcell/Activ',
            '778' => 'Kcell/Activ',
            
            // Tele2/Altel
            '747' => 'Tele2/Altel',
            '7750' => 'Tele2/Altel',
            '7751' => 'Tele2/Altel',
            '7752' => 'Tele2/Altel',
            '7753' => 'Tele2/Altel',
            '7755' => 'Tele2/Altel',
            '7756' => 'Tele2/Altel',
            '7757' => 'Tele2/Altel',
            '7758' => 'Tele2/Altel',
            '7759' => 'Tele2/Altel',
            
            // Altel
            '700' => 'Altel',
            '708' => 'Altel',
            
            // Other
            '776' => 'Mobile',
        ];
        
        // Проверяем 7-значный префикс
        if (isset($operators[$prefix7])) {
            return $operators[$prefix7];
        }
        
        // Проверяем 3-значный префикс
        if (isset($operators[$prefix3])) {
            return $operators[$prefix3];
        }
        
        // Проверяем 4-значный префикс
        $prefix4 = substr($prefix7, 0, 4);
        if (isset($operators[$prefix4])) {
            return $operators[$prefix4];
        }
        
        return 'Unknown Operator';
    }
    
    /**
     * Определение региона Казахстана по префиксу
     */
    private function detectKazakhstanRegion(string $prefix7, string $prefix3): string
    {
        // Основные регионы с координатами
        // Алматы и Алматинская область
        if (in_array($prefix3, ['701', '702', '705', '707', '747', '777', '778'])) {
            return 'Алматы';
        }
        
        // Астана/Нур-Султан
        if (in_array($prefix3, ['700', '708', '775', '776'])) {
            return 'Нур-Султан';
        }
        
        // Общий регион
        return 'Kazakhstan';
    }
    
    /**
     * Получить координаты региона
     */
    public function getRegionCoordinates(string $region): array
    {
        // Нормализуем регион (убираем лишние пробелы, приводим к одному формату)
        $normalizedRegion = trim($region);
        
        $coordinates = [
            // Основные города и регионы Казахстана
            'Алматы' => ['lat' => 43.2220, 'lng' => 76.8512, 'city' => 'Алматы'],
            'Almaty Region' => ['lat' => 43.2220, 'lng' => 76.8512, 'city' => 'Алматы'],
            'Almaty' => ['lat' => 43.2220, 'lng' => 76.8512, 'city' => 'Алматы'],
            
            'Нур-Султан' => ['lat' => 51.1694, 'lng' => 71.4491, 'city' => 'Нур-Султан'],
            'Nur-Sultan (Astana)' => ['lat' => 51.1694, 'lng' => 71.4491, 'city' => 'Нур-Султан'],
            'Astana' => ['lat' => 51.1694, 'lng' => 71.4491, 'city' => 'Нур-Султан'],
            
            'Шымкент' => ['lat' => 42.3419, 'lng' => 69.5901, 'city' => 'Шымкент'],
            'Shymkent' => ['lat' => 42.3419, 'lng' => 69.5901, 'city' => 'Шымкент'],
            
            'Караганда' => ['lat' => 49.8014, 'lng' => 73.1020, 'city' => 'Караганда'],
            'Karaganda' => ['lat' => 49.8014, 'lng' => 73.1020, 'city' => 'Караганда'],
            
            'Актобе' => ['lat' => 50.2833, 'lng' => 57.1667, 'city' => 'Актобе'],
            'Aktobe' => ['lat' => 50.2833, 'lng' => 57.1667, 'city' => 'Актобе'],
            
            'Тараз' => ['lat' => 42.9000, 'lng' => 71.3667, 'city' => 'Тараз'],
            'Taraz' => ['lat' => 42.9000, 'lng' => 71.3667, 'city' => 'Тараз'],
            
            'Павлодар' => ['lat' => 52.2833, 'lng' => 76.9667, 'city' => 'Павлодар'],
            'Pavlodar' => ['lat' => 52.2833, 'lng' => 76.9667, 'city' => 'Павлодар'],
            
            'Казахстан' => ['lat' => 48.0196, 'lng' => 66.9237, 'city' => 'Центр Казахстана'],
            'Kazakhstan' => ['lat' => 48.0196, 'lng' => 66.9237, 'city' => 'Центр Казахстана'], // Центр страны
        ];
        
        // Проверяем точное совпадение
        if (isset($coordinates[$normalizedRegion])) {
            return $coordinates[$normalizedRegion];
        }
        
        // Проверяем частичное совпадение (регион содержит название города)
        foreach ($coordinates as $key => $coords) {
            if (stripos($normalizedRegion, $key) !== false || stripos($key, $normalizedRegion) !== false) {
                return $coords;
            }
        }
        
        // Fallback - центр Казахстана
        return $coordinates['Kazakhstan'];
    }
    
    /**
     * Определение типа линии
     */
    private function detectLineType(string $prefix3): string
    {
        // Казахстанские мобильные префиксы
        $mobilePrefixes = ['700', '701', '702', '705', '707', '708', '747', '775', '776', '777', '778'];
        
        if (in_array($prefix3, $mobilePrefixes)) {
            return 'mobile';
        }
        
        return 'unknown';
    }
    
    /**
     * Анализ китайского номера через оригинальный API
     */
    private function analyzeChinaNumber(string $digits, string $originalPhone): array
    {
        try {
            // Используем оригинальный API из репозитория
            $response = Http::timeout(10)->get('http://www.webxml.com.cn/WebServices/MobileCodeWS.asmx/getMobileCodeInfo', [
                'mobileCode' => substr($digits, 0, 7),
                'userID' => ''
            ]);
            
            if ($response->successful()) {
                $result = $response->body();
                // Парсим результат в формате "номер：省份 城市 类型"
                if (strpos($result, '：') !== false) {
                    $parts = explode('：', $result);
                    $locationParts = explode(' ', $parts[1] ?? '');
                    
                    return [
                        'phone' => $originalPhone,
                        'phone_valid' => true,
                        'country' => 'China',
                        'country_code' => '+86',
                        'region' => $locationParts[0] ?? 'Unknown',
                        'city' => $locationParts[1] ?? 'Unknown',
                        'operator_name' => $locationParts[2] ?? 'Unknown',
                        'carrier_source' => 'webxml.com.cn',
                        'location_info' => [
                            'province' => $locationParts[0] ?? 'Unknown',
                            'city' => $locationParts[1] ?? 'Unknown',
                            'operator' => $locationParts[2] ?? 'Unknown'
                        ]
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("China phone lookup error: " . $e->getMessage());
        }
        
        return $this->getDefaultResult($originalPhone, 'China number');
    }
    
    /**
     * Результат по умолчанию
     */
    private function getDefaultResult(string $phone, string $reason): array
    {
        return [
            'phone' => $phone,
            'phone_valid' => null,
            'phone_type' => null,
            'country' => null,
            'region' => null,
            'city' => null,
            'operator_name' => null,
            'operator_type' => null,
            'carrier_source' => null,
            'timezone' => null,
            'international_format' => null,
            'location_info' => null,
            'data_confidence' => [
                'level' => 'low',
                'reason' => $reason
            ]
        ];
    }
}

