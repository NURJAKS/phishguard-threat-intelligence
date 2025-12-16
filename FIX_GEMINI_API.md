# 🔧 Исправление проблемы с Gemini API

## Проблема
Groq не работает автоматически, и Gemini API превышает лимит запросов.

## Решение: Новый Gemini API ключ

Настроен новый API ключ от Google AI Studio Gemini: `AIzaSyDkJsG9v0fJUtK60Wm_mvPSDNGyJp3EpXU`

## ⚡ Быстрый запуск (рекомендуется)

### Вариант 1: Используйте готовый скрипт

```bash
cd /home/nurjaks/Development/Gos-osint-project/Project_Gos
./start_phishguard_with_new_gemini.sh
```

Этот скрипт:
- ✅ Устанавливает новый Gemini API ключ
- ✅ Устанавливает Groq как fallback
- ✅ Запускает PhishGuard backend

### Вариант 2: Вручную

```bash
cd /home/nurjaks/Development/PhishGuard/05.12.25_PhishGuard/Agro_Phish/backend

# Установите новый Gemini ключ
export GOOGLE_API_KEY="AIzaSyDkJsG9v0fJUtK60Wm_mvPSDNGyJp3EpXU"

# Также установите Groq как fallback (на случай если Gemini снова превысит лимит)
export GROQ_API_KEY="gsk_YOUR_GROQ_API_KEY_HERE"
export GROQ_MODEL="llama-3.1-70b-versatile"

# Запустите backend
source venv/bin/activate
python -m uvicorn main:app --host 0.0.0.0 --port 8002
```

## 🔄 Постоянная настройка

Чтобы не устанавливать переменные каждый раз, создайте `.env` файл:

```bash
cd /home/nurjaks/Development/PhishGuard/05.12.25_PhishGuard/Agro_Phish/backend
nano .env  # или используйте любой редактор
```

Добавьте:
```env
# Новый Gemini API ключ
GOOGLE_API_KEY=AIzaSyDkJsG9v0fJUtK60Wm_mvPSDNGyJp3EpXU

# Groq как fallback
GROQ_API_KEY=gsk_YOUR_GROQ_API_KEY_HERE
GROQ_MODEL=llama-3.1-70b-versatile
```

## ✅ Проверка работы

1. Запустите PhishGuard backend (см. выше)
2. Откройте: `http://localhost:8003/check/link`
3. Проверьте ссылку (например: `http://baikonurmed.kz/`)
4. Детальный анализ должен работать! ✅

В логах PhishGuard вы должны увидеть:
```
[AI API] Using Google AI Studio (Gemini 2.5 Flash)
[AI API] API Key source: ENV
[FULL AUDIT] Audit completed: verdict=Опасно, probability=92%
```

## 🔄 Как это работает

1. **Gemini** используется по умолчанию (новый ключ)
2. Если Gemini недоступен → **Groq** автоматически берет на себя
3. Если Groq недоступен → **OpenRouter** (если настроен)

## 🐛 Если все еще не работает

### Проверка 1: Убедитесь, что backend запущен

```bash
# Проверьте, запущен ли PhishGuard backend
curl http://localhost:8002/health
```

Должен вернуть статус 200.

### Проверка 2: Проверьте переменные окружения

В терминале где запущен backend:
```bash
echo $GOOGLE_API_KEY
echo $GROQ_API_KEY
```

Оба должны быть установлены.

### Проверка 3: Проверьте логи

В логах PhishGuard backend ищите:
- `[AI API] Using Google AI Studio` - Gemini используется
- `[AI API] Using Groq` - Groq используется (fallback)
- Ошибки с API ключами

### Проверка 4: Перезапустите backend

1. Остановите текущий процесс (Ctrl+C)
2. Запустите заново с новыми переменными
3. Проверьте работу

## 📝 Примечания

- Новый Gemini ключ имеет свой лимит (20 запросов/мин на бесплатном тарифе)
- Groq настроен как fallback (1000 запросов/день)
- Если оба ключа исчерпаны, система попытается использовать OpenRouter (если настроен)

## 🎯 Готово!

Теперь PhishGuard использует новый Gemini ключ, и Groq работает как fallback. Детальный анализ должен работать!
