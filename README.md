# AntiFraud Platform - Link Check Module

Этот проект содержит **модуль проверки ссылок (Link Check)** с интеграцией AI для определения фишинговых и мошеннических сайтов. 

**🎯 Фокус проекта**: Проверка ссылок с помощью AI (OpenRouter + Google AI)

Модуль использует OpenRouter AI (приоритет) и Google AI (fallback) для детального анализа безопасности URL. Все API ключи уже настроены и готовы к использованию.

> 💡 **Важно**: Этот модуль полностью самодостаточен. Вы можете использовать **только функцию проверки ссылок**, игнорируя остальные функции проекта. См. `LINK_CHECK_ONLY.md` и `STANDALONE_USAGE.md` для деталей.

## 🎯 Основная функциональность

**Проверка ссылок (Link Check)** - главная функция проекта:
- ✅ Полный AI-аудит сайтов через OpenRouter (модель `xiaomi/mimo-v2-flash:free`)
- ✅ Автоматический fallback на Google AI при недоступности OpenRouter
- ✅ Анализ URL, TLS сертификатов, DNS информации, возраста домена
- ✅ Проверка HTML/JS структуры, контента и опасного поведения
- ✅ Базовая проверка по blacklist/whitelist правилам
- ✅ Понятные результаты на русском языке простыми словами

## 📋 Требования

- **PHP**: >= 8.2
- **Composer**: последняя версия
- **Node.js**: >= 18.x и npm
- **Python**: >= 3.8
- **Операционная система**: Linux, macOS или WSL на Windows

## 🚀 Установка

### 1. Клонирование репозитория

```bash
git clone <repository-url>
cd Project_Gos
```

### 2. Установка PHP зависимостей

```bash
composer install
```

### 3. Настройка окружения

Файл `.env` уже включен в репозиторий с необходимыми ключами. Если нужно сгенерировать новый APP_KEY:

```bash
php artisan key:generate
```

### 4. Установка Node.js зависимостей

```bash
npm install
npm run build
```

### 5. Установка Python зависимостей

Создайте виртуальное окружение и установите зависимости:

```bash
# Создание виртуального окружения
python3 -m venv venv

# Активация виртуального окружения
source venv/bin/activate  # Linux/macOS
# или
venv\Scripts\activate  # Windows

# Установка зависимостей
pip install -r python_scripts/requirements.txt
```

### 6. Настройка прав доступа

```bash
chmod +x python_scripts/phishguard_audit.py
```

## ▶️ Запуск проекта

### Запуск Laravel сервера

```bash
php artisan serve --host=0.0.0.0 --port=8003
```

Или используйте встроенный скрипт:

```bash
composer run dev
```

### Доступ к приложению

Откройте в браузере: `http://localhost:8003`

### Проверка ссылок

1. Перейдите на страницу: `http://localhost:8003/check/link`
2. Введите URL или домен для проверки
3. Нажмите "Проверить ссылку"
4. Дождитесь результатов анализа

## 🔧 Как это работает

### Архитектура

```
Laravel Frontend (PHP)
    ↓
CheckController::checkLink()
    ↓
PhishGuardService::fullAudit()
    ↓
Python Script: python_scripts/phishguard_audit.py
    ↓
ai_analyzer.py::analyze_url_full_audit()
    ↓
OpenRouter AI API (Primary) / Google AI (Fallback)
```

### Процесс проверки

1. **Сбор данных**:
   - Загрузка HTML страницы
   - Извлечение JavaScript URL
   - Анализ домена (DNS, TLS, возраст)

2. **AI-анализ**:
   - Отправка данных в OpenRouter AI (приоритет)
   - Если OpenRouter недоступен → автоматический переход на Google AI
   - Анализ всех аспектов безопасности

3. **Классификация**:
   - Безопасно / Подозрительно / Опасно
   - Вероятность фишинга (0-100%)
   - Детальное объяснение простыми словами

4. **Результат**:
   - Отображение в удобном формате
   - Детальный анализ по категориям
   - Рекомендации по безопасности

## 📁 Структура проекта (важные файлы)

```
Project_Gos/
├── app/
│   ├── Http/Controllers/
│   │   └── CheckController.php          # Контроллер проверки ссылок
│   └── Services/
│       └── PhishGuardService.php        # Сервис для работы с PhishGuard
├── python_scripts/
│   ├── phishguard_audit.py              # Главный скрипт для вызова из PHP
│   ├── ai_analyzer.py                   # AI анализ через OpenRouter/Google
│   ├── domain_info.py                   # Анализ домена (DNS, TLS, возраст)
│   ├── rules.json                       # Правила blacklist/whitelist
│   └── official_sources.json            # База официальных источников
├── resources/views/check/
│   └── link.blade.php                   # Frontend страница проверки ссылок
├── routes/
│   └── web.php                          # Маршруты (GET/POST /check/link)
├── config/
│   └── services.php                     # Конфигурация API ключей
├── .env                                  # Переменные окружения (включен в репозиторий)
└── requirements.txt                     # Python зависимости
```

## 🔑 API Ключи

Все ключи уже настроены в `.env` файле:

- **OPENROUTER_API_KEY**: `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`
- **OPENROUTER_MODEL**: `xiaomi/mimo-v2-flash:free` (бесплатная модель)
- **GOOGLE_API_KEY**: `AIzaSyBFvQQz_PQdg4jTpwefpfPuiAyV7OMT9aw` (fallback)

## 🔌 Интеграция в существующий проект

Если вы хотите интегрировать этот модуль проверки ссылок в свой существующий Laravel проект:

### 1. Скопируйте файлы

```bash
# Сервис
cp app/Services/PhishGuardService.php <your-project>/app/Services/

# Контроллер (или добавьте метод checkLink в существующий)
cp app/Http/Controllers/CheckController.php <your-project>/app/Http/Controllers/

# Python скрипты
cp -r python_scripts/ <your-project>/

# View
cp resources/views/check/link.blade.php <your-project>/resources/views/check/

# Конфигурация
# Добавьте в config/services.php секцию 'openrouter'
```

### 2. Добавьте маршруты

В `routes/web.php`:

```php
Route::get('/check/link', [CheckController::class, 'link'])->name('check.link');
Route::post('/check/link', [CheckController::class, 'checkLink'])->name('check.link.perform');
```

### 3. Установите Python зависимости

```bash
pip install -r python_scripts/requirements.txt
```

### 4. Настройте .env

Добавьте в ваш `.env`:

```env
OPENROUTER_API_KEY=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
OPENROUTER_MODEL=xiaomi/mimo-v2-flash:free
GOOGLE_API_KEY=AIzaSyBFvQQz_PQdg4jTpwefpfPuiAyV7OMT9aw
```

### 5. Обновите config/services.php

```php
'openrouter' => [
    'key' => env('OPENROUTER_API_KEY', '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'),
    'api_key' => env('OPENROUTER_API_KEY', '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'),
],
```

## 🧪 Тестирование

### Тест через командную строку

```bash
# Активация виртуального окружения
source venv/bin/activate

# Прямой вызов Python скрипта
python3 python_scripts/phishguard_audit.py "https://google.com" "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"
```

### Тест через Laravel Tinker

```bash
php artisan tinker

$service = app(\App\Services\PhishGuardService::class);
$result = $service->fullAudit('https://google.com');
print_r($result);
```

## 📝 Конфигурация

### Изменение модели AI

В `.env` измените:

```env
OPENROUTER_MODEL=xiaomi/mimo-v2-flash:free
```

Доступные бесплатные модели можно посмотреть на: https://openrouter.ai/models

### Изменение порта

В `.env`:

```env
APP_PORT=8003
```

## 🐛 Решение проблем

### Ошибка "OPENROUTER_API_KEY не настроен"

Проверьте, что в `.env` файле есть ключ `OPENROUTER_API_KEY`.

### Ошибка "PhishGuard скрипт не найден"

Убедитесь, что файл `python_scripts/phishguard_audit.py` существует и имеет права на выполнение:
```bash
chmod +x python_scripts/phishguard_audit.py
```

### Ошибка "ModuleNotFoundError: No module named 'requests'"

Установите Python зависимости:
```bash
source venv/bin/activate
pip install -r python_scripts/requirements.txt
```

### Python скрипт не выполняется

Проверьте путь к Python в `PhishGuardService.php`. По умолчанию используется `python3` или `venv/bin/python3`.

## 📚 Дополнительная информация

### Используемые технологии

- **Backend**: Laravel 12.x (PHP 8.2+)
- **Frontend**: Blade templates, TailwindCSS
- **AI**: OpenRouter API (приоритет), Google Gemini (fallback)
- **Python**: requests, beautifulsoup4, google-genai, dnspython

### Лимиты API

- **OpenRouter**: Бесплатная модель `xiaomi/mimo-v2-flash:free` имеет лимиты на количество запросов
- **Google AI**: 20 запросов/день для бесплатного тарифа

При исчерпании лимитов система автоматически переключается на fallback или показывает понятное сообщение об ошибке.

## 📄 Лицензия

MIT License

## 👥 Авторы

Этот модуль проверки ссылок был разработан для интеграции в основной проект AntiFraud Platform.

---

**Важно**: Этот проект сфокусирован на функции проверки ссылок. Другие функции (проверка номеров, изображений и т.д.) могут быть в основном проекте.
