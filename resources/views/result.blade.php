@extends('layouts.app')

@push('styles')
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>

<style>
    /* ============================================
       КАРТА И ГЕОЛОКАЦИЯ
       ============================================ */
    #phoneLocationMap {
        z-index: 1;
        position: relative;
    }

    .leaflet-container {
        background: var(--bg-secondary) !important;
    }

    /* ============================================
       ВКЛАДКИ (TABS)
       ============================================ */
    .tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .tab {
        padding: 0.75rem 1.5rem;
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        color: var(--text-muted);
        cursor: pointer;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.2s;
        font-family: var(--font-mono);
    }

    .tab:hover {
        color: var(--text-main);
        background: rgba(255, 255, 255, 0.03);
    }

    .tab.active {
        color: var(--success);
        border-bottom-color: var(--success);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* ============================================
       ИНФОРМАЦИОННЫЕ КАРТОЧКИ
       ============================================ */
    .info-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .info-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border);
    }

    .info-card-title {
        font-size: 1rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-main);
    }

    .info-card-source {
        font-size: 0.75rem;
        color: var(--text-muted);
        font-family: var(--font-mono);
    }

    /* ============================================
       СЕТКА ИНФОРМАЦИИ
       ============================================ */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .info-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .info-value {
        font-size: 0.95rem;
        color: var(--text-main);
        font-family: var(--font-mono);
    }

    .info-value.undefined {
        color: var(--text-dim);
        font-style: italic;
    }

    /* ============================================
       БЕЙДЖИ ДОВЕРИЯ
       ============================================ */
    .badge-trust {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 2px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        font-family: var(--font-mono);
    }

    .badge-trust.low {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .badge-trust.medium {
        background: rgba(250, 204, 21, 0.1);
        color: var(--warning);
        border: 1px solid rgba(250, 204, 21, 0.2);
    }

    .badge-trust.high {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    /* ============================================
       СВОДКА РИСКА
       ============================================ */
    .summary-box {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-left: 4px solid;
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .summary-box.low {
        border-left-color: var(--success);
    }

    .summary-box.medium {
        border-left-color: var(--warning);
    }

    .summary-box.high {
        border-left-color: var(--danger);
    }

    .summary-box.unknown {
        border-left-color: var(--text-muted);
    }

    .risk-level {
        font-size: 1.5rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.5rem;
        font-family: var(--font-mono);
    }

    .risk-level.low {
        color: var(--success);
    }

    .risk-level.medium {
        color: var(--warning);
    }

    .risk-level.high {
        color: var(--danger);
    }

    .risk-level.unknown {
        color: var(--text-muted);
    }

    /* ============================================
       ОБЪЯСНЕНИЕ РИСКА
       ============================================ */
    .explanation-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem;
        background: rgba(255, 255, 255, 0.02);
        border-radius: var(--radius-md);
        margin-bottom: 0.5rem;
    }

    .explanation-icon {
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    /* ============================================
       OSINT ИНСТРУМЕНТЫ
       ============================================ */
    .osint-tool {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .osint-tool-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .osint-status {
        padding: 0.25rem 0.5rem;
        border-radius: 2px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        font-family: var(--font-mono);
    }

    .osint-status.ran {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
    }

    .osint-status.not-ran {
        background: rgba(115, 115, 115, 0.1);
        color: var(--text-muted);
    }

    .osint-status.error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    .osint-result {
        font-size: 0.875rem;
        color: var(--text-muted);
    }

    /* ============================================
       ПУСТЫЕ СОСТОЯНИЯ
       ============================================ */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-muted);
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
</style>
@endpush

@section('content')
@php
    $risk = $check->risk_score;
    $verdict = $check->verdict;
    $result = $check->result;
    
    // Определение уровня риска
    $riskLevel = 'unknown';
    $riskLabel = 'Недостаточно данных';
    if ($risk > 70) {
        $riskLevel = 'high';
        $riskLabel = 'Высокий риск';
    } elseif ($risk > 40) {
        $riskLevel = 'medium';
        $riskLabel = 'Средний риск';
    } elseif ($risk > 0) {
        $riskLevel = 'low';
        $riskLabel = 'Низкий риск';
    }
    
    // Данные телефона
    $phoneData = $result['checks']['phone'] ?? null;
    $phoneMetadata = $result['technical_metadata'] ?? null;
    $rawPhoneInfoga = $result['raw_phoneinfoga_data'] ?? null;
    
    // История активности (пока пусто)
    $activityHistory = null;
    
    // Данные от всех инструментов
    $truecallerData = $result['truecaller_data'] ?? null;
    $opensearchResults = $result['opensearch_results'] ?? null;
    $neo4jRelations = $result['neo4j_relations'] ?? null;
    $mrholmesData = $result['mrholmes_data'] ?? null;
    
    // OSINT данные - из всех инструментов
    $osintTools = [];
    
    // Truecaller
    if ($truecallerData && isset($truecallerData['found']) && $truecallerData['found']) {
        $osintTools[] = [
            'name' => 'Truecaller',
            'status' => 'ran',
            'result' => 'found',
            'comment' => $truecallerData['name'] 
                ? 'Найдено: ' . $truecallerData['name'] 
                : 'Местоположение найдено',
            'location' => $truecallerData['location'] ?? null,
            'name' => $truecallerData['name'] ?? null,
            'email' => $truecallerData['email'] ?? null,
        ];
    }
    
    // Mr.Holmes
    if ($mrholmesData && isset($mrholmesData['found']) && $mrholmesData['found']) {
        $osintTools[] = [
            'name' => 'Mr.Holmes',
            'status' => 'ran',
            'result' => 'found',
            'comment' => 'OSINT данные найдены',
            'sources' => $mrholmesData['sources'] ?? [],
            'emails' => $mrholmesData['emails'] ?? [],
            'social_media' => $mrholmesData['social_media'] ?? [],
        ];
    }
    
    // OpenSearch результаты
    if ($opensearchResults && count($opensearchResults) > 0) {
        $opensearchDetails = [];
        foreach (array_slice($opensearchResults, 0, 3) as $hit) {
            $source = $hit['_source'] ?? [];
            $details = [];
            if (isset($source['region'])) $details[] = 'Регион: ' . $source['region'];
            if (isset($source['operator'])) $details[] = 'Оператор: ' . $source['operator'];
            if (isset($source['risk_score'])) $details[] = 'Риск: ' . $source['risk_score'];
            if (isset($source['timestamp'])) {
                $timestamp = new \DateTime($source['timestamp']);
                $details[] = 'Дата: ' . $timestamp->format('d.m.Y H:i');
            }
            $opensearchDetails[] = implode(', ', $details);
        }
        
        $osintTools[] = [
            'name' => 'OpenSearch',
            'status' => 'ran',
            'result' => 'found',
            'comment' => 'Найдено ' . count($opensearchResults) . ' записей в индексе',
            'results_count' => count($opensearchResults),
            'details' => $opensearchDetails,
            'has_location' => isset($opensearchResults[0]['_source']['location']),
        ];
    }
    
    // Neo4j связи
    if ($neo4jRelations && count($neo4jRelations) > 0) {
        $osintTools[] = [
            'name' => 'Neo4j',
            'status' => 'ran',
            'result' => 'found',
            'comment' => 'Найдено ' . count($neo4jRelations) . ' связанных номеров',
            'related_phones' => $neo4jRelations,
        ];
    }
    
    // Sherlock (поиск в соцсетях) - отдельный сервис
    $sherlockData = $result['sherlock_data'] ?? null;
    if ($sherlockData) {
        $sherlockAccounts = $sherlockData['accounts'] ?? [];
        $sherlockStatus = $sherlockData['status'] ?? 'not-found';
        
        $sherlockLinks = [];
        foreach ($sherlockAccounts as $account) {
            if (isset($account['url'])) {
                $sherlockLinks[] = $account['url'];
            }
        }
        
        // Если Sherlock ничего не нашел - это валидный результат, не ошибка
        $osintTools[] = [
            'name' => 'Sherlock',
            'status' => $sherlockStatus === 'success' || $sherlockStatus === 'not-found' ? 'ran' : 'error',
            'result' => count($sherlockAccounts) > 0 ? 'found' : 'not-found',
            'comment' => count($sherlockAccounts) > 0 
                ? 'Найдено ' . count($sherlockAccounts) . ' аккаунтов в соцсетях' 
                : 'Аккаунты в соцсетях не найдены',
            'links' => $sherlockLinks,
            'accounts' => $sherlockAccounts
        ];
    } else {
        $osintTools[] = [
            'name' => 'Sherlock',
            'status' => 'not-ran',
            'result' => null,
            'comment' => 'Инструмент не запускался',
            'links' => [],
            'accounts' => []
        ];
    }
    
    // Факторы риска для объяснения
    $riskFactors = $result['risk_factors'] ?? [];
    
    // Источники
    $sources = [];
    if ($rawPhoneInfoga) $sources[] = 'PhoneInfoga'; // только метаданные
    if ($sherlockData && ($sherlockData['status'] === 'success' || $sherlockData['status'] === 'not-found')) $sources[] = 'Sherlock'; // соцсети
    if ($phoneMetadata) $sources[] = 'Internal DB';
@endphp

<div class="tabs" id="resultTabs">
    <button class="tab active" onclick="showTab('results')">📊 Результаты</button>
    <button class="tab" onclick="showTab('export')">💾 Экспорт</button>
</div>

<!-- 📊 Результаты (объединенная вкладка) -->
<div id="tab-results" class="tab-content active">
    <!-- Сводка -->
    <div class="summary-box {{ $riskLevel }}" style="margin-bottom: 2rem;">
        <div class="risk-level {{ $riskLevel }}">{{ $riskLabel }}</div>
        <div style="margin-bottom: 1rem;">
            <div style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                <strong>Номер:</strong> {{ $check->phone ?? 'Не указан' }}
            </div>
            <div style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                <strong>Статус проверки:</strong> Завершена
            </div>
            <div style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                <strong>Источники:</strong> {{ implode(', ', $sources) ?: 'Не указаны' }}
            </div>
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                <strong>Основание:</strong> 
                @if(count($riskFactors) > 0)
                    {{ count($riskFactors) }} {{ count($riskFactors) == 1 ? 'фактор' : 'факторов' }}:
                    @foreach($riskFactors as $idx => $factor)
                        {{ $factor['label'] ?? 'Неизвестно' }}@if($idx < count($riskFactors) - 1), @endif
                    @endforeach
                @else
                    недостаточно данных
                @endif
            </div>
        </div>
    </div>

    <!-- Заголовок секции Информация о номере -->
    <div style="margin-top: 2rem; margin-bottom: 1rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); border-bottom: 2px solid var(--success); padding-bottom: 0.5rem; display: inline-block;">
            📞 Информация о номере
        </h2>
    </div>
    
    @php
        $phoneMetadata = $check->result['technical_metadata'] ?? null;
        $region = $phoneMetadata['region'] ?? null;
        $city = $phoneMetadata['city'] ?? $region;
        $operator = $phoneMetadata['operator_name'] ?? null;
        
        // Получаем координаты из location_info
        $locationInfo = $phoneMetadata['location_info'] ?? null;
        $coordinates = $locationInfo['coordinates'] ?? null;
        
        // Если координаты есть, но нет lat/lng, пытаемся получить их из массива координат
        if ($coordinates && is_array($coordinates)) {
            $lat = $coordinates['lat'] ?? null;
            $lng = $coordinates['lng'] ?? null;
        } else {
            $lat = null;
            $lng = null;
        }
        
        // Если координат нет, но есть регион - преобразуем регион в координаты
        if (($lat === null || $lng === null) && $region) {
            try {
                $phoneLocatorService = app(\App\Services\PhoneLocatorService::class);
                $regionCoords = $phoneLocatorService->getRegionCoordinates($region);
                if ($regionCoords && isset($regionCoords['lat']) && isset($regionCoords['lng'])) {
                    $lat = $regionCoords['lat'];
                    $lng = $regionCoords['lng'];
                    $coordinates = ['lat' => $lat, 'lng' => $lng, 'city' => $regionCoords['city'] ?? $region];
                }
            } catch (\Exception $e) {
                // Если не удалось получить координаты, оставляем null
            }
        }
        
        // Для отображения метаданных используем phoneMetadata напрямую
        $phoneValid = $phoneMetadata['phone_valid'] ?? null;
        $phoneCountry = $phoneMetadata['country'] ?? null;
        $phoneOperator = $phoneMetadata['operator_name'] ?? null;
        $phoneType = $phoneMetadata['phone_type'] ?? null;
        $phoneRegion = $phoneMetadata['region'] ?? null;
        $phoneFormat = $phoneMetadata['international_format'] ?? $check->phone ?? null;
        
        // Флаг для отображения карты
        $hasCoordinates = ($lat !== null && $lng !== null);
    @endphp
    
    <!-- Карта Казахстана -->
    @if($hasCoordinates)
    <div class="info-card" style="margin-bottom: 1.5rem;">
        <div class="info-card-header">
            <div class="info-card-title">📍 Местоположение номера на карте</div>
            <div class="info-card-source">
                @if($coordinates && isset($coordinates['city']) && $coordinates['city'] !== $region)
                    Примерное расположение по региону оператора
                @else
                    Карта Казахстана
                @endif
            </div>
        </div>
        <div id="phoneLocationMap" style="width: 100%; height: 400px; border-radius: 8px; overflow: hidden; background: var(--bg-secondary); min-height: 400px;"></div>
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                <strong>Регион:</strong> {{ $region ?? 'Не определен' }}<br>
                <strong>Город:</strong> {{ $city ?? 'Не определен' }}<br>
                @if($operator)
                <strong>Оператор:</strong> {{ $operator }}
                @endif
            </div>
        </div>
    </div>
    @else
    <div class="info-card" style="margin-bottom: 1.5rem; background: rgba(115, 115, 115, 0.05);">
        <div class="info-card-header">
            <div class="info-card-title">📍 Местоположение на карте</div>
            <div class="info-card-source">Недоступно</div>
        </div>
        <div style="padding: 2rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 1rem;">🗺️</div>
            <div style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-main);">
                Координаты недоступны
            </div>
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                Не удалось определить координаты для региона "{{ $region ?? 'Не определен' }}"
            </div>
            @if($operator)
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.875rem; color: var(--text-muted);">
                <strong>Оператор:</strong> {{ $operator }}
            </div>
            @endif
        </div>
    </div>
    @endif
    
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">Метаданные номера</div>
            <div class="info-card-source">Источник: PhoneLocatorService</div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Валидность</div>
                <div class="info-value">
                    @if($phoneValid !== null)
                        {{ $phoneValid ? 'Да' : 'Нет' }}
                    @else
                        <span class="undefined">не определено</span>
                    @endif
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Страна</div>
                <div class="info-value">
                    @if(!empty($phoneCountry))
                        {{ $phoneCountry }}
                    @else
                        <span class="undefined">не определено</span>
                    @endif
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Оператор</div>
                <div class="info-value">
                    @if(!empty($phoneOperator) && $phoneOperator !== 'Unknown Operator')
                        {{ $phoneOperator }}
                    @else
                        <span class="undefined">не определено</span>
                    @endif
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Тип номера</div>
                <div class="info-value">
                    @if(!empty($phoneType))
                        @php
                            $lineType = strtolower(trim($phoneType));
                            $typeMap = [
                                'mobile' => 'Мобильный',
                                'voip' => 'VoIP',
                                'landline' => 'Городской',
                                'fixed' => 'Стационарный'
                            ];
                            $displayType = $typeMap[$lineType] ?? ucfirst($lineType);
                        @endphp
                        {{ $displayType }}
                    @else
                        <span class="undefined">не определено</span>
                    @endif
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Регион</div>
                <div class="info-value">
                    @if(!empty($phoneRegion))
                        {{ $phoneRegion }}
                    @else
                        <span class="undefined">не определено</span>
                    @endif
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Формат E.164</div>
                <div class="info-value">
                    {{ $phoneFormat ?? $check->phone ?? 'N/A' }}
                </div>
            </div>
        </div>
        
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <div class="info-label" style="margin-bottom: 0.5rem;">Доверие</div>
            @php
                $hasValidData = isset($rawPhoneInfoga['valid']) || !empty($rawPhoneInfoga['country']) || !empty($rawPhoneInfoga['carrier']);
                $trustLevel = 'low';
                if ($hasValidData && !empty($rawPhoneInfoga['country']) && !empty($rawPhoneInfoga['carrier'])) {
                    $trustLevel = 'high';
                } elseif ($hasValidData) {
                    $trustLevel = 'medium';
                }
            @endphp
            <span class="badge-trust {{ $trustLevel }}">
                {{ $trustLevel === 'high' ? 'Высокое' : ($trustLevel === 'medium' ? 'Среднее' : 'Низкое') }}
            </span>
        </div>
    </div>

    <!-- Заголовок секции OSINT -->
    <div style="margin-top: 3rem; margin-bottom: 1rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); border-bottom: 2px solid var(--info); padding-bottom: 0.5rem; display: inline-block;">
            🔍 OSINT данные
        </h2>
    </div>
    
    <!-- OSINT данные -->
    <div>
        @if(empty($osintTools))
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <div style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">OSINT инструменты не запускались</div>
            <div style="font-size: 0.875rem; color: var(--text-dim);">
                Результаты OSINT поиска будут отображаться здесь
            </div>
        </div>
        @else
        @foreach($osintTools as $tool)
    <div class="osint-tool">
        <div class="osint-tool-header">
            <div style="font-weight: 700; text-transform: uppercase; font-family: var(--font-mono);">
                {{ $tool['name'] }}
            </div>
            <span class="osint-status {{ $tool['status'] }}">
                @if($tool['status'] === 'ran')
                    Запускался
                @elseif($tool['status'] === 'error')
                    Ошибка
                @else
                    Не запускался
                @endif
            </span>
        </div>
        
        <div style="margin-bottom: 0.75rem;">
            <div class="info-label" style="margin-bottom: 0.25rem;">Результат</div>
            <div class="osint-result">
                @if($tool['result'] === 'found')
                    Найдено
                @elseif($tool['result'] === 'not-found')
                    Не найдено
                @else
                    <span class="undefined">не определено</span>
                @endif
            </div>
        </div>
        
        <div>
            <div class="info-label" style="margin-bottom: 0.25rem;">Комментарий</div>
            <div class="osint-result">{{ $tool['comment'] }}</div>
        </div>
        
        @if(!empty($tool['links']) && count($tool['links']) > 0)
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <div class="info-label" style="margin-bottom: 0.5rem;">
                @if($tool['name'] === 'Sherlock')
                    Найденные аккаунты ({{ count($tool['accounts'] ?? []) }})
                @else
                    Ссылки для проверки ({{ count($tool['links']) }})
                @endif
            </div>
            <div style="max-height: 300px; overflow-y: auto;">
                @if($tool['name'] === 'Sherlock' && !empty($tool['accounts']))
                    @foreach(array_slice($tool['accounts'], 0, 20) as $account)
                    <div style="padding: 0.75rem; background: rgba(255,255,255,0.02); border-radius: 2px; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem;">
                                {{ $account['site'] ?? 'Unknown' }}
                            </div>
                            <a href="{{ $account['url'] ?? '#' }}" target="_blank" style="color: var(--info); text-decoration: none; word-break: break-all; font-size: 0.8rem; font-family: var(--font-mono);">
                                {{ $account['url'] ?? '' }}
                            </a>
                        </div>
                    </div>
                    @endforeach
                    @if(count($tool['accounts']) > 20)
                    <div style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.5rem; text-align: center;">
                        ... и еще {{ count($tool['accounts']) - 20 }} аккаунтов
                    </div>
                    @endif
                @else
                    @foreach(array_slice($tool['links'], 0, 10) as $link)
                    <div style="padding: 0.5rem; background: rgba(255,255,255,0.02); border-radius: 2px; margin-bottom: 0.25rem; font-size: 0.8rem; font-family: var(--font-mono);">
                        <a href="{{ $link }}" target="_blank" style="color: var(--info); text-decoration: none; word-break: break-all;">
                            {{ $link }}
                        </a>
                    </div>
                    @endforeach
                    @if(count($tool['links']) > 10)
                    <div style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.5rem;">
                        ... и еще {{ count($tool['links']) - 10 }} ссылок
                    </div>
                        @endif
                    @endif
                </div>
            </div>
            @endif
            
            @if($tool['name'] === 'OpenSearch' && isset($tool['details']) && count($tool['details']) > 0)
            <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(34, 197, 94, 0.05); border-left: 3px solid var(--success); border-radius: 2px;">
                <div style="font-size: 0.875rem; margin-bottom: 0.5rem;">
                    <strong>Детали найденных записей:</strong>
                </div>
                <div style="max-height: 200px; overflow-y: auto;">
                    @foreach($tool['details'] as $detail)
                    <div style="padding: 0.25rem 0; font-size: 0.8rem; font-family: var(--font-mono); color: var(--text-muted);">
                        • {{ $detail }}
                    </div>
                    @endforeach
                </div>
                @if(isset($tool['has_location']) && $tool['has_location'])
                <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border); font-size: 0.75rem; color: var(--success);">
                    ✅ Поле location (geo_point) доступно для визуализации на карте
                </div>
                @else
                <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border); font-size: 0.75rem; color: var(--text-muted);">
                    ⚠️ Поле location не сохранено в индексе (геоцентр региона/города недоступен)
                </div>
                @endif
            </div>
            @endif
            
            @if($tool['name'] === 'Truecaller' && isset($tool['name']) && $tool['name'])
            <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(34, 197, 94, 0.05); border-left: 3px solid var(--success); border-radius: 2px;">
                <div style="font-size: 0.875rem;">
                    <strong>Имя:</strong> {{ $tool['name'] }}<br>
                    @if(isset($tool['email']) && $tool['email'])
                    <strong>Email:</strong> {{ $tool['email'] }}<br>
                    @endif
                    @if(isset($tool['location']) && $tool['location'])
                    <strong>Местоположение:</strong> {{ $tool['location'] }}
                    @endif
                </div>
            </div>
            @endif
            
            @if($tool['name'] === 'Mr.Holmes' && isset($tool['sources']) && count($tool['sources']) > 0)
            <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(38, 189, 248, 0.05); border-left: 3px solid var(--info); border-radius: 2px;">
                <div style="font-size: 0.875rem; margin-bottom: 0.5rem;">
                    <strong>Источники:</strong> {{ implode(', ', $tool['sources']) }}
                </div>
                @if(isset($tool['emails']) && count($tool['emails']) > 0)
                <div style="font-size: 0.875rem;">
                    <strong>Emails:</strong> {{ implode(', ', $tool['emails']) }}
                </div>
                @endif
            </div>
            @endif
            
            @if($tool['name'] === 'Neo4j' && isset($tool['related_phones']) && count($tool['related_phones']) > 0)
            <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(139, 92, 246, 0.05); border-left: 3px solid #8b5cf6; border-radius: 2px;">
                <div style="font-size: 0.875rem; margin-bottom: 0.5rem;">
                    <strong>Связанные номера:</strong>
                </div>
                <div style="max-height: 150px; overflow-y: auto;">
                    @foreach(array_slice($tool['related_phones'], 0, 5) as $related)
                    <div style="padding: 0.25rem 0; font-size: 0.8rem; font-family: var(--font-mono);">
                        {{ $related['phone'] ?? 'N/A' }}
                        @if(isset($related['operator']) && $related['operator'])
                        <span style="color: var(--text-muted);">({{ $related['operator'] }})</span>
                        @endif
                    </div>
                    @endforeach
                    @if(count($tool['related_phones']) > 5)
                    <div style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.5rem;">
                        ... и еще {{ count($tool['related_phones']) - 5 }} номеров
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
        @endforeach
        @endif
    </div>

    <!-- Пояснение риска -->
    <div style="margin-top: 2rem;">
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">Объяснение риска</div>
        </div>
        
        @if($rawPhoneInfoga)
            @php
                $isValid = $rawPhoneInfoga['valid'] ?? null;
                $country = $rawPhoneInfoga['country'] ?? null;
                $lineType = $rawPhoneInfoga['line_type'] ?? null;
                $hasOsintLinks = !empty($osintLinks);
            @endphp
            
            <div class="explanation-item">
                <div class="explanation-icon">
                    @if($isValid === true)
                        ✔
                    @elseif($isValid === false)
                        ❌
                    @else
                        ⚠
                    @endif
                </div>
                <div>
                    <strong>Номер валиден:</strong> 
                    @if($isValid === true)
                        Да
                    @elseif($isValid === false)
                        Нет
                    @else
                        не определено
                    @endif
                </div>
            </div>
            
            <div class="explanation-item">
                <div class="explanation-icon">
                    @if($country === 'Kazakhstan' || $country === 'Казахстан')
                        ✔
                    @elseif($country)
                        ⚠
                    @else
                        ⚠
                    @endif
                </div>
                <div>
                    <strong>Страна:</strong> 
                    @if($country === 'Kazakhstan' || $country === 'Казахстан')
                        Совпадает (Казахстан)
                    @elseif($country)
                        {{ $country }} (иностранный номер)
                    @else
                        не определена
                    @endif
                </div>
            </div>
            
            <div class="explanation-item">
                <div class="explanation-icon">
                    @if(empty($activityHistory))
                        ⚠
                    @else
                        ℹ
                    @endif
                </div>
                <div>
                    <strong>История активности:</strong> 
                    {{ $activityHistory ? 'присутствует' : 'отсутствует' }}
                </div>
            </div>
            
            <div class="explanation-item">
                <div class="explanation-icon">
                    @if($sherlockData && !empty($sherlockData['accounts']))
                        ℹ
                    @else
                        ℹ
                    @endif
                </div>
                <div>
                    <strong>Соцсети (Sherlock):</strong> 
                    @php
                        $sherlockAccounts = $sherlockData['accounts'] ?? [];
                    @endphp
                    @if(count($sherlockAccounts) > 0)
                        найдено {{ count($sherlockAccounts) }} аккаунтов
                    @else
                        аккаунты не найдены
                    @endif
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; padding: 1rem; background: rgba(34, 197, 94, 0.05); border-left: 3px solid var(--success); border-radius: 2px;">
                <div style="font-weight: 700; margin-bottom: 0.5rem;">➡️ Итог:</div>
                <div>{{ $riskLabel }} по доступным данным</div>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">⚠️</div>
                <div style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Недостаточно данных</div>
                <div style="font-size: 0.875rem; color: var(--text-dim);">
                    Данные для анализа отсутствуют
                </div>
            </div>
        @endif
    </div>
    
    <!-- Заголовок секции Ограничения -->
    <div style="margin-top: 3rem; margin-bottom: 1rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); border-bottom: 2px solid var(--text-muted); padding-bottom: 0.5rem; display: inline-block;">
            ⚠️ Ограничения проверки
        </h2>
    </div>
    
    <!-- Ограничения проверки -->
    <div>
    <div class="info-card" style="background: rgba(115, 115, 115, 0.05);">
        <div class="info-card-header">
            <div class="info-card-title">Ограничения проверки</div>
        </div>
        <div style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.6;">
            <div style="margin-bottom: 0.5rem;">• <strong>Соцсети:</strong> не проверялись</div>
            <div style="margin-bottom: 0.5rem;">• <strong>Маркетплейсы:</strong> нет доступа</div>
            <div style="margin-bottom: 0.5rem;">• <strong>История:</strong> только внутренняя БД</div>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); color: var(--text-main);">
                Это плюс для госорганов, не минус.
            </div>
        </div>
    </div>
    </div>
    </div>
</div>

<!-- 💾 Экспорт -->
<div id="tab-export" class="tab-content">
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">Экспорт результатов</div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <button onclick="exportJSON()" class="btn-outline" style="width: 100%; padding: 1rem; cursor: pointer;">
                📄 JSON
            </button>
            <button onclick="exportCSV()" class="btn-outline" style="width: 100%; padding: 1rem; cursor: pointer;">
                📊 CSV
            </button>
            <button onclick="exportPDF()" class="btn-outline" style="width: 100%; padding: 1rem; cursor: pointer;">
                📑 PDF
            </button>
        </div>
    </div>
</div>

<script>
var phoneLocationMap = null;
var phoneLocationMarker = null;

function showTab(tabName) {
    // Скрыть все вкладки
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Показать выбранную вкладку
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
    
    // Инициализировать карту при переключении на вкладку "Результаты"
    if (tabName === 'results' && document.getElementById('phoneLocationMap') && !phoneLocationMap) {
        setTimeout(initPhoneLocationMap, 200);
    }
}

function initPhoneLocationMap() {
    @if($hasCoordinates)
    @php
        $phoneNumber = addslashes($check->phone ?? 'Номер');
        $cityName = addslashes($city ?? $region ?? 'Местоположение');
        $operatorName = $operator ? addslashes($operator) : null;
    @endphp
    
    if (typeof L === 'undefined') {
        console.error('Leaflet не загружен');
        return;
    }
    
    // Инициализация карты Казахстана
    var lat = {{ $lat }};
    var lng = {{ $lng }};
    phoneLocationMap = L.map('phoneLocationMap').setView([lat, lng], 6);
    
    // Добавление тайлов OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(phoneLocationMap);
    
    // Добавление маркера местоположения
    phoneLocationMarker = L.marker([lat, lng]).addTo(phoneLocationMap);
    
    // Всплывающее окно с информацией
    var popupContent = '<div style="padding: 0.5rem; color: #000;">' +
        '<strong>{{ $phoneNumber }}</strong><br>' +
        '<span style="color: #666; font-size: 0.875rem;">{{ $cityName }}</span>' +
        @if($operatorName)
        '<br><span style="color: #666; font-size: 0.875rem;">Оператор: {{ $operatorName }}</span>' +
        @endif
        '</div>';
    
    phoneLocationMarker.bindPopup(popupContent).openPopup();
    
    // Добавление границ Казахстана (упрощенная прямоугольная область)
    var kazakhstanBounds = L.latLngBounds(
        [40.0, 46.0], // Юго-запад
        [55.0, 87.0]  // Северо-восток
    );
    
    // Ограничение карты границами Казахстана
    phoneLocationMap.setMaxBounds(kazakhstanBounds);
    phoneLocationMap.on('drag', function() {
        phoneLocationMap.panInsideBounds(kazakhstanBounds, { animate: false });
    });
    
    // Вызвать invalidateSize после небольшой задержки для корректного отображения
    setTimeout(function() {
        if (phoneLocationMap) {
            phoneLocationMap.invalidateSize();
        }
    }, 100);
    @endif
}

// Инициализировать карту при загрузке страницы, если вкладка "Результаты" активна
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('tab-results') && document.getElementById('tab-results').classList.contains('active')) {
        setTimeout(initPhoneLocationMap, 300);
    }
});

function exportJSON() {
    const data = {
        phone: '{{ $check->phone }}',
        risk_score: {{ $risk }},
        verdict: '{{ $verdict }}',
        risk_level: '{{ $riskLevel }}',
        sources: {!! json_encode($sources) !!},
        phone_metadata: {!! json_encode($rawPhoneInfoga) !!},
        technical_metadata: {!! json_encode($phoneMetadata) !!},
        osint_tools: {!! json_encode($osintTools) !!},
        timestamp: '{{ $check->created_at }}'
    };
    
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'antifraud-check-{{ $check->id }}.json';
    a.click();
    URL.revokeObjectURL(url);
}

function exportCSV() {
    alert('Экспорт CSV будет реализован');
}

function exportPDF() {
    alert('Экспорт PDF будет реализован');
}
</script>

@endsection
