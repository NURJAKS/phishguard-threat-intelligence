# Настройка альтернативных AI провайдеров в PhishGuard

PhishGuard backend уже поддерживает **OpenRouter** как альтернативу Gemini API. Вот как настроить различные провайдеры.

## Текущая поддержка

PhishGuard backend (`ai_analyzer.py`) поддерживает:
1. **Google Gemini** (по умолчанию) - через `GOOGLE_API_KEY`
2. **OpenRouter** (fallback) - через `OPENROUTER_API_KEY` и `OPENROUTER_MODEL`

Система автоматически использует OpenRouter если Gemini недоступен.

## Быстрая настройка OpenRouter

### Шаг 1: Получите API ключ OpenRouter

1. Зарегистрируйтесь на https://openrouter.ai
2. Перейдите в Settings → Keys
3. Создайте новый API ключ
4. **Важно:** Пополните баланс минимум $10 для увеличения лимита до 1000 запросов/день

### Шаг 2: Выберите модель

Рекомендуемые модели через OpenRouter:

| Модель | Скорость | Качество | Цена за 1M токенов |
|--------|----------|----------|-------------------|
| `openai/gpt-4o-mini` | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | $0.15 |
| `anthropic/claude-3-haiku-20240307` | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | $0.25 |
| `google/gemini-2.0-flash-exp` | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | $0.075 |
| `mistralai/mistral-small-latest` | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | $0.20 |
| `meta-llama/llama-3.1-70b-instruct` | ⭐⭐⭐ | ⭐⭐⭐⭐ | $0.59 |

### Шаг 3: Настройте переменные окружения

В директории PhishGuard backend (`/home/nurjaks/Development/PhishGuard/05.12.25_PhishGuard/Agro_Phish/backend/`):

**Linux/Mac:**
```bash
export OPENROUTER_API_KEY="sk-or-v1-ваш-ключ-здесь"
export OPENROUTER_MODEL="openai/gpt-4o-mini"  # или другая модель
```

**Windows (PowerShell):**
```powershell
$env:OPENROUTER_API_KEY="sk-or-v1-ваш-ключ-здесь"
$env:OPENROUTER_MODEL="openai/gpt-4o-mini"
```

**Или создайте файл `.env` в директории backend:**
```env
OPENROUTER_API_KEY=sk-or-v1-ваш-ключ-здесь
OPENROUTER_MODEL=openai/gpt-4o-mini
```

### Шаг 4: Перезапустите PhishGuard backend

```bash
cd /home/nurjaks/Development/PhishGuard/05.12.25_PhishGuard/Agro_Phish/backend
source venv/bin/activate  # или venv\Scripts\activate на Windows
python -m uvicorn main:app --host 0.0.0.0 --port 8002
```

## Добавление поддержки других провайдеров

Если хотите добавить поддержку Groq, Mistral или других провайдеров напрямую, можно модифицировать `ai_analyzer.py`.

### Пример: Добавление Groq

Добавьте функцию в `ai_analyzer.py`:

```python
def _groq_analyze(prompt: str) -> Optional[str]:
    """Try Groq API"""
    try:
        api_key = os.getenv("GROQ_API_KEY")
        if not api_key:
            logger.warning("[AI API] GROQ_API_KEY not set, skipping Groq")
            return None
        
        model = os.getenv("GROQ_MODEL", "llama-3.1-70b-versatile")
        logger.info(f"[AI API] Using Groq (model: {model})")
        
        url = "https://api.groq.com/openai/v1/chat/completions"
        headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        }
        
        messages = [
            {
                "role": "system",
                "content": "Ты - помощник по безопасности в интернете..."
            },
            {
                "role": "user",
                "content": prompt
            }
        ]
        
        payload = {
            "model": model,
            "messages": messages,
            "response_format": {"type": "json_object"}
        }
        
        resp = requests.post(url, headers=headers, json=payload, timeout=30)
        resp.raise_for_status()
        data = resp.json()
        content = data.get("choices", [{}])[0].get("message", {}).get("content", "{}")
        return content
    except Exception as e:
        logger.error(f"[AI API] Groq error: {e}")
        return None
```

Затем обновите функцию `analyze_urls_with_llm` для использования Groq:

```python
def analyze_urls_with_llm(urls: List[str]) -> Dict[str, Any]:
    # ... существующий код ...
    
    # Попробуйте Groq если Gemini и OpenRouter не работают
    if not result:
        logger.warning(f"[SCAN STEP 3] OpenRouter failed, trying Groq...")
        result = _groq_analyze(prompt)
        provider_used = "groq"
    
    # ... остальной код ...
```

## Приоритет использования провайдеров

Текущий порядок fallback в PhishGuard:
1. **Google Gemini** (если `GOOGLE_API_KEY` установлен)
2. **OpenRouter** (если Gemini недоступен и `OPENROUTER_API_KEY` установлен)
3. **Ошибка** (если оба недоступны)

## Проверка работы

После настройки проверьте логи PhishGuard backend:

```bash
# В логах вы должны увидеть:
[AI API] Using OpenRouter (model: openai/gpt-4o-mini)
[AI API] OpenRouter response received: HTTP 200
```

Или проверьте через API:

```bash
curl -X POST http://localhost:8002/v1/ai/full-audit \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com"}'
```

## Рекомендации по моделям для разных задач

### Для быстрого анализа (много запросов):
- `openai/gpt-4o-mini` - быстрая и дешевая
- `google/gemini-2.0-flash-exp` - очень быстрая
- `mistralai/mistral-small-latest` - хороший баланс

### Для качественного анализа:
- `anthropic/claude-3-5-sonnet-20241022` - лучшая модель
- `openai/gpt-4o` - очень качественная
- `anthropic/claude-3-opus-20240229` - мощная модель

### Для экономии:
- `openai/gpt-4o-mini` - самая дешевая качественная модель
- `google/gemini-2.0-flash-exp` - дешевая и быстрая
- `mistralai/mistral-small-latest` - хорошая цена

## Мониторинг использования

OpenRouter предоставляет dashboard для мониторинга:
- https://openrouter.ai/activity - статистика использования
- https://openrouter.ai/credits - баланс и расходы

## Troubleshooting

### Ошибка: "OPENROUTER_API_KEY not set"
- Убедитесь, что переменная окружения установлена
- Проверьте, что вы перезапустили backend после установки переменной

### Ошибка: "Rate limit exceeded"
- Проверьте баланс на OpenRouter
- Убедитесь, что пополнили баланс минимум $10 для увеличения лимита

### Ошибка: "Model not found"
- Проверьте правильность названия модели
- Убедитесь, что модель доступна через OpenRouter (см. https://openrouter.ai/models)

### Gemini все еще используется вместо OpenRouter
- Убедитесь, что `GOOGLE_API_KEY` не установлен или установлен неверный ключ
- Проверьте логи - система должна автоматически переключиться на OpenRouter при ошибке Gemini

## Дополнительные ресурсы

- [OpenRouter Documentation](https://openrouter.ai/docs)
- [OpenRouter Models](https://openrouter.ai/models)
- [OpenRouter Pricing](https://openrouter.ai/docs/pricing)


