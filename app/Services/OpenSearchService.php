<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenSearch Service
 * Integrates OpenSearch for phone number search and indexing
 */
class OpenSearchService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $indexName;

    public function __construct()
    {
        $this->baseUrl = env('OPENSEARCH_URL', 'http://localhost:9200');
        $this->username = env('OPENSEARCH_USERNAME', 'admin');
        $this->password = env('OPENSEARCH_PASSWORD', 'Admin@123');
        $this->indexName = env('OPENSEARCH_INDEX', 'phone_numbers');
        
        // Создаем индекс при первом использовании (с задержкой чтобы не блокировать)
        try {
            $this->ensureIndexExists();
        } catch (\Exception $e) {
            // Игнорируем ошибки при инициализации
        }
    }

    /**
     * Ensure index exists
     */
    private function ensureIndexExists(): void
    {
        try {
            // Проверяем без аутентификации (security отключен)
            $response = Http::timeout(5)->get("{$this->baseUrl}/{$this->indexName}");
            $exists = $response->successful();

            if (!$exists && $response->status() === 404) {
                $this->createIndex();
            }
        } catch (\Exception $e) {
            Log::warning("OpenSearch index check failed: " . $e->getMessage());
        }
    }

    /**
     * Create index for phone numbers
     */
    private function createIndex(): void
    {
        try {
            $mapping = [
                'mappings' => [
                    'properties' => [
                        'phone' => ['type' => 'keyword'],
                        'phone_e164' => ['type' => 'keyword'],
                        'country' => ['type' => 'keyword'],
                        'region' => ['type' => 'text'],
                        'city' => ['type' => 'text'],
                        'operator' => ['type' => 'keyword'],
                        'operator_name' => ['type' => 'text'],
                        'phone_type' => ['type' => 'keyword'],
                        'location' => ['type' => 'geo_point'],  // Правильное поле для карты
                        'risk_score' => ['type' => 'integer'],
                        'verdict' => ['type' => 'keyword'],
                        'data_source' => ['type' => 'keyword'],
                        'timestamp' => ['type' => 'date'],
                        'metadata' => ['type' => 'object', 'enabled' => true],
                    ]
                ]
            ];

            Http::timeout(10)
                ->put("{$this->baseUrl}/{$this->indexName}", $mapping);
        } catch (\Exception $e) {
            Log::error("OpenSearch index creation failed: " . $e->getMessage());
        }
    }

    /**
     * Index phone number data
     */
    public function indexPhone(array $phoneData): bool
    {
        try {
            $document = [
                'phone' => $phoneData['phone'] ?? null,
                'phone_e164' => $phoneData['international_format'] ?? $phoneData['phone'] ?? null,
                'country' => $phoneData['country'] ?? null,
                'region' => $phoneData['region'] ?? null,
                'city' => $phoneData['city'] ?? null,
                'operator' => $phoneData['operator_name'] ?? null,
                'operator_name' => $phoneData['operator_name'] ?? null,
                'phone_type' => $phoneData['phone_type'] ?? null,
                'risk_score' => $phoneData['risk_score'] ?? 0,
                'verdict' => $phoneData['verdict'] ?? 'Unknown',
                'data_source' => $phoneData['data_source'] ?? 'PhoneLocatorService',
                'timestamp' => now()->toIso8601String(),
                'metadata' => $phoneData,
            ];

            // Add location (geo_point) - обязательно для визуализации на карте
            // Используем геоцентр города/региона, не точное местоположение пользователя
            $location = null;
            
            if (isset($phoneData['location_info']['coordinates'])) {
                // Координаты из location_info
                $coords = $phoneData['location_info']['coordinates'];
                if (isset($coords['lat']) && isset($coords['lng'])) {
                    $location = [
                        'lat' => floatval($coords['lat']),
                        'lon' => floatval($coords['lng'])
                    ];
                }
            }
            
            // Если координат нет, но есть регион/город - используем геоцентр
            if (!$location && isset($phoneData['region'])) {
                try {
                    $phoneLocatorService = app(\App\Services\PhoneLocatorService::class);
                    $regionCoords = $phoneLocatorService->getRegionCoordinates($phoneData['region']);
                    if ($regionCoords && isset($regionCoords['lat']) && isset($regionCoords['lng'])) {
                        $location = [
                            'lat' => floatval($regionCoords['lat']),
                            'lon' => floatval($regionCoords['lng'])
                        ];
                    }
                } catch (\Exception $e) {
                    // Игнорируем ошибки преобразования координат
                }
            }
            
            // Если координат нет, но есть город - пытаемся получить геоцентр города
            if (!$location && isset($phoneData['city']) && $phoneData['city'] !== $phoneData['region']) {
                try {
                    $phoneLocatorService = app(\App\Services\PhoneLocatorService::class);
                    $cityCoords = $phoneLocatorService->getRegionCoordinates($phoneData['city']);
                    if ($cityCoords && isset($cityCoords['lat']) && isset($cityCoords['lng'])) {
                        $location = [
                            'lat' => floatval($cityCoords['lat']),
                            'lon' => floatval($cityCoords['lng'])
                        ];
                    }
                } catch (\Exception $e) {
                    // Игнорируем ошибки преобразования координат
                }
            }
            
            // Добавляем location в документ только если координаты найдены
            if ($location) {
                $document['location'] = $location;
            }
            // Если location = null, поле не добавляется (это нормально для документов без координат)

            $phoneId = preg_replace('/[^0-9]/', '', $document['phone'] ?? '');
            
            $response = Http::withBasicAuth($this->username, $this->password)
                ->put("{$this->baseUrl}/{$this->indexName}/_doc/{$phoneId}", $document);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("OpenSearch indexing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search phone numbers
     */
    public function searchPhone(string $phone): array
    {
        try {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            
            $query = [
                'query' => [
                    'bool' => [
                        'should' => [
                            ['match' => ['phone' => $cleanPhone]],
                            ['match' => ['phone_e164' => $phone]],
                            ['wildcard' => ['phone' => "*{$cleanPhone}*"]],
                        ]
                    ]
                ],
                'size' => 10
            ];

            $response = Http::withBasicAuth($this->username, $this->password)
                ->post("{$this->baseUrl}/{$this->indexName}/_search", $query);

            if ($response->successful()) {
                $data = $response->json();
                return $data['hits']['hits'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error("OpenSearch search failed: " . $e->getMessage());
            return [];
        }
    }
}

