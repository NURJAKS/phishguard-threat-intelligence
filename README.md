# 🔒 Link Scanner - Сканер ссылок на безопасность

**Link Scanner** — это минималистичный веб-инструмент для проверки безопасности URL с использованием искусственного интеллекта. Система анализирует ссылки на предмет фишинга, мошенничества и других угроз безопасности.

## 🎯 Что это такое?

**Link Scanner** помогает определить, безопасна ли ссылка перед переходом по ней. Это особенно полезно для:

- **Защиты от фишинга** — обнаружение поддельных сайтов, имитирующих банки, соцсети и другие сервисы
- **Проверки подозрительных ссылок** — анализ URL из email, сообщений, объявлений
- **Оценки репутации домена** — проверка возраста домена, SSL сертификатов, DNS записей
- **Анализа содержимого** — проверка HTML/JS кода на наличие опасных паттернов

## ✨ Основные возможности

- 🤖 **AI-анализ** — использование OpenRouter AI (приоритет) и Google AI (fallback) для детального анализа
- 🔍 **Многоуровневая проверка**:
  - Анализ URL структуры (подозрительные символы, маскировка)
  - Проверка TLS/SSL сертификатов
  - Анализ DNS записей и возраста домена
  - Проверка HTML/JavaScript содержимого
  - Сравнение с базами официальных источников
- 📊 **Понятные результаты** — вердикт простыми словами на русском языке
- ⚡ **Автоматический fallback** — при недоступности одного AI провайдера автоматически переключается на другой
- 🛡️ **Базовая защита** — проверка по blacklist/whitelist правилам

## 🏗️ Как это работает?

### Архитектура системы

```
┌─────────────────┐
│  Пользователь   │
│  (Браузер)      │
└────────┬────────┘
         │ HTTP Request
         ▼
┌─────────────────┐
│  Laravel App    │
│  (PHP)          │
│  - Routes       │
│  - Controller   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ PhishGuardService│
│  (PHP Service)  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Python Scripts  │
│ - phishguard_   │
│   audit.py      │
│ - ai_analyzer.py│
│ - domain_info.py│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  AI Providers    │
│  - OpenRouter    │
│  - Google AI     │
└─────────────────┘
```

### Процесс проверки ссылки

1. **Ввод URL** — пользователь вводит ссылку в форму на веб-странице

2. **Базовая проверка** (мгновенно):
   - Проверка по whitelist (доверенные домены)
   - Проверка по blacklist (известные опасные домены)
   - Анализ структуры URL (подозрительные паттерны)

3. **Сбор данных** (если не в whitelist):
   - Загрузка HTML содержимого страницы
   - Извлечение JavaScript файлов
   - Получение DNS информации
   - Проверка TLS/SSL сертификата
   - Определение возраста домена

4. **AI-анализ**:
   - Отправка собранных данных в OpenRouter AI
   - Если OpenRouter недоступен → автоматический переход на Google AI
   - AI анализирует все аспекты и выдает вердикт

5. **Классификация**:
   - **Безопасно** (зеленый) — сайт безопасен для посещения
   - **Подозрительно** (желтый) — есть некоторые признаки риска
   - **Опасно** (красный) — высокий риск фишинга/мошенничества

6. **Отображение результатов**:
   - Вердикт с цветовой индикацией
   - Вероятность фишинга (0-100%)
   - Детальное объяснение простыми словами
   - Разбивка по категориям анализа

## 📋 Требования

- **PHP**: >= 8.2
- **Composer**: последняя версия
- **Node.js**: >= 18.x и npm
- **Python**: >= 3.8
- **Операционная система**: Linux, macOS или WSL на Windows

## 🚀 Установка

### 1. Клонирование репозитория

```bash
git clone https://github.com/NURJAKS/Link-Scanner.git
cd Link-Scanner
```

### 2. Установка PHP зависимостей

```bash
composer install
```

### 3. Настройка окружения

Скопируйте файл `.env.example` в `.env`:

```bash
cp .env.example .env
```

Сгенерируйте APP_KEY:

```bash
php artisan key:generate
```

**Важно**: Откройте файл `.env` и добавьте ваши API ключи:

```env
# OpenRouter AI API Key (получите на https://openrouter.ai/keys)
OPENROUTER_API_KEY=ваш_ключ_openrouter

# Google AI API Key (fallback, получите на https://aistudio.google.com/apikey)
GOOGLE_API_KEY=ваш_ключ_google_ai
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

### 7. Создание базы данных (опционально)

```bash
touch database/database.sqlite
php artisan migrate
```

## ▶️ Запуск проекта

### Запуск Laravel сервера

```bash
php artisan serve --host=0.0.0.0 --port=8003
```

### Доступ к приложению

Откройте в браузере: **http://localhost:8003**

### Использование

1. Перейдите на страницу: `http://localhost:8003/check/link`
2. Введите URL или домен для проверки (например: `https://example.com`)
3. Нажмите "Проверить ссылку"
4. Дождитесь результатов анализа (обычно 5-15 секунд)

## 🔑 Получение API ключей

### OpenRouter AI (рекомендуется)

1. Зарегистрируйтесь на [https://openrouter.ai](https://openrouter.ai)
2. Перейдите в [Keys](https://openrouter.ai/keys)
3. Создайте новый API ключ
4. Скопируйте ключ в `.env` файл

**Бесплатная модель**: `xiaomi/mimo-v2-flash:free` (уже настроена по умолчанию)

### Google AI (fallback)

1. Перейдите на [Google AI Studio](https://aistudio.google.com/apikey)
2. Создайте новый API ключ
3. Скопируйте ключ в `.env` файл

**Лимит**: 20 запросов/день для бесплатного тарифа

## 📁 Структура проекта

```
Link-Scanner/
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
│   ├── official_sources.json            # База официальных источников
│   └── requirements.txt                 # Python зависимости
├── resources/views/
│   ├── check/
│   │   └── link.blade.php               # Frontend страница проверки ссылок
│   └── layouts/
│       └── app.blade.php                # Основной layout
├── routes/
│   └── web.php                          # Маршруты (GET/POST /check/link)
├── config/
│   └── services.php                     # Конфигурация API ключей
└── .env.example                         # Пример файла окружения
```

## 🔧 Технические детали

### Используемые технологии

- **Backend**: Laravel 12.x (PHP 8.2+)
- **Frontend**: Blade templates, CSS
- **AI**: OpenRouter API (приоритет), Google Gemini (fallback)
- **Python**: requests, beautifulsoup4, google-genai, dnspython

### Автоматический Fallback

Система автоматически переключается между AI провайдерами:

1. **OpenRouter** (приоритет) — если доступен
2. **Google AI** (fallback) — если OpenRouter недоступен (401, 503, 429)
3. **Базовая проверка** (blacklist/whitelist) — если оба AI недоступны

### Лимиты API

- **OpenRouter**: Бесплатная модель имеет лимиты на количество запросов
- **Google AI**: 20 запросов/день для бесплатного тарифа

При исчерпании лимитов система автоматически переключается на fallback или показывает понятное сообщение об ошибке.

## 🧪 Тестирование

### Тест через командную строку

```bash
# Активация виртуального окружения
source venv/bin/activate

# Прямой вызов Python скрипта
python3 python_scripts/phishguard_audit.py "https://google.com" "ваш_openrouter_ключ" "ваш_google_ключ"
```

### Тест через Laravel Tinker

```bash
php artisan tinker

$service = app(\App\Services\PhishGuardService::class);
$result = $service->fullAudit('https://google.com');
print_r($result);
```

## 🐛 Решение проблем

### Ошибка "401 Unauthorized" от OpenRouter

Если OpenRouter ключ недействителен, система автоматически переключится на Google AI. Если хотите использовать новый OpenRouter ключ:

1. Получите новый ключ на https://openrouter.ai/keys
2. Обновите в `.env`:
   ```env
   OPENROUTER_API_KEY=ваш_новый_ключ
   ```

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

## 📝 Конфигурация

### Изменение модели AI

В `.env` измените:

```env
OPENROUTER_MODEL=xiaomi/mimo-v2-flash:free
```

Доступные бесплатные модели можно посмотреть на: [https://openrouter.ai/models](https://openrouter.ai/models)

### Изменение порта

В `.env`:

```env
APP_PORT=8003
```

## 🔌 Интеграция в существующий проект

Если вы хотите интегрировать этот модуль проверки ссылок в свой существующий Laravel проект, см. раздел "Интеграция" в документации или скопируйте необходимые файлы:

- `app/Services/PhishGuardService.php`
- `app/Http/Controllers/CheckController.php` (методы `link()` и `checkLink()`)
- `python_scripts/` (вся папка)
- `resources/views/check/link.blade.php`
- `resources/views/layouts/app.blade.php`

## 📄 Лицензия

MIT License

## 👥 Авторы

Этот проект разработан для защиты пользователей от фишинга и мошеннических сайтов.

---

**Link Scanner** — простой и эффективный способ проверить безопасность любой ссылки перед переходом по ней.
