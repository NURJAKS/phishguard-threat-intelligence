# Альтернативы Gemini API для PhishGuard

## Проблема с Gemini API

Gemini API имеет ограничения на бесплатном тарифе:
- **20 запросов в минуту** (free tier)
- При превышении лимита возвращается ошибка 429 RESOURCE_EXHAUSTED

## Рекомендуемые альтернативы

### 1. **OpenRouter** ⭐ (Рекомендуется)

**Преимущества:**
- Единый API для множества моделей (GPT-4, Claude, Llama, Mistral и др.)
- Гибкая система тарифов
- Бесплатный tier: 50 запросов/день
- После покупки $10 кредитов: **1000 запросов/день**
- Поддержка множества моделей через один API

**Модели через OpenRouter:**
- `openai/gpt-4o-mini` - быстрая и дешевая
- `anthropic/claude-3-haiku` - качественный анализ
- `google/gemini-2.0-flash-exp` - альтернатива Gemini
- `mistralai/mistral-large` - мощная модель
- `meta-llama/llama-3.1-70b-instruct` - открытая модель

**Настройка:**
```bash
# В PhishGuard backend .env
OPENROUTER_API_KEY=sk-or-v1-...
AI_PROVIDER=openrouter
AI_MODEL=openai/gpt-4o-mini  # или другая модель
```

**Стоимость:** От $0.15 за 1M токенов (зависит от модели)

---

### 2. **Groq** 🚀 (Очень быстрый)

**Преимущества:**
- **1000 запросов в день** бесплатно
- Очень быстрая генерация (до 300 токенов/сек)
- Поддержка Llama, Mixtral моделей
- Простой API

**Модели:**
- `llama-3.1-70b-versatile` - мощная модель
- `mixtral-8x7b-32768` - больший контекст
- `llama-3.1-8b-instant` - быстрая модель

**Настройка:**
```bash
# В PhishGuard backend .env
GROQ_API_KEY=gsk_YOUR_GROQ_API_KEY_HERE
AI_PROVIDER=groq
AI_MODEL=llama-3.1-70b-versatile
```

**Стоимость:** Бесплатно до 1000 запросов/день, затем платно

---

### 3. **Mistral AI** 🎯

**Преимущества:**
- Щедрый бесплатный tier
- Высокое качество моделей
- Хорошая поддержка русского языка
- До 1 запроса/сек на бесплатном tier

**Модели:**
- `mistral-large-latest` - самая мощная
- `mistral-medium-latest` - баланс качества/скорости
- `mistral-small-latest` - быстрая модель

**Настройка:**
```bash
# В PhishGuard backend .env
MISTRAL_API_KEY=...
AI_PROVIDER=mistral
AI_MODEL=mistral-medium-latest
```

**Стоимость:** Бесплатный tier с ограничениями, затем от €0.20 за 1M токенов

---

### 4. **Anthropic Claude** (через OpenRouter или напрямую)

**Преимущества:**
- Очень качественный анализ
- Хорошее понимание контекста
- Поддержка больших контекстов (до 200K токенов)

**Модели:**
- `claude-3-5-sonnet-20241022` - лучшая модель
- `claude-3-opus-20240229` - мощная
- `claude-3-haiku-20240307` - быстрая и дешевая

**Настройка через OpenRouter:**
```bash
OPENROUTER_API_KEY=sk-or-v1-...
AI_PROVIDER=openrouter
AI_MODEL=anthropic/claude-3-haiku-20240307
```

**Стоимость:** От $0.25 за 1M токенов (через OpenRouter)

---

### 5. **Together AI** 🔥

**Преимущества:**
- Множество открытых моделей
- Хорошие бесплатные лимиты
- Поддержка Llama, Mixtral, Qwen и др.

**Модели:**
- `meta-llama/Llama-3-70b-chat-hf`
- `mistralai/Mixtral-8x7B-Instruct-v0.1`
- `Qwen/Qwen2.5-72B-Instruct`

**Настройка:**
```bash
TOGETHER_API_KEY=...
AI_PROVIDER=together
AI_MODEL=meta-llama/Llama-3-70b-chat-hf
```

**Стоимость:** От $0.0002 за 1K токенов

---

### 6. **DeepInfra** 💰

**Преимущества:**
- Дешевые цены
- Поддержка множества моделей
- Хорошая производительность

**Модели:**
- `meta-llama/Llama-3-70b-chat-hf`
- `mistralai/Mixtral-8x7B-Instruct-v0.1`
- `google/gemma-7b-it`

**Настройка:**
```bash
DEEPINFRA_API_KEY=...
AI_PROVIDER=deepinfra
AI_MODEL=meta-llama/Llama-3-70b-chat-hf
```

**Стоимость:** От $0.0001 за 1K токенов

---

## Сравнительная таблица

| Провайдер | Бесплатный tier | Платные цены | Скорость | Качество |
|-----------|----------------|--------------|----------|----------|
| **OpenRouter** | 50 req/день → 1000 после $10 | От $0.15/1M | Средняя | ⭐⭐⭐⭐⭐ |
| **Groq** | 1000 req/день | Платно после | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Mistral** | Щедрый tier | От €0.20/1M | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Together AI** | Ограниченный | От $0.0002/1K | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **DeepInfra** | Нет | От $0.0001/1K | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Gemini** | 20 req/мин | От $0.075/1M | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |

## Рекомендации для PhishGuard

### Для разработки/тестирования:
1. **Groq** - 1000 запросов/день бесплатно, очень быстро
2. **OpenRouter** - после $10 кредитов = 1000 req/день, много моделей

### Для продакшена:
1. **OpenRouter** - гибкость, много моделей, хорошие цены
2. **Mistral** - качество и цена
3. **Together AI** - дешево для больших объемов

## Как настроить в PhishGuard Backend

### Вариант 1: Через OpenRouter (рекомендуется)

1. Зарегистрируйтесь на https://openrouter.ai
2. Получите API ключ
3. Пополните баланс минимум $10 для увеличения лимита до 1000 req/день

```bash
# В PhishGuard backend .env
OPENROUTER_API_KEY=sk-or-v1-ваш-ключ
AI_PROVIDER=openrouter
AI_MODEL=openai/gpt-4o-mini  # или anthropic/claude-3-haiku
```

### Вариант 2: Через Groq (для тестирования)

1. Зарегистрируйтесь на https://console.groq.com
2. Получите API ключ бесплатно

```bash
# В PhishGuard backend .env
GROQ_API_KEY=ваш-ключ
AI_PROVIDER=groq
AI_MODEL=llama-3.1-70b-versatile
```

### Вариант 3: Через Mistral

1. Зарегистрируйтесь на https://console.mistral.ai
2. Получите API ключ

```bash
# В PhishGuard backend .env
MISTRAL_API_KEY=ваш-ключ
AI_PROVIDER=mistral
AI_MODEL=mistral-medium-latest
```

## Проверка поддержки в PhishGuard Backend

Проверьте файлы конфигурации PhishGuard backend:
- `config.py` или `.env` - настройки AI провайдеров
- `services/ai_service.py` или аналогичный - код работы с AI

Если поддержки нет, можно добавить через:
1. Обновление кода PhishGuard backend
2. Использование универсального HTTP клиента для вызова API

## Примеры использования

### OpenRouter API (совместим с OpenAI форматом)
```python
import requests

response = requests.post(
    "https://openrouter.ai/api/v1/chat/completions",
    headers={
        "Authorization": f"Bearer {OPENROUTER_API_KEY}",
        "HTTP-Referer": "https://your-app.com",
        "X-Title": "PhishGuard"
    },
    json={
        "model": "openai/gpt-4o-mini",
        "messages": [
            {"role": "user", "content": "Проанализируй этот URL на фишинг: ..."}
        ]
    }
)
```

### Groq API
```python
import requests

response = requests.post(
    "https://api.groq.com/openai/v1/chat/completions",
    headers={
        "Authorization": f"Bearer {GROQ_API_KEY}"
    },
    json={
        "model": "llama-3.1-70b-versatile",
        "messages": [
            {"role": "user", "content": "Проанализируй этот URL на фишинг: ..."}
        ]
    }
)
```

## Полезные ссылки

- **OpenRouter**: https://openrouter.ai/docs
- **Groq**: https://console.groq.com/docs
- **Mistral**: https://docs.mistral.ai
- **Together AI**: https://docs.together.ai
- **DeepInfra**: https://deepinfra.com/docs

## Примечания

- Все цены указаны приблизительно и могут изменяться
- Проверяйте актуальные лимиты на сайтах провайдеров
- Для продакшена рекомендуется использовать несколько провайдеров с fallback
- OpenRouter удобен тем, что позволяет легко переключаться между моделями


