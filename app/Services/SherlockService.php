<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SherlockService
{
    private string $sherlockPath;
    private int $timeout;
    
    public function __construct()
    {
        // Путь к Sherlock (может быть через Docker или локально)
        $this->sherlockPath = env('SHERLOCK_PATH', '/home/nurbol/HACKATON_TURKISTAN/sherlock');
        $this->timeout = env('SHERLOCK_TIMEOUT', 5); // 5 секунд максимум (очень быстрый поиск)
    }
    
    /**
     * Получить путь к виртуальному окружению Sherlock
     */
    private function getSherlockVenvPath(): string
    {
        return $this->sherlockPath . '/venv/bin/sherlock';
    }

    /**
     * Поиск аккаунтов в соцсетях по username через Sherlock
     * 
     * @param string $username Username для поиска
     * @return array Результаты поиска
     */
    public function searchUsername(string $username): array
    {
        if (empty($username)) {
            return [
                'status' => 'error',
                'error' => 'Username не указан',
                'accounts' => []
            ];
        }

        try {
            // Очистка username от спецсимволов
            $cleanUsername = preg_replace('/[^a-zA-Z0-9._-]/', '', $username);
            
            if (empty($cleanUsername)) {
                return [
                    'status' => 'error',
                    'error' => 'Некорректный username',
                    'accounts' => []
                ];
            }

            Log::info("Sherlock search started for username: {$cleanUsername}");

            // Запуск Sherlock через Python CLI
            $result = $this->runSherlockCLI($cleanUsername);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("Sherlock error: " . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'accounts' => []
            ];
        }
    }

    /**
     * Поиск по номеру телефона (пробуем использовать номер как username)
     * 
     * @param string $phone Номер телефона
     * @return array Результаты поиска
     */
    public function searchByPhone(string $phone): array
    {
        // Очистка номера от спецсимволов для использования как username
        $phoneUsername = preg_replace('/[^0-9]/', '', $phone);
        
        // Пробуем разные варианты username из номера
        $usernames = [];
        
        // Вариант 1: Полный номер без + и пробелов
        if (!empty($phoneUsername)) {
            $usernames[] = $phoneUsername;
        }
        
        // Вариант 2: Последние 10 цифр (часто используется)
        if (strlen($phoneUsername) > 10) {
            $usernames[] = substr($phoneUsername, -10);
        }
        
        // Вариант 3: Последние 9 цифр
        if (strlen($phoneUsername) > 9) {
            $usernames[] = substr($phoneUsername, -9);
        }

        $allAccounts = [];
        $errors = [];

        foreach ($usernames as $username) {
            if (empty($username) || strlen($username) < 5) {
                continue; // Пропускаем слишком короткие
            }
            
            $result = $this->searchUsername($username);
            
            if ($result['status'] === 'success' && !empty($result['accounts'])) {
                $allAccounts = array_merge($allAccounts, $result['accounts']);
            } elseif ($result['status'] === 'error') {
                $errors[] = $result['error'] ?? 'Unknown error';
            }
        }

        return [
            'status' => !empty($allAccounts) ? 'success' : 'not-found',
            'accounts' => $this->deduplicateAccounts($allAccounts),
            'errors' => $errors,
            'searched_usernames' => $usernames
        ];
    }

    /**
     * Запуск Sherlock через CLI
     */
    private function runSherlockCLI(string $username): array
    {
        try {
            // Проверяем, доступен ли Sherlock через Docker
            $dockerAvailable = $this->tryDockerSherlock($username);
            if ($dockerAvailable['status'] !== 'not-available') {
                return $dockerAvailable;
            }

            // Пробуем через локальный Python
            return $this->tryLocalSherlock($username);
            
        } catch (\Exception $e) {
            Log::error("Sherlock CLI execution error: " . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'accounts' => []
            ];
        }
    }

    /**
     * Попытка запуска через Docker
     */
    private function tryDockerSherlock(string $username): array
    {
        try {
            $command = "docker run --rm -i sherlock/sherlock:latest {$username} --print-found --no-color 2>&1";
            
            $process = Process::timeout($this->timeout)->run($command);
            
            if ($process->failed()) {
                // Docker может быть недоступен
                return ['status' => 'not-available'];
            }
            
            $output = $process->output();
            return $this->parseSherlockOutput($output, $username);
            
        } catch (\Exception $e) {
            return ['status' => 'not-available'];
        }
    }

    /**
     * Попытка запуска через локальный Python
     */
    private function tryLocalSherlock(string $username): array
    {
        try {
            // Пробуем через виртуальное окружение
            $venvPath = $this->getSherlockVenvPath();
            if (file_exists($venvPath)) {
                $command = "{$venvPath} {$username} --print-found --no-color 2>&1";
                $process = Process::timeout($this->timeout)->run($command);
                
                if (!$process->failed()) {
                    return $this->parseSherlockOutput($process->output(), $username);
                }
            }
            
            // Пробуем через pipx/pip установленный sherlock
            $command = "sherlock {$username} --print-found --no-color 2>&1";
            $process = Process::timeout($this->timeout)->run($command);
            
            if (!$process->failed()) {
                return $this->parseSherlockOutput($process->output(), $username);
            }
            
            // Пробуем через python -m с виртуальным окружением
            $venvPython = $this->sherlockPath . '/venv/bin/python3';
            if (file_exists($venvPython)) {
                $command = "cd {$this->sherlockPath} && {$venvPython} -m sherlock_project.sherlock " . escapeshellarg($username) . " --print-found --no-color --timeout 5 2>&1";
                Log::info("Sherlock command: {$command}");
                $process = Process::timeout($this->timeout)->run($command);
                
                $output = $process->output();
                $errorOutput = $process->errorOutput();
                
                Log::info("Sherlock output length: " . strlen($output));
                Log::info("Sherlock error output: " . $errorOutput);
                
                if (!$process->failed() || strlen($output) > 0) {
                    $result = $this->parseSherlockOutput($output, $username);
                    if ($result['status'] !== 'error' || !empty($result['accounts'])) {
                        return $result;
                    }
                }
            }
            
            return [
                'status' => 'error',
                'error' => 'Sherlock не доступен. Установите через: pip install sherlock-project',
                'accounts' => []
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'accounts' => []
            ];
        }
    }

    /**
     * Парсинг вывода Sherlock
     */
    private function parseSherlockOutput(string $output, string $username): array
    {
        $accounts = [];
        
        // Sherlock выводит результаты в формате:
        // [+] SiteName: https://site.com/username
        // или
        // [*] SiteName: Not Found
        
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Пропускаем пустые строки и служебную информацию
            if (empty($line) || 
                strpos($line, '[*]') === 0 || 
                strpos($line, '[-]') === 0 ||
                strpos($line, 'Searching') !== false ||
                strpos($line, 'Checking') !== false) {
                continue;
            }
            
            // Ищем строки с [+] (найдено)
            if (preg_match('/\[\+\]\s+([^:]+):\s*(.+)/', $line, $matches)) {
                $siteName = trim($matches[1]);
                $url = trim($matches[2]);
                
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $accounts[] = [
                        'site' => $siteName,
                        'url' => $url,
                        'username' => $username
                    ];
                }
            }
        }
        
        return [
            'status' => !empty($accounts) ? 'success' : 'not-found',
            'accounts' => $accounts,
            'count' => count($accounts)
        ];
    }

    /**
     * Удаление дубликатов аккаунтов
     */
    private function deduplicateAccounts(array $accounts): array
    {
        $unique = [];
        $seen = [];
        
        foreach ($accounts as $account) {
            $key = $account['site'] . '|' . $account['url'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $account;
            }
        }
        
        return $unique;
    }
}

