# Установка зависимостей для ML анализа объявлений

## Требования

Для работы системы проверки объявлений необходимо установить следующие Python библиотеки:

1. **scikit-learn** - для машинного обучения и анализа
2. **scrapy** - для парсинга веб-страниц
3. **beautifulsoup4** - для fallback парсинга (если Scrapy недоступен)
4. **requests** - для HTTP запросов

## Установка

### Вариант 1: Через pip (рекомендуется)

```bash
pip install scikit-learn scrapy beautifulsoup4 requests numpy scipy joblib threadpoolctl
```

### Вариант 2: Через requirements.txt

Создайте файл `requirements.txt` в корне проекта:

```txt
scikit-learn>=1.3.0
scrapy>=2.11.0
beautifulsoup4>=4.12.0
requests>=2.31.0
numpy>=1.24.1
scipy>=1.10.0
joblib>=1.3.0
threadpoolctl>=3.2.0
```

Затем установите:

```bash
pip install -r requirements.txt
```

### Вариант 3: Клонирование репозиториев (для разработки)

Если нужно работать с исходным кодом:

```bash
# Scikit-learn
git clone https://github.com/scikit-learn/scikit-learn.git
cd scikit-learn
pip install -e .

# Scrapy
cd ..
git clone https://github.com/scrapy/scrapy.git
cd scrapy
pip install -e .
```

## Проверка установки

Проверьте, что все библиотеки установлены:

```bash
python3 -c "import sklearn; print('scikit-learn:', sklearn.__version__)"
python3 -c "import scrapy; print('scrapy:', scrapy.__version__)"
python3 -c "import bs4; print('beautifulsoup4: OK')"
python3 -c "import requests; print('requests:', requests.__version__)"
```

## Настройка в .env

Убедитесь, что в файле `.env` указан правильный путь к Python:

```env
PYTHON_PATH=python3
# или
PYTHON_PATH=/usr/bin/python3
```

## Структура работы системы

1. **Scrapy** - парсит объявления с сайтов (krisha.kz, olx.kz и др.)
2. **scikit-learn** - анализирует извлеченные данные и определяет риск мошенничества
3. **Gemini AI** - интерпретирует результаты (ТОЛЬКО для объяснения, не для поиска данных)

## Примечания

- Scrapy требует установки дополнительных зависимостей для некоторых функций
- scikit-learn автоматически создаст модель при первом использовании
- Модель сохраняется в `ml_scripts/fraud_detection_model.pkl`
- Для продакшена рекомендуется обучить модель на реальных данных

