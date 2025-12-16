<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ImageHashService
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
     * Анализ изображений через Python + imagehash.
     *
     * @param array $filesMeta Метаинформация о файлах из CheckController::checkImage
     * @return array Ассоциативный массив [stored_path => hashes]
     */
    public function analyzeImages(array $filesMeta): array
    {
        try {
            $script = $this->createImageHashScript();
            $scriptPath = $this->scriptsPath . '/image_hash_scan.py';
            file_put_contents($scriptPath, $script);

            $dataFile = $this->scriptsPath . '/image_files.json';
            file_put_contents($dataFile, json_encode($filesMeta, JSON_UNESCAPED_UNICODE));

            $result = Process::run([
                $this->pythonPath,
                $scriptPath,
                escapeshellarg($dataFile),
            ]);

            if (!$result->successful()) {
                Log::warning('ImageHashService: python script failed: ' . $result->errorOutput());
                return [];
            }

            $output = json_decode($result->output(), true);
            return is_array($output) ? $output : [];
        } catch (\Throwable $e) {
            Log::error('ImageHashService error: ' . $e->getMessage());
            return [];
        }
    }

    private function createImageHashScript(): string
    {
        return <<<'PYTHON'
import sys
import json
from pathlib import Path
from io import BytesIO

try:
    from PIL import Image
    import imagehash
    IMAGEHASH_AVAILABLE = True
except ImportError:
    IMAGEHASH_AVAILABLE = False


def compute_hashes_for_file(file_meta):
    """Возвращает словарь с хэшами для одного файла."""
    stored_path = file_meta.get('stored_path') or file_meta.get('path')
    absolute_path = file_meta.get('absolute_path')
    result = {
        'stored_path': stored_path,
        'average_hash': None,
        'phash': None,
        'dhash': None,
        'whash': None,
        'colorhash': None,
        'error': None,
    }

    if not IMAGEHASH_AVAILABLE:
        result['error'] = 'imagehash_not_installed'
        return result

    # Используем absolute_path, если есть, иначе пробуем stored_path как относительный путь
    path_to_use = absolute_path or stored_path
    if not path_to_use:
        result['error'] = 'no_path'
        return result

    try:
        path = Path(path_to_use)
        if not path.is_file():
            result['error'] = 'file_not_found'
            return result

        img = Image.open(path).convert('RGBA')

        try:
            result['average_hash'] = str(imagehash.average_hash(img))
        except Exception:
            pass

        try:
            result['phash'] = str(imagehash.phash(img))
        except Exception:
            pass

        try:
            result['dhash'] = str(imagehash.dhash(img))
        except Exception:
            pass

        try:
            result['whash'] = str(imagehash.whash(img))
        except Exception:
            pass

        try:
            result['colorhash'] = str(imagehash.colorhash(img))
        except Exception:
            pass

    except Exception as e:
        result['error'] = str(e)

    return result


def main():
    if len(sys.argv) < 2:
        print(json.dumps({}))
        sys.exit(1)

    data_file = sys.argv[1]
    # data_file может быть передан экранированным через PHP escapeshellarg
    if data_file.startswith("'") and data_file.endswith("'"):
        data_file = data_file[1:-1]

    try:
        with open(data_file, 'r', encoding='utf-8') as f:
            files_meta = json.load(f)
    except Exception:
        print(json.dumps({}))
        sys.exit(1)

    if not isinstance(files_meta, list):
        print(json.dumps({}))
        sys.exit(0)

    result = {}
    for file_meta in files_meta:
        hashes = compute_hashes_for_file(file_meta)
        key = hashes.get('stored_path') or file_meta.get('stored_path')
        if key:
            result[key] = hashes

    print(json.dumps(result, ensure_ascii=False))


if __name__ == '__main__':
    main()
PYTHON;
    }
}
