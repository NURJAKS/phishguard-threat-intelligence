# Руководство по интеграции модуля проверки ссылок

Это руководство поможет вам интегрировать модуль проверки ссылок в существующий Laravel проект.

## 📦 Что нужно скопировать

### 1. PHP файлы

```bash
# Сервис
app/Services/PhishGuardService.php

# Контроллер (или метод checkLink из CheckController)
app/Http/Controllers/CheckController.php
# Метод: checkLink() - строки ~380-530
```

### 2. Python скрипты

```bash
# Вся папка python_scripts
python_scripts/
├── phishguard_audit.py
├── ai_analyzer.py
├── domain_info.py
├── rules.json
└── official_sources.json
```

### 3. Frontend

```bash
# View для страницы проверки
resources/views/check/link.blade.php
```

### 4. Конфигурация

```bash
# Добавить в config/services.php секцию 'openrouter'
```

## 🔧 Пошаговая интеграция

### Шаг 1: Копирование файлов

```bash
# В вашем проекте
mkdir -p app/Services
mkdir -p python_scripts
mkdir -p resources/views/check

# Копируем файлы
cp Project_Gos/app/Services/PhishGuardService.php app/Services/
cp Project_Gos/python_scripts/* python_scripts/
cp Project_Gos/resources/views/check/link.blade.php resources/views/check/
```

### Шаг 2: Добавление маршрутов

В `routes/web.php`:

```php
use App\Http\Controllers\CheckController;

Route::get('/check/link', [CheckController::class, 'link'])->name('check.link');
Route::post('/check/link', [CheckController::class, 'checkLink'])->name('check.link.perform');
```

### Шаг 3: Добавление метода в контроллер

Если у вас уже есть `CheckController`, добавьте методы:

```php
public function link()
{
    return view('check.link');
}

public function checkLink(Request $request)
{
    // Код из CheckController::checkLink() (строки ~380-530)
}
```

### Шаг 4: Установка Python зависимостей

```bash
# Создайте виртуальное окружение (если еще нет)
python3 -m venv venv
source venv/bin/activate

# Установите зависимости
pip install -r python_scripts/requirements.txt
```

### Шаг 5: Настройка .env

Добавьте в ваш `.env`:

```env
OPENROUTER_API_KEY=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
OPENROUTER_MODEL=xiaomi/mimo-v2-flash:free
GOOGLE_API_KEY=AIzaSyBFvQQz_PQdg4jTpwefpfPuiAyV7OMT9aw
```

### Шаг 6: Обновление config/services.php

Добавьте секцию:

```php
'openrouter' => [
    'key' => env('OPENROUTER_API_KEY', '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'),
    'api_key' => env('OPENROUTER_API_KEY', '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'),
],
```

### Шаг 7: Права доступа

```bash
chmod +x python_scripts/phishguard_audit.py
```

### Шаг 8: Очистка кеша

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## ✅ Проверка работы

1. Запустите сервер: `php artisan serve`
2. Откройте: `http://localhost:8000/check/link`
3. Введите URL и нажмите "Проверить ссылку"
4. Дождитесь результатов

## 🔍 Структура вызова

```
User → /check/link (POST)
  ↓
CheckController::checkLink()
  ↓
PhishGuardService::fullAudit($url)
  ↓
shell_exec("python3 python_scripts/phishguard_audit.py $url $apiKey")
  ↓
ai_analyzer.py::analyze_url_full_audit()
  ↓
OpenRouter API / Google AI
  ↓
JSON результат → Laravel → View
```

## 📝 Важные моменты

1. **Путь к Python**: В `PhishGuardService.php` проверяется путь к Python. Если у вас другой путь, обновите строку 137-141.

2. **Виртуальное окружение**: Если используете venv, путь должен быть `venv/bin/python3` (автоматически определяется).

3. **API ключи**: Все ключи уже в `.env` файле, но вы можете использовать свои.

4. **Модель AI**: По умолчанию используется `xiaomi/mimo-v2-flash:free`. Можно изменить в `.env`.

## 🐛 Решение проблем

### Ошибка "PhishGuard скрипт не найден"

Проверьте путь в `PhishGuardService.php` (строка 118):
```php
$scriptPath = base_path('python_scripts/phishguard_audit.py');
```

### Ошибка "OPENROUTER_API_KEY не настроен"

Проверьте `.env` и `config/services.php`.

### Python зависимости не установлены

```bash
source venv/bin/activate
pip install requests beautifulsoup4 google-genai dnspython
```

## 📞 Поддержка

Если возникли проблемы при интеграции, проверьте:
1. Логи Laravel: `storage/logs/laravel.log`
2. Вывод Python скрипта (добавьте `Log::debug()` в PhishGuardService)
3. Права доступа на файлы Python

