<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Services\CheckService;
use App\Services\ListingCheckService;
use App\Services\ImageHashService;
use App\Services\PhishGuardService;

class CheckController extends Controller
{
    protected $checkService;
    protected $listingCheckService;
    protected $imageHashService;
    protected $phishGuardService;

    public function __construct(
        CheckService $checkService,
        ListingCheckService $listingCheckService,
        ImageHashService $imageHashService,
        PhishGuardService $phishGuardService,
    )
    {
        $this->checkService = $checkService;
        $this->listingCheckService = $listingCheckService;
        $this->imageHashService = $imageHashService;
        $this->phishGuardService = $phishGuardService;
    }

    public function index()
    {
        return view('check');
    }

    public function check(Request $request)
    {
        $link = $request->input('link');
        $phone = $request->input('phone');

        if (!$link && !$phone) {
            return back()->with('error', 'Введите ссылку или номер телефона');
        }

        try {
            $check = $this->checkService->performCheck($link, $phone);
            
            // Используем только реальные данные из результата проверки
            // НЕ генерируем мок-данные о находках в базах мошенников
            if (isset($check->result['reasons']) && !empty($check->result['reasons'])) {
                 $check->setRelation('reasons_mock', $check->result['reasons']);
            } else {
                // Только общие факты на основе реальных данных, без утверждений о находках
                $riskScore = $check->risk_score ?? 0;
                $reasons = [];

                // Используем только факты из risk_factors, не выдумываем
                if (isset($check->result['risk_factors']) && is_array($check->result['risk_factors'])) {
                    foreach ($check->result['risk_factors'] as $factor) {
                        if (isset($factor['label'])) {
                            $reasons[] = $factor['label'];
                        }
                    }
                }

                // Если нет факторов, используем только общие наблюдения на основе риска
                if (empty($reasons)) {
                    if ($riskScore > 70) {
                        $reasons[] = 'Высокий уровень риска на основе технических данных';
                    } elseif ($riskScore > 40) {
                        $reasons[] = 'Средний уровень риска';
                    } else {
                        $reasons[] = 'Низкий уровень риска';
                    }
                }
                
                $check->setRelation('reasons_mock', $reasons);
            }

            return view('result', ['check' => $check]);
        } catch (\Exception $e) {
            return back()->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }
    
    public function dashboard()
    {
        // Placeholder for dashboard stats
        return view('dashboard');
    }

    public function listing()
    {
        return view('check.listing');
    }

    /**
     * Обработка проверки объявления
     */
    public function checkListing(Request $request)
    {
        $url = trim($request->input('url', ''));
        $content = trim($request->input('content', ''));

        // Валидация
        if (empty($url) && empty($content)) {
            return back()->with('error', 'Введите ссылку на объявление или текст объявления');
        }

        // Если URL не пустой, проверяем что это валидный URL
        if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
            // Если это не URL, возможно это текст - используем как content
            if (empty($content)) {
                $content = $url;
                $url = null;
            } else {
                return back()->with('error', 'Введите корректную ссылку на объявление');
            }
        }

        try {
            $check = $this->listingCheckService->checkListing(
                !empty($url) ? $url : null,
                !empty($content) ? $content : null
            );
            
            // Формируем факторы риска для отображения
            $riskFactors = [];
            if (isset($check->result['risk_factors']) && is_array($check->result['risk_factors'])) {
                foreach ($check->result['risk_factors'] as $factor) {
                    if (is_string($factor)) {
                        $riskFactors[] = $factor;
                    } elseif (isset($factor['label'])) {
                        $riskFactors[] = $factor['label'];
                    }
                }
            }
            
            $check->setRelation('reasons_mock', $riskFactors);

            return view('result', ['check' => $check]);
        } catch (\Exception $e) {
            Log::error("Listing check error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()
                ->withInput()
                ->with('error', 'Ошибка при проверке объявления: ' . $e->getMessage());
        }
    }

    public function image()
    {
        return view('check.image');
    }

    /**
     * Обработка проверки изображений
     */
    public function checkImage(Request $request)
    {
        $validated = $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'file|max:5120|mimes:jpg,jpeg,png,webp,gif,pdf'
        ]);

        $filesMeta = [];
        $riskFactors = [];

        foreach ($request->file('images') as $uploadedFile) {
            $path = $uploadedFile->store('uploads/images', 'public');
            $absolutePath = $uploadedFile->getRealPath();

            $hash = hash_file('sha256', $absolutePath);
            $mime = $uploadedFile->getMimeType();
            $size = $uploadedFile->getSize();

            $width = null;
            $height = null;
            if (str_starts_with($mime, 'image/')) {
                [$width, $height] = @getimagesize($absolutePath) ?: [null, null];
                if ($width !== null && $width < 400) {
                    $riskFactors[] = 'Низкое разрешение изображения';
                }
            }

            if ($size < 10 * 1024) {
                $riskFactors[] = 'Очень маленький размер файла';
            }

            $filesMeta[] = [
                'original_name' => $uploadedFile->getClientOriginalName(),
                'stored_path' => $path,
                'absolute_path' => $absolutePath,
                'url' => Storage::url($path),
                'mime' => $mime,
                'size_kb' => round($size / 1024, 2),
                'hash_sha256' => $hash,
                'width' => $width,
                'height' => $height,
                // Заполним позже после анализа истории
                'duplicates' => [
                    'count' => 0,
                    'items' => [],
                    'first_seen' => null,
                ],
            ];
        }

        // Единый Python-анализ изображений (OpenCV + imagehash + imagededup + SerpAPI)
        $pythonResult = null;
        try {
            $scriptsPath = base_path('ml_scripts');
            $dataFile = $scriptsPath . '/image_files_payload.json';
            if (!is_dir($scriptsPath)) {
                mkdir($scriptsPath, 0755, true);
            }
            file_put_contents($dataFile, json_encode($filesMeta, JSON_UNESCAPED_UNICODE));

            $scriptPath = $scriptsPath . '/image_analysis.py';
            $pythonPath = env('PYTHON_PATH', 'python3');

            $process = \Illuminate\Support\Facades\Process::run([
                $pythonPath,
                $scriptPath,
                escapeshellarg($dataFile),
            ], null, [
                'SERPAPI_KEY' => env('SERPAPI_KEY', ''),
            ]);

            if ($process->successful()) {
                $pythonResult = json_decode($process->output(), true);
            } else {
                Log::warning('Image analysis python failed: ' . $process->errorOutput());
            }
        } catch (\Throwable $e) {
            Log::error('Image analysis error: ' . $e->getMessage());
        }

        // Структурированный результат для сохранения/отображения
        $imageResult = is_array($pythonResult) ? $pythonResult : [
            'verdict' => [
                'level' => 'safe',
                'risk_score' => 0,
                'sources' => ['Internal DB'],
                'explanation' => ['Локальный Python-анализ недоступен, использованы только базовые метаданные.'],
            ],
            'image_fingerprint_profile' => [
                'hashes' => [],
                'visual_signature' => null,
                'similarity_score_max' => 0.0,
                'status' => 'unavailable',
                'source' => 'none',
                'error' => 'python_analysis_failed',
            ],
            'internal_db_matches' => [
                'matches_count' => 0,
                'first_seen' => null,
                'last_seen' => null,
                'related_checks' => [],
                'source' => 'Internal DB',
            ],
            'reverse_image_osint' => [
                'serpapi' => [
                    'ran' => false,
                    'results_count' => 0,
                    'domains' => [],
                    'limit_reached' => false,
                    'error' => 'not_ran',
                ],
                'rapidapi' => [
                    'ran' => false,
                    'reason' => 'not_configured',
                ],
            ],
            'technical_metadata' => [
                'files' => $filesMeta,
            ],
        ];

        // Анализ повторяемости по внутренней БД (Internal DB) — дополняем блок internal_db_matches
        $maxDuplicates = 0;
        try {
            $existingChecks = \App\Models\Check::where('type', 'image')->get();

            $historyFiles = [];
            foreach ($existingChecks as $existing) {
                $result = $existing->result ?? [];
                foreach (($result['technical_metadata']['files'] ?? $result['files'] ?? []) as $histFile) {
                    if (!empty($histFile['hash_sha256'])) {
                        $historyFiles[] = [
                            'check_id' => $existing->id,
                            'hash_sha256' => $histFile['hash_sha256'],
                            'original_name' => $histFile['original_name'] ?? null,
                            'created_at' => $existing->created_at ?? null,
                        ];
                    }
                }
            }

            $relatedChecks = [];
            foreach ($filesMeta as $file) {
                $fileDuplicates = array_values(array_filter(
                    $historyFiles,
                    fn($hf) => $hf['hash_sha256'] === $file['hash_sha256']
                ));

                $count = count($fileDuplicates);
                if ($count > $maxDuplicates) {
                    $maxDuplicates = $count;
                }

                foreach ($fileDuplicates as $dup) {
                    $relatedChecks[] = [
                        'check_id' => $dup['check_id'],
                        'created_at' => $dup['created_at'],
                        'original_name' => $dup['original_name'],
                    ];
                }
            }

            $imageResult['internal_db_matches']['matches_count'] = $maxDuplicates;
            $imageResult['internal_db_matches']['related_checks'] = $relatedChecks;
            if (!empty($relatedChecks)) {
                $dates = collect($relatedChecks)->pluck('created_at')->filter()->sort();
                $imageResult['internal_db_matches']['first_seen'] = $dates->first();
                $imageResult['internal_db_matches']['last_seen'] = $dates->last();
            }
        } catch (\Exception $e) {
            Log::warning('Image check: failed to analyze duplicates from Internal DB: ' . $e->getMessage());
        }

        // Финальный risk_score и вердикт только из структурированного блока verdict
        $riskScore = (int)($imageResult['verdict']['risk_score'] ?? 0);
        $verdict = $imageResult['verdict']['level'] ?? 'safe';

        // Сохраняем результат (если БД доступна)
        try {
            $check = \App\Models\Check::create([
                'type' => 'image',
                'link' => null,
                'phone' => null,
                'risk_score' => $riskScore,
                'verdict' => $verdict,
                'result' => $imageResult,
            ]);
        } catch (\Exception $e) {
            Log::warning('Image check: DB unavailable, returning in-memory result: ' . $e->getMessage());
            $check = new \App\Models\Check([
                'id' => 0,
                'type' => 'image',
                'risk_score' => $riskScore,
                'verdict' => $verdict,
                'result' => $imageResult,
            ]);
        }

        return view('check.image_result', [
            'check' => $check,
            'imageResult' => $imageResult,
        ]);
    }

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

        // Проверка не требуется - используем Python скрипты напрямую
        // PhishGuardService теперь работает через Python скрипты, а не через HTTP API

        try {
            // ВСЕГДА используем полный AI-аудит для реальной проверки ссылок
            $checkResult = null;
            $fullAudit = null;
            
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
                    
                    // Используем базовую классификацию, если она есть
                    if (isset($basicClassification)) {
                        $classification = $basicClassification;
                        // Добавляем пометку, что использована базовая проверка
                        $classification['reason'] = ($classification['reason'] ?? '') . ' (Детальный AI-анализ временно недоступен)';
                    } else {
                        $classification = [
                            'verdict' => 'unknown',
                            'label' => 'Не удалось проверить',
                            'action' => 'unknown',
                            'score' => 0.5,
                            'reason' => 'AI-анализ недоступен: ' . $errorMsg
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

    public function connections()
    {
        return view('connections');
    }

    public function profile()
    {
        return view('profile');
    }

    public function analytics()
    {
        return view('analytics');
    }

    public function settings()
    {
        return view('settings');
    }

    public function history()
    {
        $checks = $this->checkService->getHistory();
        return view('history', ['checks' => $checks]);
    }
}
