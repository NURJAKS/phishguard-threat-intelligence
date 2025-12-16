@extends('layouts.app')

@push('styles')
<style>
    .tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }
    .tab {
        padding: 0.6rem 1.4rem;
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        color: var(--text-muted);
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.2s;
        font-family: var(--font-mono);
    }
    .tab:hover { color: var(--text-main); background: rgba(255,255,255,0.03); }
    .tab.active { color: var(--success); border-bottom-color: var(--success); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .summary-bar {
        position: sticky;
        top: 64px;
        z-index: 10;
        background: var(--bg-main);
        padding-bottom: 1rem;
        margin-bottom: 1.5rem;
    }
    .summary-box {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-left: 4px solid;
        border-radius: var(--radius-lg);
        padding: 1.1rem 1.4rem;
        display: grid;
        grid-template-columns: 1.6fr 1.2fr 1.2fr 1fr;
        gap: 1rem;
        align-items: center;
    }
    .summary-box.safe { border-left-color: var(--success); }
    .summary-box.suspicious { border-left-color: var(--warning); }
    .summary-box.high_risk { border-left-color: var(--danger); }

    .risk-level {
        font-size: 1.2rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 0.25rem;
        font-family: var(--font-mono);
    }
    .risk-level.safe { color: var(--success); }
    .risk-level.suspicious { color: var(--warning); }
    .risk-level.high_risk { color: var(--danger); }

    .badge-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        font-family: var(--font-mono);
    }
    .badge-pill.green { background: rgba(34,197,94,0.12); color: var(--success); }
    .badge-pill.yellow { background: rgba(250,204,21,0.12); color: var(--warning); }
    .badge-pill.gray { background: rgba(148,163,184,0.18); color: var(--text-muted); }

    .progress-bar { width: 100%; height: 6px; border-radius: 999px; background: rgba(255,255,255,0.05); overflow: hidden; }
    .progress-bar-inner { height: 100%; border-radius: 999px; background: linear-gradient(90deg,#22c55e,#facc15,#ef4444); }

    .info-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.4rem;
        margin-bottom: 1.4rem;
    }
    .info-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.9rem;
        padding-bottom: 0.7rem;
        border-bottom: 1px solid var(--border);
    }
    .info-card-title {
        font-size: 0.95rem;
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
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
        gap: 1rem;
    }
    .info-item { display: flex; flex-direction: column; gap: 0.25rem; }
    .info-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    .info-value {
        font-size: 0.93rem;
        color: var(--text-main);
        font-family: var(--font-mono);
        word-break: break-all;
    }
    .timeline {
        position: relative;
        margin-left: 1rem;
        padding-left: 1rem;
        border-left: 1px solid var(--border);
    }
    .timeline-item {
        margin-bottom: 1rem;
        position: relative;
    }
    .timeline-dot {
        position: absolute;
        left: -0.58rem;
        top: 0.15rem;
        width: 0.7rem;
        height: 0.7rem;
        border-radius: 999px;
        background: var(--success);
        box-shadow: 0 0 0 3px rgba(34,197,94,0.25);
    }
    .json-block {
        font-family: var(--font-mono);
        font-size: 0.8rem;
        background: #020617;
        padding: 1rem;
        border-radius: 0.5rem;
        overflow: auto;
        max-height: 380px;
        border: 1px solid var(--border);
    }
</style>
@endpush

@section('content')
@php
    /** @var \App\Models\Check $check */
    $imageResult = $imageResult ?? ($check->result ?? []);
    $verdict = $imageResult['verdict'] ?? [];
    $level = $verdict['level'] ?? 'safe';
    $riskScore = (int)($verdict['risk_score'] ?? 0);
    $explanation = $verdict['explanation'] ?? [];

    $fingerprint = $imageResult['image_fingerprint_profile'] ?? [];
    $hashes = $fingerprint['hashes'] ?? [];

    $internal = $imageResult['internal_db_matches'] ?? [];
    $reverse = $imageResult['reverse_image_osint'] ?? [];
    $technical = $imageResult['technical_metadata'] ?? [];
    $files = $technical['files'] ?? [];
    $firstFile = $files[0] ?? null;
    $matchesCount = $internal['matches_count'] ?? 0;
    $timeline = $imageResult['timeline_analysis']['events'] ?? [];
    $serp = $reverse['serpapi'] ?? null;
@endphp

<div class="card" style="max-width: 1100px; margin: 0 auto;">
    <h1 style="margin-bottom: 0.75rem;">🖼 Результаты проверки изображений</h1>

    <div class="summary-bar">
        <div class="summary-box {{ $level }}">
            <div>
                <div class="risk-level {{ $level }}">
                    @if($level === 'high_risk')
                        Высокий риск изображения
                    @elseif($level === 'suspicious')
                        Средний риск изображения
                    @else
                        Низкий риск изображения
                    @endif
                </div>
                <div style="font-size: 0.83rem; color: var(--text-muted);">
                    @if(!empty($explanation))
                        {{ $explanation[0] }}
                    @else
                        Оценка построена только на внутренних данных и техническом анализе.
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.25rem;">Risk score</div>
                <div class="progress-bar" style="margin-bottom: 0.25rem;">
                    <div class="progress-bar-inner" style="width: {{ max(0, min(100, $riskScore)) }}%;"></div>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted);">{{ $riskScore }}/100</div>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.25rem;">Уникальность</div>
                @if($matchesCount === 0)
                    <span class="badge-pill green">Уникальное изображение</span>
                @else
                    <span class="badge-pill yellow">Повторяется ({{ $matchesCount }})</span>
                @endif
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.25rem;">Внешний OSINT</div>
                @if(!$serp || !$serp['ran'])
                    <span class="badge-pill gray">SerpAPI: not ran</span>
                @elseif(!empty($serp['error']))
                    <span class="badge-pill yellow">SerpAPI: error</span>
                @else
                    <span class="badge-pill green">SerpAPI: {{ $serp['results_count'] ?? 0 }} результатов</span>
                @endif
            </div>
        </div>
    </div>

    <div class="tabs" id="imageTabs">
        <button class="tab active" onclick="showImageTab('overview')">📊 Overview</button>
        <button class="tab" onclick="showImageTab('fingerprint')">🧠 Image Fingerprint Profile</button>
        <button class="tab" onclick="showImageTab('reuse')">🔁 Image Reuse Intelligence</button>
        <button class="tab" onclick="showImageTab('osint')">🔍 Reverse Image OSINT</button>
        <button class="tab" onclick="showImageTab('timeline')">🕒 Timeline Analysis</button>
        <button class="tab" onclick="showImageTab('technical')">💾 Technical Details</button>
    </div>

    {{-- OVERVIEW TAB --}}
    <div id="tab-overview" class="tab-content active">
        <div class="info-card" style="display: grid; grid-template-columns: 260px 1fr; gap: 1.5rem; align-items: flex-start;">
            <div>
                @if($firstFile && str_starts_with($firstFile['mime'] ?? '', 'image/'))
                    <div style="border-radius: 0.5rem; overflow: hidden; border: 1px solid var(--border); background: #020617;">
                        <img src="{{ $firstFile['url'] }}" alt="Preview" style="display:block; width:100%; height:auto;">
                    </div>
                @else
                    <div style="border-radius: 0.5rem; border: 1px dashed var(--border); padding: 2rem 1rem; text-align:center; color: var(--text-muted);">
                        Превью недоступно
                    </div>
                @endif
            </div>
            <div>
                <div class="info-card-header" style="padding-bottom: 0.5rem; margin-bottom: 0.7rem;">
                    <div class="info-card-title">Краткий отчёт для аналитика</div>
                    <div class="info-card-source">Overview</div>
                </div>
                <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem; color: var(--text-main);">
                    <li style="margin-bottom: 0.4rem;">• Уровень риска: <strong>{{ $riskScore }}/100</strong>, классификация —
                        @if($level === 'high_risk')
                            высокий риск.
                        @elseif($level === 'suspicious')
                            средний риск.
                        @else
                            низкий риск.
                        @endif
                    </li>
                    <li style="margin-bottom: 0.4rem;">• Внутренняя БД: совпадений по этому изображению — <strong>{{ $matchesCount }}</strong>.</li>
                    <li>• Внешний reverse-image поиск (SerpAPI):
                        @if(!$serp || !$serp['ran'])
                            автоматический запрос не выполнялся;
                        @elseif(!empty($serp['error']))
                            при выполнении запроса возникла ошибка (подробности в логах);
                        @else
                            выполнен, получено {{ $serp['results_count'] ?? 0 }} результатов без автоматической интерпретации.
                        @endif
                        решение о связи с мошенничеством принимает аналитик.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- IMAGE FINGERPRINT PROFILE TAB --}}
    <div id="tab-fingerprint" class="tab-content">
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">Image Fingerprint Profile</div>
                <div class="info-card-source">OpenCV + imagehash</div>
            </div>
            <div class="info-grid">
                @if($firstFile)
                    <div class="info-item">
                        <div class="info-label">Имя файла</div>
                        <div class="info-value">{{ $firstFile['original_name'] }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Размер / формат</div>
                        <div class="info-value">{{ $firstFile['size_kb'] ?? 'N/A' }} KB · {{ $firstFile['mime'] ?? 'N/A' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Разрешение</div>
                        <div class="info-value">
                            @if(!empty($firstFile['width']) && !empty($firstFile['height']))
                                {{ $firstFile['width'] }} × {{ $firstFile['height'] }}
                            @else
                                N/A
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div style="margin-top: 1.2rem;" class="info-grid">
                <div class="info-item" title="Average hash — чувствителен к общей яркости и структуре изображения.">
                    <div class="info-label">aHash</div>
                    <div class="info-value">{{ $hashes['ahash'] ?? '—' }}</div>
                </div>
                <div class="info-item" title="Perceptual hash — устойчив к мелким изменениям и сжатию.">
                    <div class="info-label">pHash</div>
                    <div class="info-value">{{ $hashes['phash'] ?? '—' }}</div>
                </div>
                <div class="info-item" title="Difference hash — сравнивает соседние пиксели по яркости.">
                    <div class="info-label">dHash</div>
                    <div class="info-value">{{ $hashes['dhash'] ?? '—' }}</div>
                </div>
                <div class="info-item" title="Wavelet hash — учитывает частоты и детали.">
                    <div class="info-label">wHash</div>
                    <div class="info-value">{{ $hashes['whash'] ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Similarity score (internal)</div>
                    <div class="info-value">{{ $fingerprint['similarity_score_max'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- IMAGE REUSE INTELLIGENCE TAB --}}
    <div id="tab-reuse" class="tab-content">
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">Image Reuse Intelligence</div>
                <div class="info-card-source">Internal DB</div>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Совпадений во внутренней БД</div>
                    <div class="info-value">{{ $matchesCount }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Первое использование</div>
                    <div class="info-value">{{ $internal['first_seen'] ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Последнее использование</div>
                    <div class="info-value">{{ $internal['last_seen'] ?? '—' }}</div>
                </div>
            </div>

            @php $relatedChecks = $internal['related_checks'] ?? []; @endphp
            @if($matchesCount === 0)
                <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-muted);">
                    На момент проверки совпадений этого изображения во внутренней базе не обнаружено. Статус: уникальное изображение в рамках платформы.
                </div>
            @else
                <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-muted);">
                    Изображение ранее встречалось в других проверках. Ниже приведён список связанных проверок (ID и дата).
                </div>
                <div style="margin-top: 0.8rem; max-height: 220px; overflow-y: auto;">
                    @foreach($relatedChecks as $rel)
                        <div style="padding: 0.45rem 0.6rem; background: rgba(255,255,255,0.02); border-radius: 4px; margin-bottom: 0.4rem; font-size: 0.82rem; font-family: var(--font-mono);">
                            Check #{{ $rel['check_id'] ?? 'N/A' }} · {{ $rel['created_at'] ?? '—' }} · {{ $rel['original_name'] ?? 'file' }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- REVERSE IMAGE OSINT TAB --}}
    <div id="tab-osint" class="tab-content">
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">Reverse Image OSINT</div>
                <div class="info-card-source">SerpAPI + внешние сервисы</div>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">SerpAPI</div>
                    <div class="info-value">
                        @if(!$serp || !$serp['ran'])
                            not_ran
                        @elseif(!empty($serp['error']))
                            error
                        @else
                            ran ({{ $serp['results_count'] ?? 0 }})
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Домены</div>
                    <div class="info-value">
                        @if(!empty($serp['domains']))
                            {{ implode(', ', $serp['domains']) }}
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
            <div style="margin-top: 0.9rem; font-size: 0.85rem; color: var(--text-muted);">
                Автоматический reverse-image поиск выполняется через SerpAPI только как вспомогательный источник. Платформа не делает выводов о мошенничестве, итоговое решение принимает аналитик.
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">Внешние сервисы</div>
                <div class="info-card-source">Google · Yandex · TinEye</div>
            </div>
            @if($firstFile)
                @php $encodedUrl = urlencode(url($firstFile['url'])); @endphp
                <div style="display:flex;flex-wrap:wrap;gap:0.6rem;">
                    <a href="https://www.google.com/searchbyimage?image_url={{ $encodedUrl }}" target="_blank" class="btn" style="padding:0.35rem 0.9rem; font-size:0.9rem;">🔍 Google Images</a>
                    <a href="https://yandex.com/images/search?rpt=imageview&url={{ $encodedUrl }}" target="_blank" class="btn" style="padding:0.35rem 0.9rem; font-size:0.9rem;">🔍 Yandex Images</a>
                    <a href="https://tineye.com/search?url={{ $encodedUrl }}" target="_blank" class="btn" style="padding:0.35rem 0.9rem; font-size:0.9rem;">🔍 TinEye</a>
                </div>
                <div style="margin-top:0.7rem;font-size:0.8rem;color:var(--text-muted);">
                    Кнопки открывают внешние сервисы reverse-image поиска. Результаты необходимо оценивать вручную; платформа их не сохраняет и не интерпретирует автоматически.
                </div>
            @else
                <div style="font-size:0.9rem;color:var(--text-muted);">Файл недоступен для формирования ссылок на внешний поиск.</div>
            @endif
        </div>
    </div>

    {{-- TIMELINE TAB --}}
    <div id="tab-timeline" class="tab-content">
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">Timeline Analysis</div>
                <div class="info-card-source">Internal DB</div>
            </div>
            @php
                $events = [];
                foreach($timeline as $ev){ $events[] = $ev; }
                // если таймлайн пустой, строим базовый из first_seen/last_seen и текущей проверки
                $firstSeen = $internal['first_seen'] ?? null;
                $lastSeen = $internal['last_seen'] ?? null;
                if(empty($events)){
                    if($firstSeen){ $events[] = ['timestamp' => $firstSeen, 'type' => 'first_seen']; }
                    if($lastSeen && $lastSeen !== $firstSeen){ $events[] = ['timestamp' => $lastSeen, 'type' => 'reused']; }
                    $events[] = ['timestamp' => $check->created_at ?? now(), 'type' => 'checked'];
                }
            @endphp
            <div class="timeline">
                @foreach($events as $ev)
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div style="margin-left:0.6rem;">
                            <div style="font-size:0.8rem;color:var(--text-muted);">{{ $ev['timestamp'] ?? '—' }}</div>
                            <div style="font-size:0.9rem;color:var(--text-main);">
                                @switch($ev['type'] ?? '')
                                    @case('first_seen') Первое появление изображения во внутренней БД. @break
                                    @case('reused') Повторное использование изображения зафиксировано. @break
                                    @case('checked') Текущая проверка изображения выполнена. @break
                                    @default Событие: {{ $ev['type'] ?? 'unknown' }}. @break
                                @endswitch
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- TECHNICAL DETAILS TAB --}}
    <div id="tab-technical" class="tab-content">
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">Technical Details</div>
                <div class="info-card-source">SHA256 · Resolution · Debug</div>
            </div>
            @if($firstFile)
                <div class="info-grid" style="margin-bottom:1rem;">
                    <div class="info-item">
                        <div class="info-label">SHA256</div>
                        <div class="info-value">{{ $firstFile['hash_sha256'] ?? 'N/A' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Размер / формат</div>
                        <div class="info-value">{{ $firstFile['size_kb'] ?? 'N/A' }} KB · {{ $firstFile['mime'] ?? 'N/A' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Разрешение</div>
                        <div class="info-value">
                            @if(!empty($firstFile['width']) && !empty($firstFile['height']))
                                {{ $firstFile['width'] }} × {{ $firstFile['height'] }}
                            @else
                                N/A
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <details>
                <summary style="cursor:pointer;color:var(--info);font-size:0.85rem;">Показать raw JSON результата проверки</summary>
                <pre class="json-block" style="margin-top:0.7rem;">{{ json_encode($imageResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        </div>
    </div>
</div>

<script>
function showImageTab(name) {
    document.querySelectorAll('#imageTabs .tab').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>
@endsection
