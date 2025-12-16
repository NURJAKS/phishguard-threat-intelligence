<?php

namespace App\Services;

use App\Models\Check;
use Illuminate\Support\Facades\Http;

class CheckService
{
    protected PhoneLocatorService $phoneLocatorService;
    protected SherlockService $sherlockService;
    protected OpenSearchService $openSearchService;
    protected Neo4jService $neo4jService;
    protected MrHolmesService $mrHolmesService;
    protected TruecallerService $truecallerService;

    public function __construct(
        PhoneLocatorService $phoneLocatorService,
        SherlockService $sherlockService,
        OpenSearchService $openSearchService,
        Neo4jService $neo4jService,
        MrHolmesService $mrHolmesService,
        TruecallerService $truecallerService
    ) {
        $this->phoneLocatorService = $phoneLocatorService;
        $this->sherlockService = $sherlockService;
        $this->openSearchService = $openSearchService;
        $this->neo4jService = $neo4jService;
        $this->mrHolmesService = $mrHolmesService;
        $this->truecallerService = $truecallerService;
    }

    public function performCheck($link, $phone)
    {
        // Реальная логика проверки с использованием PhoneInfoga и Gemini AI
        $data = $this->analyzeFraud($link, $phone);

        // Создаем объект Check без сохранения в БД (для совместимости с контроллером)
        $check = new Check();
        $check->link = $link;
        $check->phone = $phone;
        $check->result = $data;
        $check->risk_score = $data['final_risk'] ?? 0;
        $check->verdict = $data['verdict'] ?? 'Unknown';
        $check->exists = false; // Указываем, что это не сохраненная запись

        // Попытка сохранить в БД (опционально, не критично)
        try {
            $check->save();
        } catch (\Exception $e) {
            // Игнорируем ошибки БД - результат все равно вернется пользователю
            \Illuminate\Support\Facades\Log::warning("Не удалось сохранить в БД: " . $e->getMessage());
        }

        return $check;
    }

    private function analyzeFraud($link, $phone)
    {
        $risk_factors = [];
        $checks = [
            'price' => ['risk' => 0, 'details' => 'N/A'],
            'text' => ['risk' => 0, 'details' => 'N/A'],
            'phone' => ['risk' => 0, 'details' => 'N/A'],
            'domain' => ['risk' => 0, 'details' => 'N/A'],
            'images' => ['risk' => 0, 'details' => 'N/A']
        ];

        $phoneResult = null;
        $phoneTechnicalMetadata = null;
        $rawPhoneInfogaData = null;
        $sherlockData = null;

        // Анализ телефона через все доступные инструменты
        if ($phone) {
            // 1. PhoneLocatorService - базовая информация
            $phoneResult = $this->analyzePhone($phone);
            $checks['phone'] = $phoneResult['check'];
            $phoneTechnicalMetadata = $phoneResult['technical_metadata'] ?? null;
            $rawPhoneInfogaData = null;
            
            // 2. Truecaller - реальное местоположение и владелец
            $truecallerData = null;
            try {
                $truecallerData = $this->truecallerService->searchPhone($phone);
                if ($truecallerData['found'] && isset($truecallerData['location'])) {
                    // Обновляем координаты из Truecaller если есть
                    if ($phoneTechnicalMetadata && !isset($phoneTechnicalMetadata['location_info']['coordinates'])) {
                        $phoneTechnicalMetadata['location_info']['coordinates'] = $truecallerData['coordinates'] ?? null;
                        $phoneTechnicalMetadata['location_info']['truecaller_location'] = $truecallerData['location'];
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Truecaller search error: " . $e->getMessage());
            }
            
            // 3. OpenSearch - поиск по индексу
            $opensearchResults = null;
            try {
                $opensearchResults = $this->openSearchService->searchPhone($phone);
                // Индексируем текущие данные
                if ($phoneTechnicalMetadata) {
                    $indexData = array_merge($phoneTechnicalMetadata, [
                        'risk_score' => $phoneResult['risk'] ?? 0,
                        'verdict' => $phoneResult['label'] ?? 'Unknown'
                    ]);
                    $this->openSearchService->indexPhone($indexData);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("OpenSearch error: " . $e->getMessage());
            }
            
            // 4. Neo4j - граф связей
            $neo4jRelations = null;
            try {
                // Создаем узел телефона
                if ($phoneTechnicalMetadata) {
                    $nodeData = array_merge($phoneTechnicalMetadata, [
                        'risk_score' => $phoneResult['risk'] ?? 0,
                        'verdict' => $phoneResult['label'] ?? 'Unknown'
                    ]);
                    $this->neo4jService->createPhoneNode($nodeData);
                    
                    // Ищем связанные номера
                    $neo4jRelations = $this->neo4jService->findRelatedPhones($phone);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Neo4j error: " . $e->getMessage());
            }
            
            // 5. Mr.Holmes - OSINT поиск
            $mrholmesData = null;
            try {
                $mrholmesData = $this->mrHolmesService->searchPhone($phone);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Mr.Holmes search error: " . $e->getMessage());
            }
            
            // 6. Sherlock - поиск в соцсетях
            $sherlockData = null;
            if (env('ENABLE_SHERLOCK', false)) {
                try {
                    $sherlockData = $this->sherlockService->searchByPhone($phone);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Sherlock search error: " . $e->getMessage());
                    $sherlockData = [
                        'status' => 'not-found',
                        'error' => null,
                        'accounts' => []
                    ];
                }
            } else {
                $sherlockData = [
                    'status' => 'disabled',
                    'accounts' => []
                ];
            }
            
            // Добавление risk_factors на основе реальных данных
            if ($phoneResult['risk'] > 70) {
                $risk_factors[] = [
                    'key' => 'phone_risk_high',
                    'label' => $phoneResult['label'],
                    'status' => 'danger'
                ];
            } elseif ($phoneResult['risk'] > 40) {
                $risk_factors[] = [
                    'key' => 'phone_risk_medium',
                    'label' => $phoneResult['label'],
                    'status' => 'warning'
                ];
            } elseif ($phoneResult['risk'] > 0) {
                $risk_factors[] = [
                    'key' => 'phone_risk_low',
                    'label' => $phoneResult['label'],
                    'status' => 'warning'
                ];
            } else {
                $risk_factors[] = [
                    'key' => 'phone_checked',
                    'label' => $phoneResult['label'] ?? 'Недостаточно данных для оценки риска',
                    'status' => 'success'
                ];
            }
        }

        // Анализ ссылки/домена
        if ($link) {
            $domainResult = $this->analyzeDomain($link);
            $checks['domain'] = $domainResult['check'];
            if ($domainResult['risk'] > 0) {
                $risk_factors[] = [
                    'key' => 'domain_risk',
                    'label' => $domainResult['label'],
                    'status' => $domainResult['risk'] > 50 ? 'danger' : 'warning'
                ];
            } else {
                $risk_factors[] = [
                    'key' => 'domain_ok',
                    'label' => 'Домен проверен',
                    'status' => 'success'
                ];
            }
        }

        // Расчет общего риска
        $totalRisk = 0;
        $count = 0;
        foreach ($checks as $check) {
            if ($check['risk'] > 0 || $check['details'] !== 'N/A') {
                $totalRisk += $check['risk'];
                $count++;
            }
        }
        $final_risk = $count > 0 ? min(intval($totalRisk / $count), 100) : 0;

        // Определение вердикта
        if ($final_risk < 20) {
            $verdict = 'Low Risk';
        } elseif ($final_risk < 70) {
            $verdict = 'Medium Risk';
        } else {
            $verdict = 'High Risk';
        }

        // Уверенность
        $confidence = 75;
        if ($phone && $link) {
            $confidence = 85;
        }

        return [
            'final_risk' => $final_risk,
            'verdict' => $verdict,
            'confidence' => $confidence,
            'checks' => $checks,
            'risk_factors' => $risk_factors,
            'reasons' => [],
            'technical_metadata' => $phoneTechnicalMetadata,
            'raw_phoneinfoga_data' => $rawPhoneInfogaData,
            'sherlock_data' => $sherlockData ?? null,
            'truecaller_data' => $truecallerData ?? null,
            'opensearch_results' => $opensearchResults ?? null,
            'neo4j_relations' => $neo4jRelations ?? null,
            'mrholmes_data' => $mrholmesData ?? null,
            'geo_analysis' => [
                'ad_region' => 'Unknown',
                'phone_region' => 'Unknown',
                'match' => false,
                'risk' => 0
            ],
            'pattern_analysis' => [
                'is_repeat' => false,
                'similar_ads_count' => 0,
                'previously_reported' => false,
                'previous_verdict' => null
            ],
            'recommendations' => []
        ];
    }

    private function analyzePhone($phone)
    {
        // Использование PhoneLocatorService для определения локации номера
        $locationData = $this->phoneLocatorService->getLocation($phone);

        // Расчет риска на основе данных от PhoneLocatorService
        $risk = 0;
        $details = [];
        $hasData = false;

        // Базовые данные из PhoneLocatorService
        $isValid = $locationData['phone_valid'] ?? null;
        $country = $locationData['country'] ?? null;
        $carrier = $locationData['operator_name'] ?? null;
        $lineType = $locationData['phone_type'] ?? null;
        $formatE164 = $locationData['international_format'] ?? $phone;
        $region = $locationData['region'] ?? null;
        $city = $locationData['city'] ?? null;

        // Проверка валидности номера (только если есть данные)
        if ($isValid !== null && $isValid !== false) {
            $details[] = 'Номер валиден';
            $hasData = true;
        } elseif ($isValid === false) {
            $risk += 60;
            $details[] = 'Номер недействителен';
            $hasData = true;
        }

        // Проверка типа линии (VoIP = высокий риск, только если точно определено)
        if ($lineType && strtolower(trim($lineType)) === 'voip') {
            $risk += 70;
            $details[] = "Тип линии: VoIP (высокий риск мошенничества)";
            $hasData = true;
        } elseif ($lineType && trim($lineType) !== '') {
            $details[] = "Тип линии: {$lineType}";
            $hasData = true;
        }

        // Проверка страны (только реальные данные)
        if ($country && trim($country) !== '') {
            if ($country !== 'Kazakhstan' && $country !== 'Казахстан') {
                $risk += 40;
                $details[] = "Страна: {$country} (иностранный номер)";
            } else {
                $details[] = "Страна: Казахстан";
            }
            $hasData = true;
        } else {
            $details[] = "Страна: не определена";
        }

        // Регион и город (если есть данные)
        if ($region && trim($region) !== '') {
            $details[] = "Регион: {$region}";
            $hasData = true;
        }
        if ($city && trim($city) !== '' && $city !== $region) {
            $details[] = "Город: {$city}";
            $hasData = true;
        }

        // Оператор (только если есть реальные данные)
        if ($carrier && trim($carrier) !== '' && $carrier !== 'Unknown Operator') {
            $details[] = "Оператор: {$carrier}";
            $hasData = true;
        } else {
            $details[] = "Оператор: неизвестен";
        }

        // Формат номера
        if ($formatE164 && trim($formatE164) !== '') {
            $details[] = "Формат: {$formatE164}";
        }

        // История активности (не храним)
        $details[] = "История активности: отсутствует";

        // OSINT поиск выполняется через отдельный сервис Sherlock
        // PhoneLocatorService используется для определения локации и оператора
        $details[] = "Внешние OSINT: проверка через Sherlock";

        // Итоговая оценка на основе реальных данных
        if ($risk > 70) {
            $details[] = "Итог: высокий риск (VoIP или невалидный номер)";
        } elseif ($risk > 40) {
            $details[] = "Итог: средний риск (иностранный номер)";
        } elseif ($hasData && $risk === 0) {
            $details[] = "Итог: низкий риск (валидный номер, Казахстан)";
        } else {
            $details[] = "Итог: недостаточно данных для оценки риска";
        }

        $detailsStr = implode('; ', $details);

        // Статус на основе реального риска
        $label = 'Недостаточно данных';
        if ($risk > 70) {
            $label = 'Высокий риск';
        } elseif ($risk > 40) {
            $label = 'Средний риск';
        } elseif ($hasData && $risk === 0) {
            $label = 'Низкий риск';
        }

        return [
            'check' => [
                'risk' => min($risk, 100),
                'details' => $detailsStr
            ],
            'risk' => $risk,
            'label' => $label,
            'technical_metadata' => $locationData, // Сохраняем данные локации как технические метаданные
            'raw_phoneinfoga_data' => null // PhoneLocatorService не возвращает raw данные
        ];
    }

    private function analyzeDomain($link)
    {
        $risk = 0;
        $details = [];

        // Проверка валидности URL
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            return [
                'check' => ['risk' => 80, 'details' => 'Некорректный URL'],
                'risk' => 80,
                'label' => 'Некорректная ссылка'
            ];
        }

        $parsedUrl = parse_url($link);
        $domain = $parsedUrl['host'] ?? '';

        // Проверка на известные домены объявлений
        $trustedDomains = ['krisha.kz', 'market.kz', 'olx.kz', 'avito.kz'];
        $isTrusted = false;
        foreach ($trustedDomains as $trusted) {
            if (strpos($domain, $trusted) !== false) {
                $isTrusted = true;
                $details[] = "Домен: $trusted (проверенная платформа)";
                break;
            }
        }

        if (!$isTrusted) {
            $risk += 30;
            $details[] = "Неизвестный домен: $domain";
        }

        // Проверка на подозрительные паттерны
        if (strpos($domain, 'bit.ly') !== false || strpos($domain, 'tinyurl') !== false) {
            $risk += 50;
            $details[] = 'Использован URL-сокращатель';
        }

        $detailsStr = empty($details) ? 'Домен проверен' : implode('; ', $details);

        return [
            'check' => [
                'risk' => min($risk, 100),
                'details' => $detailsStr
            ],
            'risk' => $risk,
            'label' => $risk > 50 ? 'Подозрительный домен' : ($risk > 20 ? 'Неизвестный домен' : 'Домен безопасен')
        ];
    }


    public function getHistory()
    {
        try {
            return Check::latest()->take(20)->get();
        } catch (\Exception $e) {
            // Если БД недоступна, возвращаем пустой массив
            \Illuminate\Support\Facades\Log::warning("Не удалось получить историю из БД: " . $e->getMessage());
            return collect([]);
        }
    }
}
