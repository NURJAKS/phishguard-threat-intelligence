# Быстрая установка

## Минимальные шаги для запуска

```bash
# 1. Клонирование
git clone <repository-url>
cd Project_Gos

# 2. PHP зависимости
composer install

# 3. Генерация ключа (если нужно)
php artisan key:generate

# 4. Node.js зависимости
npm install
npm run build

# 5. Python зависимости
python3 -m venv venv
source venv/bin/activate
pip install -r python_scripts/requirements.txt

# 6. Права доступа
chmod +x python_scripts/phishguard_audit.py

# 7. Запуск
php artisan serve --host=0.0.0.0 --port=8003
```

Откройте: `http://localhost:8003/check/link`

## API Ключи (уже в .env)

- **OPENROUTER_API_KEY**: `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`
- **OPENROUTER_MODEL**: `xiaomi/mimo-v2-flash:free`
- **GOOGLE_API_KEY**: `AIzaSyBFvQQz_PQdg4jTpwefpfPuiAyV7OMT9aw`

## Проверка работы

1. Откройте `http://localhost:8003/check/link`
2. Введите URL (например: `https://google.com`)
3. Нажмите "Проверить ссылку"
4. Дождитесь результатов

## Если что-то не работает

```bash
# Очистка кеша
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Проверка Python
source venv/bin/activate
python3 python_scripts/phishguard_audit.py "https://google.com" "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"
```

