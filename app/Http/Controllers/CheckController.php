<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PhishGuardService;

class CheckController extends Controller
{
    protected $phishGuardService;

    public function __construct(PhishGuardService $phishGuardService)
    {
        $this->phishGuardService = $phishGuardService;
    }

    /**
     * Страница проверки ссылки
     */
    public function link()
    {
        return view('check.link');
    }

    /**
     * Проверка ссылки/домена через PhishGuard
     */
    public function checkLink(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ], [
            'url.required' => 'Введите ссылку или домен',
        ]);

        try {
            // ВСЕГДА используем полный AI-аудит для реальной проверки ссылок
            $checkResult = null;
            $fullAudit = null;
            $basicClassification = null;
            
            // 1. Сначала делаем базовую проверку правил (для whitelist - быстрый пропуск)
            try {
                $checkResult = $this->phishGuardService->checkUrl($data['url']);
                $basicClassification = $this->phishGuardService->classify($checkResult);
                
                // Если сайт в whitelist - сразу разрешаем без AI проверки
                if ($basicClassification['action'] === 'allow' && ($checkResult['reason'] ?? '') !== 'URL выглядит безопасно') {
                    // Это whitelist - доверяем
                    return view('check.link', [
                        'url' => $data['url'],
                        'check' => $checkResult,
                        'classification' => $basicClassification,
                        'fullAudit' => null,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Basic check failed: ' . $e->getMessage());
            }
            
            // 2. ВСЕГДА выполняем полный AI-аудит для реального анализа содержимого сайта
            try {
                Log::info('Starting full AI audit for URL: ' . $data['url']);
                $fullAudit = $this->phishGuardService->fullAudit($data['url']);
                
                // fullAudit теперь всегда возвращает массив (может содержать error)
                if ($fullAudit && !isset($fullAudit['error'])) {
                    // Используем результаты AI-аудита как основу для классификации
                    $verdict = mb_strtolower($fullAudit['verdict'] ?? '', 'UTF-8');
                    $risk = strtolower($fullAudit['risk'] ?? '');
                    
                    // AI возвращает phishing_probability как число 0-100, конвертируем в 0-1
                    $phishingProb = $fullAudit['phishing_probability'] ?? 0;
                    if ($phishingProb > 1) {
                        $score = $phishingProb / 100; // Конвертируем из 0-100 в 0-1
                    } else {
                        $score = $phishingProb;
                    }
                    
                    // AI возвращает вердикт на русском или английском, risk на английском
                    $isDanger = false;
                    $isWarning = false;
                    
                    // Проверяем вердикт (может быть на русском: "Опасно", "Подозрительно", "Безопасно")
                    if (in_array($verdict, ['опасно', 'danger', 'dangerous']) || 
                        in_array($risk, ['high', 'danger']) || 
                        $score >= 0.7) {
                        $isDanger = true;
                    } elseif (in_array($verdict, ['подозрительно', 'warning', 'suspicious']) || 
                              in_array($risk, ['medium', 'warning']) || 
                              ($score >= 0.4 && $score < 0.7)) {
                        $isWarning = true;
                    }
                    
                    // Классификация на основе AI-анализа
                    if ($isDanger) {
                        $classification = [
                            'verdict' => 'danger',
                            'label' => 'Опасный сайт',
                            'action' => 'block',
                            'score' => $score,
                            'reason' => $fullAudit['explanation'] ?? 'AI обнаружил признаки фишинга'
                        ];
                    } elseif ($isWarning) {
                        $classification = [
                            'verdict' => 'warning',
                            'label' => 'Подозрительный сайт',
                            'action' => 'warn',
                            'score' => $score,
                            'reason' => $fullAudit['explanation'] ?? 'AI обнаружил подозрительные признаки'
                        ];
                    } else {
                        // AI говорит что безопасно
                        $classification = [
                            'verdict' => 'safe',
                            'label' => 'Безопасный сайт',
                            'action' => 'allow',
                            'score' => $score,
                            'reason' => $fullAudit['explanation'] ?? 'AI проверил сайт и признал его безопасным'
                        ];
                    }
                } elseif (isset($fullAudit['error'])) {
                    // Если AI-аудит вернул ошибку - используем базовую проверку
                    $errorMsg = $fullAudit['error'];
                    Log::warning('AI audit failed or returned error: ' . $errorMsg . ', using basic check');
                    
                    // Проверяем, является ли это ошибкой 401 (неверный ключ OpenRouter)
                    $is401Error = strpos($errorMsg, '401') !== false || strpos($errorMsg, 'Unauthorized') !== false;
                    
                    // Используем базовую классификацию, если она есть
                    if (isset($basicClassification)) {
                        $classification = $basicClassification;
                        // Добавляем пометку об ошибке
                        if ($is401Error) {
                            $classification['reason'] = ($classification['reason'] ?? '') . ' (OpenRouter API ключ недействителен, используется базовая проверка)';
                        } else {
                            $classification['reason'] = ($classification['reason'] ?? '') . ' (Детальный AI-анализ временно недоступен)';
                        }
                    } else {
                        $classification = [
                            'verdict' => 'unknown',
                            'label' => 'Не удалось проверить',
                            'action' => 'unknown',
                            'score' => 0.5,
                            'reason' => $is401Error 
                                ? 'OpenRouter API ключ недействителен. Проверьте настройки в .env файле.'
                                : 'AI-анализ недоступен: ' . $errorMsg
                        ];
                    }
                } else {
                    // Если ответ пустой или некорректный
                    Log::warning('AI audit returned empty or invalid response, using basic check');
                    $classification = $basicClassification ?? [
                        'verdict' => 'unknown',
                        'label' => 'Не удалось проверить',
                        'action' => 'unknown',
                        'score' => 0.5,
                        'reason' => 'AI-анализ вернул некорректный ответ'
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Full AI audit failed: ' . $e->getMessage());
                // Fallback на базовую проверку
                $classification = $basicClassification ?? [
                    'verdict' => 'unknown',
                    'label' => 'Ошибка анализа',
                    'action' => 'unknown',
                    'score' => 0.5,
                    'reason' => 'Ошибка при выполнении AI-анализа: ' . $e->getMessage()
                ];
                $fullAudit = ['error' => 'Ошибка при выполнении AI-анализа: ' . $e->getMessage()];
            }

            // Убеждаемся, что classification всегда есть
            if (!isset($classification)) {
                $classification = [
                    'verdict' => 'unknown',
                    'label' => 'Ошибка анализа',
                    'action' => 'unknown',
                    'score' => 0.5,
                    'reason' => 'Не удалось выполнить анализ'
                ];
            }
            
            return view('check.link', [
                'url' => $data['url'],
                'check' => $checkResult,
                'classification' => $classification,
                'fullAudit' => $fullAudit,
            ]);
        } catch (\Throwable $e) {
            Log::error('PhishGuard link check error: ' . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Ошибка при проверке ссылки: ' . $e->getMessage());
        }
    }
}
