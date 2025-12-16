# Интеграция PhishGuard в проект

## Что было сделано

1. **Клонирован репозиторий PhishGuard**: `/home/nurjaks/Development/PHISHGUARD-V1`
2. **Создан PHP сервис**: `app/Services/PhishGuardService.php` - обёртка для вызова FastAPI backend
3. **Обновлён контроллер**: `CheckController@checkLink` теперь использует PhishGuard вместо Urlscan
4. **Обновлён view**: `resources/views/check/link.blade.php` отображает результаты PhishGuard
5. **Запущен FastAPI backend**: PhishGuard работает на порту 8002

## Порты и сервисы

- **Laravel приложение**: `http://localhost:8000`
- **PhishGuard FastAPI**: `http://localhost:8002`
- **Страница проверки ссылок**: `http://localhost:8000/check/link`

## Настройка

В `.env` добавлена переменная:
```
PHISHGUARD_API_URL=http://localhost:8002
```

## Запуск

### 1. PhishGuard FastAPI Backend
```bash
cd /home/nurjaks/Development/PHISHGUARD-V1/Agro_Phish/backend
source venv/bin/activate
python -m uvicorn main:app --host 0.0.0.0 --port 8002
```

### 2. Laravel приложение
```bash
cd /home/nurjaks/Development/Gos-osint-project/Project_Gos
php artisan serve --host 0.0.0.0 --port 8000
```

## Функциональность

PhishGuard предоставляет:
- **Базовая проверка URL** (`/v1/check/url`): анализ через правила (blacklist, whitelist, keywords)
- **AI анализ** (`/v1/ai/scan`): LLM-анализ через Google Gemini или OpenRouter
- **Полный аудит** (`/v1/ai/full-audit`): детальный анализ всех аспектов сайта

## Результаты проверки

- **action**: `allow` / `warn` / `block`
- **score**: 0.0 - 1.0 (оценка риска)
- **reason**: текстовое объяснение
- **incident_id**: ID сохранённого инцидента

## Классификация

- **danger** (опасно): `action === 'block'` или `score >= 0.8`
- **warning** (подозрительно): `action === 'warn'` или `score >= 0.4`
- **safe** (безопасно): иначе

