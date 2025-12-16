# Использование только функции проверки ссылок

Этот модуль полностью самодостаточен и может использоваться **только для проверки ссылок** с помощью AI. Все остальные функции проекта не требуются.

## ✅ Что нужно для работы проверки ссылок

### Минимальные файлы:

1. **PHP файлы:**
   - `app/Services/PhishGuardService.php` - сервис для работы с AI
   - `app/Http/Controllers/CheckController.php` - только метод `checkLink()` (строки ~380-530)

2. **Python скрипты:**
   - `python_scripts/phishguard_audit.py` - главный скрипт
   - `python_scripts/ai_analyzer.py` - AI анализ
   - `python_scripts/domain_info.py` - анализ домена
   - `python_scripts/rules.json` - правила проверки
   - `python_scripts/official_sources.json` - база официальных источников

3. **Frontend:**
   - `resources/views/check/link.blade.php` - страница проверки

4. **Конфигурация:**
   - `config/services.php` - секция `openrouter`
   - `.env` - переменные окружения

5. **Маршруты:**
   - `routes/web.php` - только маршруты для `/check/link`

## 🔑 API Ключи (уже настроены)

Все ключи уже в `.env` файле:

```env
OPENROUTER_API_KEY=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
OPENROUTER_MODEL=xiaomi/mimo-v2-flash:free
GOOGLE_API_KEY=AIzaSyBFvQQz_PQdg4jTpwefpfPuiAyV7OMT9aw
```

**Ваш друг может использовать эти же ключи** - они уже настроены и работают.

## 🚀 Быстрый старт (только проверка ссылок)

```bash
# 1. Установка зависимостей
composer install
npm install && npm run build

# 2. Python зависимости
python3 -m venv venv
source venv/bin/activate
pip install -r python_scripts/requirements.txt

# 3. Запуск
php artisan serve --port=8003
```

Откройте: `http://localhost:8003/check/link`

## 📝 Использование в существующем проекте

Если ваш друг хочет добавить только эту функцию в свой проект:

### Вариант 1: Использовать как есть

Просто скопируйте файлы и используйте маршрут `/check/link` - он работает независимо от других функций.

### Вариант 2: Минимальная интеграция

Скопируйте только необходимые файлы (см. `INTEGRATION_GUIDE.md`) и добавьте в свой проект.

## 🔒 Безопасность ключей

- Ключи находятся в `.env` файле
- Они уже настроены и готовы к использованию
- Ваш друг может использовать те же ключи или заменить на свои
- OpenRouter ключ: `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`
- Google ключ (fallback): `AIzaSyBFvQQz_PQdg4jTpwefpfPuiAyV7OMT9aw`

## ⚡ Что НЕ нужно

Для работы проверки ссылок **НЕ требуются**:
- ❌ База данных (используется file-based storage)
- ❌ Другие сервисы (PhoneInfoga, ImageHash и т.д.)
- ❌ Дополнительные порты (все работает через Laravel)
- ❌ Docker или другие контейнеры
- ❌ Внешние API кроме OpenRouter/Google AI

## 🎯 Только проверка ссылок

Этот модуль сфокусирован **только на проверке ссылок**. Все остальные функции проекта (проверка номеров, изображений и т.д.) не влияют на работу проверки ссылок и могут быть проигнорированы.

## 📞 Тестирование

После установки проверьте работу:

```bash
# Через браузер
http://localhost:8003/check/link

# Или через командную строку
php artisan tinker
>>> $service = app(\App\Services\PhishGuardService::class);
>>> $result = $service->fullAudit('https://google.com');
>>> print_r($result);
```

## ✅ Готово к использованию

Все настроено и готово. Ваш друг может:
1. Клонировать проект
2. Установить зависимости
3. Запустить и использовать проверку ссылок
4. Использовать ваши API ключи (они уже в `.env`)

