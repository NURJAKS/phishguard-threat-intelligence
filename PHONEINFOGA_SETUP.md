# Настройка PhoneInfoga

## Вариант 1: Запуск через Docker (Рекомендуется)

```bash
# Запустить PhoneInfoga API сервер
docker run -d -p 5000:5000 --name phoneinfoga sundowndev/phoneinfoga serve --no-client

# Проверить что сервер работает
curl http://localhost:5000/api/
```

Затем в `.env` файле Laravel добавьте:
```
PHONEINFOGA_API_URL=http://localhost:5000/api
```

## Вариант 2: Сборка из исходников

```bash
cd /home/nurbol/HACKATON_TURKISTAN/phoneinfoga

# Собрать бинарник (требуется Go)
go build -o phoneinfoga main.go

# Запустить API сервер
./phoneinfoga serve --no-client --port 5000
```

## Вариант 3: Использование Docker CLI (Fallback)

Если API сервер не запущен, система автоматически использует Docker CLI:
```bash
docker run --rm phoneinfoga scan -n +77771234567
```

Этот вариант работает, но менее эффективен, так как требует парсинга текстового вывода.

