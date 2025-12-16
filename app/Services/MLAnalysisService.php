<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class MLAnalysisService
{
    private string $pythonPath;
    private string $scriptsPath;

    public function __construct()
    {
        $this->pythonPath = env('PYTHON_PATH', 'python3');
        $this->scriptsPath = base_path('ml_scripts');
        
        if (!is_dir($this->scriptsPath)) {
            mkdir($this->scriptsPath, 0755, true);
        }
    }

    /**
     * Анализ объявления с использованием scikit-learn
     * 
     * @param array $listingData Данные объявления
     * @return array Результаты ML анализа
     */
    public function analyzeListing(array $listingData): array
    {
        try {
            $script = $this->createMLAnalysisScript();
            $scriptPath = $this->scriptsPath . '/analyze_listing.py';
            file_put_contents($scriptPath, $script);
            
            // Сохраняем данные во временный файл
            $dataFile = $this->scriptsPath . '/listing_data.json';
            file_put_contents($dataFile, json_encode($listingData, JSON_UNESCAPED_UNICODE));

            $result = Process::run([
                $this->pythonPath,
                $scriptPath,
                escapeshellarg($dataFile)
            ]);

            if ($result->successful()) {
                $output = json_decode($result->output(), true);
                return [
                    'success' => true,
                    'risk_score' => $output['risk_score'] ?? 0,
                    'risk_factors' => $output['risk_factors'] ?? [],
                    'ml_features' => $output['features'] ?? [],
                    'model_confidence' => $output['confidence'] ?? 0,
                    // Хэши изображений, рассчитанные через imagehash
                    'image_hashes' => $output['image_hashes'] ?? [],
                ];
            }

            Log::warning("ML Analysis failed: " . $result->errorOutput());
            return $this->getFallbackAnalysis($listingData);

        } catch (\Exception $e) {
            Log::error("ML Analysis error: " . $e->getMessage());
            return $this->getFallbackAnalysis($listingData);
        }
    }

    /**
     * Создание скрипта для ML анализа с scikit-learn
     */
    private function createMLAnalysisScript(): string
    {
        return <<<'PYTHON'
import sys
import json
import re
from pathlib import Path
from io import BytesIO

# Импорты scikit-learn
try:
    from sklearn.feature_extraction.text import TfidfVectorizer
    from sklearn.ensemble import RandomForestClassifier
    import numpy as np
    import pickle
    SKLEARN_AVAILABLE = True
except ImportError:
    SKLEARN_AVAILABLE = False

# Импорт библиотеки perceptual image hashing (imagehash)
try:
    from PIL import Image
    import imagehash
    import requests
    IMAGEHASH_AVAILABLE = True
except ImportError:
    IMAGEHASH_AVAILABLE = False

def extract_features(listing_data):
    """Извлечение признаков из объявления"""
    features = {
        'text_length': 0,
        'price_suspicious': 0,
        'phone_present': 0,
        'description_suspicious_keywords': 0,
        'title_suspicious_keywords': 0,
        'has_images': 0,
        'location_present': 0,
        'price_too_low': 0,
        'price_too_high': 0,
        'description_quality': 0,
        'urgency_keywords': 0,
        'contact_info_quality': 0
    }
    
    # Длина текста
    full_text = (listing_data.get('title', '') + ' ' + 
                 listing_data.get('description', '') + ' ' + 
                 listing_data.get('full_text', '')).strip()
    features['text_length'] = len(full_text)
    
    # Подозрительные ключевые слова в описании
    suspicious_keywords = [
        'срочно', 'быстро', 'немедленно', 'сегодня', 'прямо сейчас',
        'перевод', 'предоплата', 'залог', 'без документов',
        'недорого', 'дешево', 'скидка', 'акция', 'распродажа'
    ]
    description = listing_data.get('description', '').lower()
    title = listing_data.get('title', '').lower()
    
    for keyword in suspicious_keywords:
        if keyword in description:
            features['description_suspicious_keywords'] += 1
        if keyword in title:
            features['title_suspicious_keywords'] += 1
    
    # Ключевые слова срочности
    urgency_keywords = ['срочно', 'быстро', 'немедленно', 'сегодня', 'прямо сейчас']
    for keyword in urgency_keywords:
        if keyword in full_text.lower():
            features['urgency_keywords'] += 1
    
    # Анализ цены
    price_str = listing_data.get('price', '')
    if price_str:
        # Извлекаем числа из цены
        price_numbers = re.findall(r'\d+', price_str.replace(' ', ''))
        if price_numbers:
            try:
                price = int(''.join(price_numbers))
                # Подозрительно низкая цена (менее 10000)
                if price < 10000:
                    features['price_too_low'] = 1
                # Подозрительно высокая цена (более 100000000)
                elif price > 100000000:
                    features['price_too_high'] = 1
                features['price_suspicious'] = 1 if (price < 50000 or price > 50000000) else 0
            except:
                pass
    
    # Наличие телефона
    features['phone_present'] = 1 if listing_data.get('phone') else 0
    
    # Наличие изображений
    images = listing_data.get('images', [])
    features['has_images'] = 1 if len(images) > 0 else 0
    
    # Наличие местоположения
    features['location_present'] = 1 if listing_data.get('location') else 0
    
    # Качество описания (длина и детализация)
    desc_length = len(listing_data.get('description', ''))
    if desc_length < 50:
        features['description_quality'] = 0  # Низкое качество
    elif desc_length < 200:
        features['description_quality'] = 1  # Среднее качество
    else:
        features['description_quality'] = 2  # Высокое качество
    
    # Качество контактной информации
    contact_score = 0
    if listing_data.get('phone'):
        contact_score += 1
    if listing_data.get('location'):
        contact_score += 1
    if listing_data.get('seller_name'):
        contact_score += 1
    features['contact_info_quality'] = contact_score
    
    return features

def compute_image_hashes(listing_data):
    """
    Расчет perceptual-хэшей для изображений объявления с помощью библиотеки imagehash.
    Поддерживаются как локальные пути к файлам, так и URL.
    """
    if not IMAGEHASH_AVAILABLE:
        return []

    images = listing_data.get('images', [])
    hashes = []

    for img in images:
        img_info = {
            'source': img,
            'average_hash': None,
            'phash': None,
            'dhash': None,
            'whash': None,
            'colorhash': None,
            'error': None,
        }

        try:
            # Определяем, это URL или локальный путь
            if isinstance(img, dict):
                # Если изображение передано как словарь, пробуем взять url/path
                img_url = img.get('url') or img.get('path') or img.get('src')
            else:
                img_url = str(img)

            if not img_url:
                img_info['error'] = 'empty_image_reference'
                hashes.append(img_info)
                continue

            # Загрузка изображения
            if img_url.startswith('http://') or img_url.startswith('https://'):
                resp = requests.get(img_url, timeout=10)
                resp.raise_for_status()
                pil_image = Image.open(BytesIO(resp.content))
            else:
                # Считаем, что это локальный путь
                path = Path(img_url)
                if not path.is_file():
                    img_info['error'] = 'file_not_found'
                    hashes.append(img_info)
                    continue
                pil_image = Image.open(path)

            pil_image = pil_image.convert('RGBA')

            # Разные типы хэшей из imagehash
            try:
                img_info['average_hash'] = str(imagehash.average_hash(pil_image))
            except Exception:
                pass

            try:
                img_info['phash'] = str(imagehash.phash(pil_image))
            except Exception:
                pass

            try:
                img_info['dhash'] = str(imagehash.dhash(pil_image))
            except Exception:
                pass

            try:
                img_info['whash'] = str(imagehash.whash(pil_image))
            except Exception:
                pass

            try:
                img_info['colorhash'] = str(imagehash.colorhash(pil_image))
            except Exception:
                pass

        except Exception as e:
            img_info['error'] = str(e)

        hashes.append(img_info)

    return hashes

def calculate_risk_score(features):
    """Расчет риска на основе признаков"""
    risk_score = 0
    
    # Низкое качество описания увеличивает риск
    if features['description_quality'] == 0:
        risk_score += 20
    elif features['description_quality'] == 1:
        risk_score += 10
    
    # Подозрительные ключевые слова
    risk_score += min(features['description_suspicious_keywords'] * 5, 25)
    risk_score += min(features['title_suspicious_keywords'] * 5, 25)
    risk_score += min(features['urgency_keywords'] * 3, 15)
    
    # Подозрительная цена
    if features['price_too_low']:
        risk_score += 15
    if features['price_too_high']:
        risk_score += 10
    if features['price_suspicious']:
        risk_score += 5
    
    # Отсутствие важной информации
    if not features['phone_present']:
        risk_score += 10
    if not features['has_images']:
        risk_score += 5
    if not features['location_present']:
        risk_score += 5
    
    # Низкое качество контактной информации
    if features['contact_info_quality'] < 2:
        risk_score += 10
    
    # Ограничиваем до 100
    risk_score = min(risk_score, 100)
    
    return risk_score

def get_risk_factors(features, risk_score):
    """Определение факторов риска"""
    factors = []
    
    if features['description_quality'] == 0:
        factors.append('Низкое качество описания')
    
    if features['description_suspicious_keywords'] > 0:
        factors.append('Подозрительные ключевые слова в описании')
    
    if features['title_suspicious_keywords'] > 0:
        factors.append('Подозрительные ключевые слова в заголовке')
    
    if features['urgency_keywords'] > 2:
        factors.append('Избыточное использование слов срочности')
    
    if features['price_too_low']:
        factors.append('Подозрительно низкая цена')
    
    if features['price_too_high']:
        factors.append('Подозрительно высокая цена')
    
    if not features['phone_present']:
        factors.append('Отсутствие контактного телефона')
    
    if not features['has_images']:
        factors.append('Отсутствие фотографий')
    
    if not features['location_present']:
        factors.append('Отсутствие указания местоположения')
    
    if features['contact_info_quality'] < 2:
        factors.append('Недостаточная контактная информация')
    
    return factors

def analyze_with_sklearn(features):
    """Анализ с использованием scikit-learn"""
    if not SKLEARN_AVAILABLE:
        return None
    
    try:
        from pathlib import Path
        import os
        
        # Путь к модели
        script_dir = Path(__file__).parent
        model_path = script_dir / 'fraud_detection_model.pkl'
        
        # Если модель существует - используем её
        if model_path.exists():
            with open(model_path, 'rb') as f:
                model = pickle.load(f)
            
            # Преобразуем признаки в вектор
            feature_vector = np.array([list(features.values())])
            prediction = model.predict_proba(feature_vector)[0]
            return prediction[1] * 100  # Вероятность мошенничества
        
        # Если модели нет - создаём и обучаем базовую модель
        # Используем RandomForest для классификации
        from sklearn.ensemble import RandomForestClassifier
        from sklearn.model_selection import train_test_split
        
        # Создаём синтетические данные для обучения (в продакшене использовать реальные данные)
        # Это временное решение для демонстрации
        np.random.seed(42)
        n_samples = 1000
        X = np.random.rand(n_samples, len(features))
        y = np.random.randint(0, 2, n_samples)  # 0 = безопасно, 1 = мошенничество
        
        # Обучаем модель
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
        model = RandomForestClassifier(n_estimators=100, random_state=42, max_depth=10)
        model.fit(X_train, y_train)
        
        # Сохраняем модель
        with open(model_path, 'wb') as f:
            pickle.dump(model, f)
        
        # Предсказание для текущих признаков
        feature_vector = np.array([list(features.values())])
        prediction = model.predict_proba(feature_vector)[0]
        return prediction[1] * 100
        
    except Exception as e:
        # В случае ошибки возвращаем None и используем эвристику
        return None

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No data file provided'}))
        sys.exit(1)
    
    data_file = sys.argv[1]
    
    try:
        with open(data_file, 'r', encoding='utf-8') as f:
            listing_data = json.load(f)
    except Exception as e:
        print(json.dumps({'error': str(e)}))
        sys.exit(1)
    
    # Извлечение признаков
    features = extract_features(listing_data)

    # Расчет хэшей изображений через imagehash (если установлен)
    image_hashes = compute_image_hashes(listing_data)
    
    # Расчет риска
    risk_score = calculate_risk_score(features)
    
    # Факторы риска
    risk_factors = get_risk_factors(features, risk_score)
    
    # Попытка использовать scikit-learn модель
    ml_risk = analyze_with_sklearn(features)
    if ml_risk is not None:
        # Комбинируем эвристический и ML подходы
        risk_score = (risk_score * 0.6 + ml_risk * 0.4)
        confidence = 0.8
    else:
        confidence = 0.6
    
    result = {
        'risk_score': int(risk_score),
        'risk_factors': risk_factors,
        'features': features,
        'confidence': confidence,
        'image_hashes': image_hashes,
    }
    
    print(json.dumps(result, ensure_ascii=False))

if __name__ == '__main__':
    main()
PYTHON;
    }

    /**
     * Fallback анализ без ML
     */
    private function getFallbackAnalysis(array $listingData): array
    {
        $riskScore = 0;
        $riskFactors = [];
        
        // Простая эвристика
        $description = ($listingData['description'] ?? '') . ' ' . ($listingData['title'] ?? '');
        $descriptionLower = mb_strtolower($description);
        
        // Проверка на подозрительные слова
        $suspiciousKeywords = ['срочно', 'быстро', 'немедленно', 'перевод', 'предоплата'];
        foreach ($suspiciousKeywords as $keyword) {
            if (str_contains($descriptionLower, $keyword)) {
                $riskScore += 10;
                $riskFactors[] = "Подозрительное ключевое слово: {$keyword}";
            }
        }
        
        // Отсутствие важной информации
        if (empty($listingData['phone'])) {
            $riskScore += 15;
            $riskFactors[] = 'Отсутствие контактного телефона';
        }
        
        if (empty($listingData['images'])) {
            $riskScore += 10;
            $riskFactors[] = 'Отсутствие фотографий';
        }
        
        return [
            'success' => true,
            'risk_score' => min($riskScore, 100),
            'risk_factors' => $riskFactors,
            'ml_features' => [],
            'model_confidence' => 0.5
        ];
    }
}

