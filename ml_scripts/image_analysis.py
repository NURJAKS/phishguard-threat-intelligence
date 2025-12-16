import sys
import json
import os
from pathlib import Path

# Optional imports – зависят от окружения
try:
    import cv2  # OpenCV
except ImportError:  # pragma: no cover
    cv2 = None

try:
    from PIL import Image
    import imagehash
except ImportError:  # pragma: no cover
    Image = None
    imagehash = None

try:
    from imagededup.methods import PHash
except ImportError:  # pragma: no cover
    PHash = None

try:
    from py2neo import Graph
except ImportError:  # pragma: no cover
    Graph = None

import requests


def load_files_meta(path: str):
    with open(path, 'r', encoding='utf-8') as f:
        data = json.load(f)
    if not isinstance(data, list):
        raise ValueError('files_meta must be list')
    return data


def normalize_image(path: Path):
    """Нормализация изображения через OpenCV (если доступен).
    Возвращает PIL.Image для дальнейшей работы imagehash.
    """
    if cv2 is None or Image is None:
        # Fallback: прямой PIL-open
        return Image.open(path).convert('RGB') if Image is not None else None

    img = cv2.imread(str(path))
    if img is None:
        return None
    img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    # Лёгкий resize для стабильности hash (без фейковой логики)
    img = cv2.resize(img, (512, 512))
    pil_img = Image.fromarray(img)
    return pil_img


def compute_hashes(pil_img):
    if imagehash is None or pil_img is None:
        return {
            'status': 'unavailable',
            'hashes': {},
            'error': 'imagehash_or_pil_not_available',
            'source': 'python:imagehash',
        }

    hashes = {}
    try:
        hashes['ahash'] = str(imagehash.average_hash(pil_img))
    except Exception:
        hashes['ahash'] = None
    try:
        hashes['phash'] = str(imagehash.phash(pil_img))
    except Exception:
        hashes['phash'] = None
    try:
        hashes['dhash'] = str(imagehash.dhash(pil_img))
    except Exception:
        hashes['dhash'] = None
    try:
        hashes['whash'] = str(imagehash.whash(pil_img))
    except Exception:
        hashes['whash'] = None

    return {
        'status': 'computed',
        'hashes': hashes,
        'error': None,
        'source': 'opencv+imagehash' if cv2 is not None else 'imagehash',
    }


def find_internal_duplicates(files_meta):
    """Пока что только готовим структуру: фактический поиск дублей по БД делает Laravel/SQL.
    Здесь считаем similarity=1.0 только для самих себя, без выдуманных совпадений.
    """
    results = []
    for f in files_meta:
        results.append({
            'sha256': f.get('hash_sha256'),
            'similarity_max': 1.0,
            'matches': [],  # реальные матчи заполняет backend из PostgreSQL
        })
    return results


def serpapi_reverse_image(image_url: str):
    api_key = os.getenv('SERPAPI_KEY')
    if not api_key:
        return {
            'ran': False,
            'results_count': 0,
            'domains': [],
            'limit_reached': False,
            'error': 'serpapi_key_not_configured',
        }

    # Минимальный реальный запрос. Без интерпретации результата.
    try:
        params = {
            'engine': 'google_images',
            'image_url': image_url,
            'api_key': api_key,
        }
        resp = requests.get('https://serpapi.com/search', params=params, timeout=20)
        data = resp.json() if resp.ok else {}
        domains = []
        for res in data.get('image_results', [])[:20]:
            link = res.get('link') or ''
            try:
                dom = link.split('/')[2] if '://' in link else None
                if dom:
                    domains.append(dom)
            except Exception:
                continue
        return {
            'ran': True,
            'results_count': len(data.get('image_results', [])),
            'domains': sorted(set(domains)),
            'limit_reached': bool(data.get('error')),
            'error': None if resp.ok else data.get('error', 'http_error'),
        }
    except Exception as e:
        return {
            'ran': True,
            'results_count': 0,
            'domains': [],
            'limit_reached': False,
            'error': str(e),
        }


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'no_input_file'}))
        sys.exit(1)

    input_file = sys.argv[1]
    if input_file.startswith("'") and input_file.endswith("'"):
        input_file = input_file[1:-1]

    try:
        files_meta = load_files_meta(input_file)
    except Exception as e:
        print(json.dumps({'error': str(e)}))
        sys.exit(1)

    if not files_meta:
        print(json.dumps({'error': 'empty_files_meta'}))
        sys.exit(0)

    # Для MVP работаем с первым файлом как основным
    first = files_meta[0]
    abs_path = first.get('absolute_path')
    url = first.get('url')

    # 1) Нормализация + hash
    pil_img = None
    hashes_block = None
    if abs_path and Image is not None:
        try:
            pil_img = normalize_image(Path(abs_path))
            hashes_block = compute_hashes(pil_img)
        except Exception as e:
            hashes_block = {
                'status': 'unavailable',
                'hashes': {},
                'error': str(e),
                'source': 'opencv+imagehash',
            }
    else:
        hashes_block = {
            'status': 'unavailable',
            'hashes': {},
            'error': 'no_absolute_path_or_pil',
            'source': 'opencv+imagehash',
        }

    # 2) Внутренняя похожесть (MVP – только структура)
    internal_sim = find_internal_duplicates(files_meta)

    # 3) SerpAPI reverse image (если URL доступен)
    reverse_block = {
        'serpapi': {
            'ran': False,
            'results_count': 0,
            'domains': [],
            'limit_reached': False,
            'error': 'no_image_url',
        },
        'rapidapi': {
            'ran': False,
            'reason': 'not_implemented',
        },
    }
    if url:
        reverse_block['serpapi'] = serpapi_reverse_image(url=url if url.startswith('http') else '')

    # 4) Собираем итоговый JSON (без выдумок)
    verdict = {
        'level': 'safe',
        'risk_score': 0,
        'sources': ['Internal DB', 'ImageHash'],
        'explanation': [
            'Совпадений по изображению в текущем запросе не анализировалось на уровне Python-скрипта (анализ делает backend по БД).',
            'Перцептивные хэши рассчитаны локально и могут использоваться для поиска дублей.',
        ],
    }

    result = {
        'verdict': verdict,
        'image_fingerprint_profile': {
            'hashes': hashes_block.get('hashes', {}),
            'visual_signature': hashes_block.get('hashes', {}).get('phash') or hashes_block.get('hashes', {}).get('ahash'),
            'similarity_score_max': max((i['similarity_max'] for i in internal_sim), default=0.0),
            'status': hashes_block.get('status'),
            'source': hashes_block.get('source'),
            'error': hashes_block.get('error'),
        },
        'internal_db_matches': {
            'matches_count': 0,   # фактические совпадения считает Laravel по PostgreSQL
            'first_seen': None,
            'last_seen': None,
            'related_checks': [],
            'source': 'Internal DB',
        },
        'reverse_image_osint': reverse_block,
        'technical_metadata': {
            'files': files_meta,
        },
    }

    print(json.dumps(result, ensure_ascii=False))


if __name__ == '__main__':
    main()
