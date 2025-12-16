<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAIService
{
    private string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    public function __construct()
    {
        // Ключ из .env или дефолтный
        $this->apiKey = env('GEMINI_API_KEY', 'AIzaSyCuSqkI9RHVFoYIurqfkmQUoMgkl7Ct8o4');
    }

    /**
     * Нормализация и структурирование данных номера через Gemini AI
     */
    public function normalizePhoneData(array $phoneInfogaData, array $context = []): array
    {
        $prompt = $this->buildNormalizationPrompt($phoneInfogaData, $context);

        try {
            $response = Http::timeout(15)->post($this->apiUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 2000,
                ]
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
                    
                    // Очистка JSON от markdown блоков
                    $text = preg_replace('/```json\s*/', '', $text);
                    $text = preg_replace('/```\s*/', '', $text);
                    $text = trim($text);
                    
                    $normalized = json_decode($text, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $normalized;
                    }
                }
            }
            
            // Если rate limit или другая ошибка - используем fallback
            if ($response->status() === 429) {
                Log::warning("Gemini AI rate limit exceeded, using fallback");
            } else {
                Log::warning("Gemini AI failed to normalize data", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 200)
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error("Gemini AI error: " . $e->getMessage());
        }

        // Fallback: возвращаем базовую нормализацию на основе реальных данных PhoneInfoga
        return $this->getFallbackNormalizedData($phoneInfogaData);
    }

    /**
     * Анализ контекста для определения рисков через Gemini AI
     */
    public function analyzeFraudContext(array $phoneData, array $context): array
    {
        $prompt = $this->buildAnalysisPrompt($phoneData, $context);

        try {
            $response = Http::timeout(15)->post($this->apiUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 1000,
                ]
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
                    $text = preg_replace('/```json\s*/', '', $text);
                    $text = preg_replace('/```\s*/', '', $text);
                    $text = trim($text);
                    
                    $analysis = json_decode($text, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $analysis;
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Gemini AI analysis error: " . $e->getMessage());
        }

        return [];
    }

    private function buildNormalizationPrompt(array $phoneInfogaData, array $context): string
    {
        return <<<PROMPT
Ты — backend-аналитический движок антифрод-платформы AntiFraud.KZ.
Твоя задача — НОРМАЛИЗОВАТЬ и СТРУКТУРИРОВАТЬ данные о номере телефона в СТРОГО ОПРЕДЕЛЁННОЙ СТРУКТУРЕ.

❗ КРИТИЧЕСКИ ВАЖНО:
- НЕ придумывай результаты проверок
- НЕ утверждай, что номер "найден в базах мошенников" если этого нет в данных
- НЕ утверждай, что номер "обнаружен в соцсетях" если это только поисковые ссылки
- НЕ генерируй количество ссылок, аккаунтов, совпадений
- Используй ТОЛЬКО данные из PhoneInfoga, не выдумывай факты

ВХОДНЫЕ ДАННЫЕ PhoneInfoga:
{$this->formatDataForPrompt($phoneInfogaData)}

КОНТЕКСТ:
{$this->formatDataForPrompt($context)}

📤 ОБЯЗАТЕЛЬНАЯ СТРУКТУРА ВЫХОДА (JSON):
{
  "phone_e164": "<string or null>",
  "phone_valid": <true/false>,
  "phone_type": "<mobile/voip/landline/unknown>",
  "country": "<string or null>",
  "country_code": "<string or null>",
  "region": "<string or null>",
  "city": "<string or null>",
  "operator_name": "<string or null>",
  "operator_type": "<mobile/virtual/unknown>",
  "carrier_source": "<phoneinfoga/libphonenumber/combined>",
  "timezone": "<string or null>",
  "international_format": "<string or null>",
  "osint_signals": {
    "found_in_public_sources": <true/false - ТОЛЬКО если в данных PhoneInfoga есть реальные находки, не поисковые ссылки>,
    "virtual_number_detected": <true/false - на основе line_type из PhoneInfoga>,
    "disposable_provider_detected": <true/false - ТОЛЬКО если есть реальные данные, не поисковые ссылки>
  },
  "data_confidence": {
    "level": "<high/medium/low>",
    "reason": "<string>"
  }
}

Верни ТОЛЬКО JSON, без пояснений.
PROMPT;
    }

    private function buildAnalysisPrompt(array $phoneData, array $context): string
    {
        return <<<PROMPT
Проанализируй данные о номере телефона и определи уровень риска мошенничества.

❗ КРИТИЧЕСКИ ВАЖНО:
- НЕ придумывай результаты проверок
- НЕ утверждай о находках в базах мошенников без реальных данных
- НЕ утверждай о находках в соцсетях - это только поисковые ссылки
- Используй ТОЛЬКО факты из предоставленных данных

ДАННЫЕ:
{$this->formatDataForPrompt($phoneData)}

КОНТЕКСТ:
{$this->formatDataForPrompt($context)}

Верни JSON с анализом на основе ТОЛЬКО реальных данных:
{
  "risk_score": <0-100 - на основе реальных фактов: VoIP, невалидный номер, иностранный номер>,
  "risk_factors": ["только реальные факты из данных"],
  "recommendations": ["общие рекомендации"],
  "verdict": "<Low Risk/Medium Risk/High Risk>"
}

Верни ТОЛЬКО JSON.
PROMPT;
    }

    private function formatDataForPrompt(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function getFallbackNormalizedData(array $phoneInfogaData): array
    {
        $e164 = $phoneInfogaData['format_e164'] ?? '';
        $code = '';
        
        if (preg_match('/\+(\d{1,3})/', $e164, $matches)) {
            $code = $matches[1];
        }

        // Используем ТОЛЬКО реальные данные из PhoneInfoga
        $country = $phoneInfogaData['country'] ?? null;
        $carrier = $phoneInfogaData['carrier'] ?? null;
        $lineType = $phoneInfogaData['line_type'] ?? null;

        return [
            'phone_e164' => $e164,
            'phone_valid' => $phoneInfogaData['valid'] ?? false,
            'phone_type' => $lineType ? $this->mapLineType($lineType) : 'unknown',
            'country' => $country ?? null,
            'country_code' => $code ? '+' . $code : null,
            'region' => $phoneInfogaData['region'] ?? null,
            'city' => null,
            'operator_name' => $carrier ?? null,
            'operator_type' => $lineType ? $this->mapOperatorType($lineType) : 'unknown',
            'carrier_source' => 'phoneinfoga',
            'timezone' => $phoneInfogaData['timezone'] ?? null,
            'international_format' => $phoneInfogaData['format_international'] ?? $e164,
            'osint_signals' => [
                'found_in_public_sources' => false, // НЕ утверждаем о находках без реальных данных
                'virtual_number_detected' => ($lineType ?? '') === 'VoIP',
                'disposable_provider_detected' => false // НЕ утверждаем без реальных данных
            ],
            'data_confidence' => [
                'level' => empty($phoneInfogaData['error']) ? 'medium' : 'low',
                'reason' => $phoneInfogaData['error'] ?? 'Data from PhoneInfoga API'
            ]
        ];
    }

    private function mapLineType(string $type): string
    {
        $type = strtolower($type);
        if (str_contains($type, 'mobile') || str_contains($type, 'cell')) {
            return 'mobile';
        } elseif (str_contains($type, 'voip') || str_contains($type, 'virtual')) {
            return 'voip';
        } elseif (str_contains($type, 'landline') || str_contains($type, 'fixed')) {
            return 'landline';
        }
        return 'unknown';
    }

    private function mapOperatorType(string $lineType): string
    {
        $lineType = strtolower($lineType);
        if (str_contains($lineType, 'voip') || str_contains($lineType, 'virtual')) {
            return 'virtual';
        }
        return 'mobile';
    }

    /**
     * ИНТЕРПРЕТАЦИЯ результатов проверки объявления
     * ТОЛЬКО для объяснения, классификации и summary
     * НЕ для поиска данных или фактов
     */
    public function interpretListingResults(array $parsedData, array $mlAnalysis, array $context = []): array
    {
        $prompt = $this->buildInterpretationPrompt($parsedData, $mlAnalysis, $context);

        try {
            $response = Http::timeout(15)->post($this->apiUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1500,
                ]
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
                    
                    // Очистка JSON от markdown блоков
                    $text = preg_replace('/```json\s*/', '', $text);
                    $text = preg_replace('/```\s*/', '', $text);
                    $text = trim($text);
                    
                    $interpretation = json_decode($text, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $interpretation;
                    }
                }
            }
            
            Log::warning("Gemini AI interpretation failed", [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 200)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Gemini AI interpretation error: " . $e->getMessage());
        }

        // Fallback интерпретация
        return $this->getFallbackInterpretation($parsedData, $mlAnalysis);
    }

    /**
     * Построение промпта для интерпретации
     */
    private function buildInterpretationPrompt(array $parsedData, array $mlAnalysis, array $context): string
    {
        return <<<PROMPT
Ты — аналитический движок для интерпретации результатов проверки объявления на платформе AntiFraud.KZ.

❗ КРИТИЧЕСКИ ВАЖНО:
- Твоя задача ТОЛЬКО интерпретировать, объяснять и классифицировать УЖЕ ПОЛУЧЕННЫЕ данные
- НЕ ищи данные в интернете
- НЕ делай предположения о фактах, которых нет в данных
- НЕ утверждай о находках в базах мошенников без реальных данных
- Используй ТОЛЬКО предоставленные данные для объяснения

ДАННЫЕ ОБЪЯВЛЕНИЯ (парсинг):
{$this->formatDataForPrompt($parsedData)}

ML АНАЛИЗ (scikit-learn):
{$this->formatDataForPrompt($mlAnalysis)}

КОНТЕКСТ:
{$this->formatDataForPrompt($context)}

📤 ЗАДАЧА:
Объясни результаты проверки, классифицируй уровень риска и предоставь summary на основе ТОЛЬКО предоставленных данных.

Верни JSON:
{
  "summary": "<краткое резюме проверки на основе данных>",
  "interpretation": "<детальное объяснение результатов>",
  "risk_classification": "<Low Risk/Medium Risk/High Risk>",
  "key_findings": ["найденные факты из данных"],
  "recommendations": ["рекомендации на основе анализа"],
  "confidence": <0.0-1.0 - уверенность в интерпретации>
}

Верни ТОЛЬКО JSON, без пояснений.
PROMPT;
    }

    /**
     * Fallback интерпретация
     */
    private function getFallbackInterpretation(array $parsedData, array $mlAnalysis): array
    {
        $riskScore = $mlAnalysis['risk_score'] ?? 0;
        $riskFactors = $mlAnalysis['risk_factors'] ?? [];
        
        $riskClassification = 'Low Risk';
        if ($riskScore > 70) {
            $riskClassification = 'High Risk';
        } elseif ($riskScore > 40) {
            $riskClassification = 'Medium Risk';
        }
        
        $summary = "Проверка объявления завершена. Уровень риска: {$riskClassification} ({$riskScore}/100).";
        
        if (!empty($riskFactors)) {
            $summary .= " Обнаружены факторы риска: " . implode(', ', array_slice($riskFactors, 0, 3));
        }
        
        return [
            'summary' => $summary,
            'interpretation' => 'Анализ выполнен на основе извлеченных признаков объявления. ' . 
                              (count($riskFactors) > 0 ? 'Обнаружены подозрительные признаки.' : 'Подозрительных признаков не обнаружено.'),
            'risk_classification' => $riskClassification,
            'key_findings' => $riskFactors,
            'recommendations' => $this->generateRecommendations($riskScore, $riskFactors),
            'confidence' => 0.6
        ];
    }

    /**
     * Генерация рекомендаций
     */
    private function generateRecommendations(int $riskScore, array $riskFactors): array
    {
        $recommendations = [];
        
        if ($riskScore > 70) {
            $recommendations[] = 'Высокий уровень риска. Рекомендуется дополнительная проверка.';
        } elseif ($riskScore > 40) {
            $recommendations[] = 'Средний уровень риска. Рекомендуется осторожность.';
        } else {
            $recommendations[] = 'Низкий уровень риска. Объявление выглядит безопасно.';
        }
        
        if (in_array('Отсутствие контактного телефона', $riskFactors)) {
            $recommendations[] = 'Рекомендуется запросить контактный телефон у продавца.';
        }
        
        if (in_array('Отсутствие фотографий', $riskFactors)) {
            $recommendations[] = 'Рекомендуется запросить фотографии товара.';
        }
        
        return $recommendations;
    }
}

