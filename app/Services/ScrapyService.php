<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ScrapyService
{
    private string $scrapyPath;
    private string $pythonPath;

    public function __construct()
    {
        // Путь к scrapy (будет установлен через pip)
        $this->pythonPath = env('PYTHON_PATH', 'python3');
        $this->scrapyPath = base_path('scrapy_scripts');
        
        // Создаем директорию для скриптов если не существует
        if (!is_dir($this->scrapyPath)) {
            mkdir($this->scrapyPath, 0755, true);
        }
    }

    /**
     * Парсинг объявления по URL
     */
    public function parseListing(string $url): array
    {
        try {
            // Определяем тип сайта и используем соответствующий парсер
            $domain = parse_url($url, PHP_URL_HOST);
            
            if (str_contains($domain, 'krisha.kz')) {
                return $this->parseKrishaKz($url);
            } elseif (str_contains($domain, 'olx.kz') || str_contains($domain, 'olx.uz')) {
                return $this->parseOlx($url);
            } else {
                return $this->parseGeneric($url);
            }
        } catch (\Exception $e) {
            Log::error("Scrapy parsing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Парсинг Krisha.kz
     */
    private function parseKrishaKz(string $url): array
    {
        $script = $this->createKrishaParser();
        $scriptPath = $this->scrapyPath . '/krisha_parser.py';
        file_put_contents($scriptPath, $script);

        $result = Process::run([
            $this->pythonPath,
            $scriptPath,
            escapeshellarg($url)
        ]);

        if ($result->successful()) {
            $output = json_decode($result->output(), true);
            return [
                'success' => true,
                'data' => $output ?? [],
                'source' => 'krisha.kz'
            ];
        }

        return [
            'success' => false,
            'error' => $result->errorOutput(),
            'data' => []
        ];
    }

    /**
     * Парсинг OLX
     */
    private function parseOlx(string $url): array
    {
        $script = $this->createOlxParser();
        $scriptPath = $this->scrapyPath . '/olx_parser.py';
        file_put_contents($scriptPath, $script);

        $result = Process::run([
            $this->pythonPath,
            $scriptPath,
            escapeshellarg($url)
        ]);

        if ($result->successful()) {
            $output = json_decode($result->output(), true);
            return [
                'success' => true,
                'data' => $output ?? [],
                'source' => 'olx'
            ];
        }

        return [
            'success' => false,
            'error' => $result->errorOutput(),
            'data' => []
        ];
    }

    /**
     * Универсальный парсер
     */
    private function parseGeneric(string $url): array
    {
        $script = $this->createGenericParser();
        $scriptPath = $this->scrapyPath . '/generic_parser.py';
        file_put_contents($scriptPath, $script);

        $result = Process::run([
            $this->pythonPath,
            $scriptPath,
            escapeshellarg($url)
        ]);

        if ($result->successful()) {
            $output = json_decode($result->output(), true);
            return [
                'success' => true,
                'data' => $output ?? [],
                'source' => 'generic'
            ];
        }

        return [
            'success' => false,
            'error' => $result->errorOutput(),
            'data' => []
        ];
    }

    /**
     * Создание парсера для Krisha.kz с использованием Scrapy
     */
    private function createKrishaParser(): string
    {
        return <<<'PYTHON'
import sys
import json
import scrapy
from scrapy.crawler import CrawlerProcess
from scrapy.utils.project import get_project_settings
from scrapy.http import HtmlResponse
import requests

class KrishaSpider(scrapy.Spider):
    name = 'krisha'
    
    def __init__(self, url, *args, **kwargs):
        super(KrishaSpider, self).__init__(*args, **kwargs)
        self.start_urls = [url]
        self.result = {
            'title': '',
            'description': '',
            'price': '',
            'phone': '',
            'location': '',
            'images': [],
            'seller_name': '',
            'category': '',
            'posted_date': '',
            'full_text': ''
        }
    
    def parse(self, response):
        # Заголовок
        title = response.css('h1::text').get() or response.css('title::text').get()
        if title:
            self.result['title'] = title.strip()
        
        # Описание
        desc = response.css('div.description::text').getall() or response.css('div[data-name="description"]::text').getall()
        if desc:
            self.result['description'] = ' '.join([d.strip() for d in desc if d.strip()])
        
        # Цена
        price = response.css('div.price::text').get() or response.css('span.price::text').get()
        if price:
            self.result['price'] = price.strip()
        
        # Телефон
        phone = response.css('a[href^="tel:"]::text').get() or response.css('div.phone::text').get()
        if phone:
            self.result['phone'] = phone.strip()
        
        # Местоположение
        location = response.css('div.location::text').get() or response.css('span.location::text').get()
        if location:
            self.result['location'] = location.strip()
        
        # Изображения
        images = response.css('img::attr(src)').getall() or response.css('img::attr(data-src)').getall()
        self.result['images'] = [img for img in images[:10] if img and 'http' in img]
        
        # Полный текст
        self.result['full_text'] = ' '.join(response.css('body *::text').getall())
        
        return self.result

def parse_krisha(url):
    try:
        # Используем Scrapy для парсинга
        process = CrawlerProcess({
            'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'ROBOTSTXT_OBEY': False,
            'LOG_LEVEL': 'ERROR'
        })
        
        spider = KrishaSpider(url=url)
        process.crawl(spider)
        process.start()
        
        return spider.result
        
    except Exception as e:
        # Fallback на requests + BeautifulSoup если Scrapy недоступен
        try:
            import requests
            from bs4 import BeautifulSoup
            
            headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}
            response = requests.get(url, headers=headers, timeout=10)
            response.raise_for_status()
            
            soup = BeautifulSoup(response.content, 'html.parser')
            
            data = {
                'title': '',
                'description': '',
                'price': '',
                'phone': '',
                'location': '',
                'images': [],
                'seller_name': '',
                'category': '',
                'posted_date': '',
                'full_text': soup.get_text(separator=' ', strip=True)
            }
            
            title_elem = soup.find('h1') or soup.find('title')
            if title_elem:
                data['title'] = title_elem.get_text(strip=True)
            
            desc_elem = soup.find('div', class_='description') or soup.find('div', {'data-name': 'description'})
            if desc_elem:
                data['description'] = desc_elem.get_text(strip=True)
            
            price_elem = soup.find('div', class_='price') or soup.find('span', class_='price')
            if price_elem:
                data['price'] = price_elem.get_text(strip=True)
            
            phone_elem = soup.find('a', href=lambda x: x and 'tel:' in x) or soup.find('div', class_='phone')
            if phone_elem:
                data['phone'] = phone_elem.get_text(strip=True)
            
            location_elem = soup.find('div', class_='location') or soup.find('span', class_='location')
            if location_elem:
                data['location'] = location_elem.get_text(strip=True)
            
            img_tags = soup.find_all('img')
            for img in img_tags[:10]:
                src = img.get('src') or img.get('data-src')
                if src and 'http' in src:
                    data['images'].append(src)
            
            return data
        except:
            return {'error': str(e)}

if __name__ == '__main__':
    url = sys.argv[1] if len(sys.argv) > 1 else ''
    result = parse_krisha(url)
    print(json.dumps(result, ensure_ascii=False))
PYTHON;
    }

    /**
     * Создание парсера для OLX с использованием Scrapy
     */
    private function createOlxParser(): string
    {
        return <<<'PYTHON'
import sys
import json
import scrapy
from scrapy.crawler import CrawlerProcess

class OlxSpider(scrapy.Spider):
    name = 'olx'
    
    def __init__(self, url, *args, **kwargs):
        super(OlxSpider, self).__init__(*args, **kwargs)
        self.start_urls = [url]
        self.result = {
            'title': '',
            'description': '',
            'price': '',
            'phone': '',
            'location': '',
            'images': [],
            'seller_name': '',
            'category': '',
            'posted_date': '',
            'full_text': ''
        }
    
    def parse(self, response):
        # Заголовок
        title = response.css('h1::text').get() or response.css('title::text').get()
        if title:
            self.result['title'] = title.strip()
        
        # Описание
        desc = response.css('div[data-cy="ad_description"]::text').getall() or response.css('div.description::text').getall()
        if desc:
            self.result['description'] = ' '.join([d.strip() for d in desc if d.strip()])
        
        # Цена
        price = response.css('div[data-testid="ad-price-container"]::text').get() or response.css('h3.price::text').get()
        if price:
            self.result['price'] = price.strip()
        
        # Телефон
        phone = response.css('a[href^="tel:"]::text').get()
        if phone:
            self.result['phone'] = phone.strip()
        
        # Местоположение
        location = response.css('div[data-testid="location-date"]::text').get() or response.css('span.location::text').get()
        if location:
            self.result['location'] = location.strip()
        
        # Изображения
        images = response.css('img::attr(src)').getall() or response.css('img::attr(data-src)').getall()
        self.result['images'] = [img for img in images[:10] if img and 'http' in img and 'placeholder' not in img.lower()]
        
        # Полный текст
        self.result['full_text'] = ' '.join(response.css('body *::text').getall())
        
        return self.result

def parse_olx(url):
    try:
        process = CrawlerProcess({
            'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'ROBOTSTXT_OBEY': False,
            'LOG_LEVEL': 'ERROR'
        })
        
        spider = OlxSpider(url=url)
        process.crawl(spider)
        process.start()
        
        return spider.result
        
    except Exception as e:
        # Fallback на requests + BeautifulSoup
        try:
            import requests
            from bs4 import BeautifulSoup
            
            headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}
            response = requests.get(url, headers=headers, timeout=10)
            response.raise_for_status()
            
            soup = BeautifulSoup(response.content, 'html.parser')
            
            data = {
                'title': '',
                'description': '',
                'price': '',
                'phone': '',
                'location': '',
                'images': [],
                'seller_name': '',
                'category': '',
                'posted_date': '',
                'full_text': soup.get_text(separator=' ', strip=True)
            }
            
            title_elem = soup.find('h1') or soup.find('title')
            if title_elem:
                data['title'] = title_elem.get_text(strip=True)
            
            desc_elem = soup.find('div', {'data-cy': 'ad_description'}) or soup.find('div', class_='description')
            if desc_elem:
                data['description'] = desc_elem.get_text(strip=True)
            
            price_elem = soup.find('div', {'data-testid': 'ad-price-container'}) or soup.find('h3', class_='price')
            if price_elem:
                data['price'] = price_elem.get_text(strip=True)
            
            phone_elem = soup.find('a', href=lambda x: x and 'tel:' in x)
            if phone_elem:
                data['phone'] = phone_elem.get_text(strip=True)
            
            location_elem = soup.find('div', {'data-testid': 'location-date'}) or soup.find('span', class_='location')
            if location_elem:
                data['location'] = location_elem.get_text(strip=True)
            
            img_tags = soup.find_all('img')
            for img in img_tags[:10]:
                src = img.get('src') or img.get('data-src')
                if src and 'http' in src and 'placeholder' not in src.lower():
                    data['images'].append(src)
            
            return data
        except:
            return {'error': str(e)}

if __name__ == '__main__':
    url = sys.argv[1] if len(sys.argv) > 1 else ''
    result = parse_olx(url)
    print(json.dumps(result, ensure_ascii=False))
PYTHON;
    }

    /**
     * Создание универсального парсера с использованием Scrapy
     */
    private function createGenericParser(): string
    {
        return <<<'PYTHON'
import sys
import json
import scrapy
from scrapy.crawler import CrawlerProcess
import re

class GenericSpider(scrapy.Spider):
    name = 'generic'
    
    def __init__(self, url, *args, **kwargs):
        super(GenericSpider, self).__init__(*args, **kwargs)
        self.start_urls = [url]
        self.result = {
            'title': '',
            'description': '',
            'price': '',
            'phone': '',
            'location': '',
            'images': [],
            'full_text': ''
        }
    
    def parse(self, response):
        # Заголовок
        title = response.css('h1::text').get() or response.css('title::text').get()
        if title:
            self.result['title'] = title.strip()
        
        # Полный текст
        self.result['full_text'] = ' '.join(response.css('body *::text').getall())
        
        # Поиск телефонов в тексте
        phone_pattern = r'[\+]?[7-8]?[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}'
        phones = re.findall(phone_pattern, self.result['full_text'])
        if phones:
            self.result['phone'] = phones[0]
        
        # Поиск цен
        price_pattern = r'(\d+[\s]?[\d\s]*)\s*(тенге|тг|₸|KZT|сом|руб|₽)'
        prices = re.findall(price_pattern, self.result['full_text'], re.IGNORECASE)
        if prices:
            self.result['price'] = ' '.join(prices[0])
        
        # Изображения
        images = response.css('img::attr(src)').getall() or response.css('img::attr(data-src)').getall()
        self.result['images'] = [img for img in images[:10] if img and 'http' in img]
        
        return self.result

def parse_generic(url):
    try:
        process = CrawlerProcess({
            'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'ROBOTSTXT_OBEY': False,
            'LOG_LEVEL': 'ERROR'
        })
        
        spider = GenericSpider(url=url)
        process.crawl(spider)
        process.start()
        
        return spider.result
        
    except Exception as e:
        # Fallback на requests + BeautifulSoup
        try:
            import requests
            from bs4 import BeautifulSoup
            import re
            
            headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}
            response = requests.get(url, headers=headers, timeout=10)
            response.raise_for_status()
            
            soup = BeautifulSoup(response.content, 'html.parser')
            
            data = {
                'title': '',
                'description': '',
                'price': '',
                'phone': '',
                'location': '',
                'images': [],
                'full_text': soup.get_text(separator=' ', strip=True)
            }
            
            title_elem = soup.find('h1') or soup.find('title')
            if title_elem:
                data['title'] = title_elem.get_text(strip=True)
            
            # Поиск телефонов
            phone_pattern = r'[\+]?[7-8]?[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}'
            phones = re.findall(phone_pattern, data['full_text'])
            if phones:
                data['phone'] = phones[0]
            
            # Поиск цен
            price_pattern = r'(\d+[\s]?[\d\s]*)\s*(тенге|тг|₸|KZT|сом|руб|₽)'
            prices = re.findall(price_pattern, data['full_text'], re.IGNORECASE)
            if prices:
                data['price'] = ' '.join(prices[0])
            
            # Изображения
            img_tags = soup.find_all('img')
            for img in img_tags[:10]:
                src = img.get('src') or img.get('data-src')
                if src and 'http' in src:
                    data['images'].append(src)
            
            return data
        except:
            return {'error': str(e)}

if __name__ == '__main__':
    url = sys.argv[1] if len(sys.argv) > 1 else ''
    result = parse_generic(url)
    print(json.dumps(result, ensure_ascii=False))
PYTHON;
    }
}

