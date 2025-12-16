<?php

namespace App\Services;

use App\Models\Check;
use Illuminate\Support\Facades\Log;

class ListingCheckService
{
    protected ScrapyService $scrapyService;
    protected MLAnalysisService $mlAnalysisService;
    protected GeminiAIService $geminiAIService;

    public function __construct(
        ScrapyService $scrapyService,
        MLAnalysisService $mlAnalysisService,
        GeminiAIService $geminiAIService
    ) {
        $this->scrapyService = $scrapyService;
        $this->mlAnalysisService = $mlAnalysisService;
        $this->geminiAIService = $geminiAIService;
    }

    /**
     * Проверка объявления по URL или тексту
     * 
     * @param string|null $url URL объявления
     * @param string|null $text Текст объявления
     * @return Check Результат проверки
     */
    public function checkListing(?string $url = null, ?string $text = null): Check
    {
        $listingData = [];
        $parsedData = [];
        $source = 'manual';

        // Шаг 1: Парсинг данных (Scrapy)
        if ($url) {
            try {
                $parseResult = $this->scrapyService->parseListing($url);
                if ($parseResult['success']) {
                    $parsedData = $parseResult['data'];
                    $source = $parseResult['source'] ?? 'parsed';
                }
            } catch (\Exception $e) {
                Log::warning("Scrapy parsing failed: " . $e->getMessage());
            }
        }

        // Если есть текст - используем его
        if ($text && trim($text)) {
            $listingData = [
                'title' => '',
                'description' => trim($text),
                'full_text' => trim($text),
                'price' => '',
                'phone' => '',
                'location' => '',
                'images' => []
            ];
        } elseif (!empty($parsedData)) {
            // Используем распарсенные данные
            $listingData = array_merge([
                'title' => '',
                'description' => '',
                'full_text' => '',
                'price' => '',
                'phone' => '',
                'location' => '',
                'images' => []
            ], $parsedData);
        } else {
            // Если нет ни текста, ни распарсенных данных - используем URL как описание
            $listingData = [
                'title' => '',
                'description' => $url ?? '',
                'full_text' => $url ?? '',
                'price' => '',
                'phone' => '',
                'location' => '',
                'images' => []
            ];
        }

        // Шаг 2: ML анализ (scikit-learn)
        try {
            $mlAnalysis = $this->mlAnalysisService->analyzeListing($listingData);
        } catch (\Exception $e) {
            Log::error("ML Analysis failed: " . $e->getMessage());
            $mlAnalysis = [
                'success' => false,
                'risk_score' => 50,
                'risk_factors' => ['Ошибка при ML анализе'],
                'ml_features' => [],
                'model_confidence' => 0
            ];
        }

        // Формируем результат
        $riskScore = $mlAnalysis['risk_score'] ?? 0;
        $riskFactors = $mlAnalysis['risk_factors'] ?? [];

        // Шаг 3: Интерпретация через Gemini AI (ТОЛЬКО для объяснения)
        $context = [
            'url' => $url,
            'source' => $source,
            'parsed_successfully' => !empty($parsedData)
        ];
        
        try {
            $interpretation = $this->geminiAIService->interpretListingResults(
                !empty($parsedData) ? $parsedData : $listingData,
                $mlAnalysis,
                $context
            );
        } catch (\Exception $e) {
            Log::error("Gemini interpretation failed: " . $e->getMessage());
            $interpretation = [
                'summary' => 'Проверка объявления завершена',
                'interpretation' => 'Анализ выполнен с ограниченными возможностями',
                'risk_classification' => $riskScore > 70 ? 'High Risk' : ($riskScore > 40 ? 'Medium Risk' : 'Low Risk'),
                'key_findings' => $riskFactors,
                'recommendations' => [],
                'confidence' => 0.5
            ];
        }

        // Определяем вердикт
        $verdict = 'safe';
        if ($riskScore > 70) {
            $verdict = 'high_risk';
        } elseif ($riskScore > 40) {
            $verdict = 'medium_risk';
        }

        // Сохраняем результат в базу
        try {
            $check = Check::create([
                'phone' => $listingData['phone'] ?? null,
                'link' => $url,
                'type' => 'listing',
                'risk_score' => $riskScore,
                'verdict' => $verdict,
                'result' => [
                    'listing_data' => $listingData,
                    'parsed_data' => $parsedData,
                    'ml_analysis' => $mlAnalysis,
                    'interpretation' => $interpretation,
                    'risk_factors' => array_map(function($factor) {
                        return is_string($factor) ? ['label' => $factor] : $factor;
                    }, $riskFactors),
                    'source' => $source
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to save check to database: " . $e->getMessage());
            // Создаем объект Check без сохранения в БД
            $check = new Check([
                'phone' => $listingData['phone'] ?? null,
                'link' => $url,
                'type' => 'listing',
                'risk_score' => $riskScore,
                'verdict' => $verdict,
                'result' => [
                    'listing_data' => $listingData,
                    'parsed_data' => $parsedData,
                    'ml_analysis' => $mlAnalysis,
                    'interpretation' => $interpretation,
                    'risk_factors' => array_map(function($factor) {
                        return is_string($factor) ? ['label' => $factor] : $factor;
                    }, $riskFactors),
                    'source' => $source
                ]
            ]);
            $check->id = 0; // Временный ID
        }

        return $check;
    }
}

