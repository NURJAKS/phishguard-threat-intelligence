@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 800px; margin: 0 auto;">
    <h1>🌐 Проверка Ссылки / Домена</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Проверьте фишинговые ссылки и репутацию домена через PhishGuard AI.</p>

    @if(session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('check.link.perform') }}">
        @csrf
        <div class="form-group">
            <label for="url">URL или Домен</label>
            <input
                type="text"
                id="url"
                name="url"
                placeholder="example.com или https://..."
                value="{{ old('url', $url ?? '') }}"
            />
        </div>
        <button type="submit" class="btn" style="width: 100%;">Проверить ссылку</button>
    </form>

    @if(isset($classification) || !empty($check))
        <div style="margin-top: 2rem;">
            <h2 style="margin-bottom: 0.75rem;">Результат проверки</h2>

            @if(($classification['verdict'] ?? 'unknown') === 'danger')
                <div class="badge badge-danger">ОПАСНО</div>
            @elseif(($classification['verdict'] ?? 'unknown') === 'warning')
                <div class="badge badge-warning">ПОДОЗРИТЕЛЬНО</div>
            @elseif(($classification['verdict'] ?? 'unknown') === 'safe')
                <div class="badge badge-success">БЕЗОПАСНО</div>
            @else
                <div class="badge badge-warning">НЕОПРЕДЕЛЁННО</div>
            @endif

            <p class="text-muted" style="margin-top: 0.75rem;">
                {{ $classification['label'] ?? 'Интерпретация недоступна' }}
            </p>

            <div class="form-group" style="margin-top: 1.5rem;">
                <label>📋 Краткая информация</label>
                <div style="background-color: var(--bg-input); padding: 1rem; border-radius: 2px; margin-top: 0.5rem;">
                    <p style="margin: 0.5rem 0;"><strong>Адрес сайта:</strong> <span class="font-mono" style="color: var(--accent);">{{ $url ?? '' }}</span></p>
                    @if(isset($classification['score']))
                        <p style="margin: 0.5rem 0;"><strong>Оценка риска:</strong> 
                            @if($classification['score'] >= 0.7)
                                <span style="color: var(--danger);">{{ number_format($classification['score'] * 100, 0) }}% (Высокий риск)</span>
                            @elseif($classification['score'] >= 0.4)
                                <span style="color: var(--warning);">{{ number_format($classification['score'] * 100, 0) }}% (Средний риск)</span>
                            @else
                                <span style="color: var(--success);">{{ number_format($classification['score'] * 100, 0) }}% (Низкий риск)</span>
                            @endif
                        </p>
                    @endif
                    @if(isset($fullAudit['phishing_probability']))
                        <p style="margin: 0.5rem 0;"><strong>Вероятность фишинга:</strong> 
                            @if($fullAudit['phishing_probability'] >= 70)
                                <span style="color: var(--danger);">{{ $fullAudit['phishing_probability'] }}%</span>
                            @elseif($fullAudit['phishing_probability'] >= 40)
                                <span style="color: var(--warning);">{{ $fullAudit['phishing_probability'] }}%</span>
                            @else
                                <span style="color: var(--success);">{{ $fullAudit['phishing_probability'] }}%</span>
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            @if(isset($fullAudit) && is_array($fullAudit))
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label>📊 Детальный анализ AI</label>
                    <div style="background-color: var(--bg-input); padding: 1rem; border-radius: 2px; margin-top: 0.5rem;">
                        @if(isset($fullAudit['error']))
                            <div style="padding: 0.75rem; background-color: rgba(239, 68, 68, 0.1); border-left: 3px solid var(--danger); border-radius: 2px; margin-bottom: 1rem;">
                                <p style="color: var(--danger); margin: 0; font-weight: 500;">⚠️ Ошибка при получении детального анализа:</p>
                                <p style="color: var(--text-muted); margin: 0.5rem 0 0 0; font-size: 0.875rem; line-height: 1.5;">{{ $fullAudit['error'] }}</p>
                                @if(strpos($fullAudit['error'], 'лимит') !== false || strpos($fullAudit['error'], 'quota') !== false)
                                    <div style="margin-top: 0.75rem; padding: 0.5rem; background-color: rgba(59, 130, 246, 0.1); border-radius: 2px;">
                                        <p style="margin: 0; font-size: 0.8rem; color: var(--info);">
                                            💡 <strong>Решение:</strong> Детальный AI-анализ временно недоступен из-за лимита API. 
                                            Базовая проверка (черные списки, ключевые слова) работает нормально. 
                                            Система автоматически переключится на Groq API (1000 запросов/день бесплатно) если он настроен в PhishGuard backend.
                                            Или подождите минуту и попробуйте снова.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if(!isset($fullAudit['error']))
                            @if(isset($fullAudit['verdict']) || isset($fullAudit['risk']))
                                <div style="margin-bottom: 1rem; padding: 0.75rem; background-color: rgba(59, 130, 246, 0.1); border-left: 3px solid var(--info); border-radius: 2px;">
                                    @if(isset($fullAudit['verdict']))
                                        <p style="margin: 0 0 0.25rem 0; font-weight: 500; color: var(--text-main);">
                                            <strong>Вердикт AI:</strong> 
                                            <span style="text-transform: capitalize;">{{ $fullAudit['verdict'] }}</span>
                                        </p>
                                    @endif
                                    @if(isset($fullAudit['risk']))
                                        <p style="margin: 0; font-size: 0.875rem; color: var(--text-muted);">
                                            <strong>Уровень риска:</strong> 
                                            <span style="text-transform: capitalize;">{{ $fullAudit['risk'] }}</span>
                                        </p>
                                    @endif
                                </div>
                            @endif
                        @if(isset($fullAudit['explanation']))
                            <p style="margin-bottom: 1rem; line-height: 1.6;">{{ $fullAudit['explanation'] }}</p>
                            @endif
                        @endif
                        
                        @if(!isset($fullAudit['error']) && isset($fullAudit['url_analysis']))
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-main);">🔗 Анализ адреса сайта</h4>
                                @if(isset($fullAudit['url_analysis']['masking']))
                                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">{{ $fullAudit['url_analysis']['masking'] }}</p>
                                @endif
                                @if(isset($fullAudit['url_analysis']['suspicious_chars']))
                                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">{{ $fullAudit['url_analysis']['suspicious_chars'] }}</p>
                                @endif
                            </div>
                        @endif

                        @if(!isset($fullAudit['error']) && isset($fullAudit['tls_certificate']))
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-main);">🔒 Защита соединения</h4>
                                @if(isset($fullAudit['tls_certificate']['explanation']))
                                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">{{ $fullAudit['tls_certificate']['explanation'] }}</p>
                                @endif
                                @if(isset($fullAudit['tls_certificate']['valid_until']) && $fullAudit['tls_certificate']['valid_until'] !== 'неизвестно')
                                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">Действителен до: {{ $fullAudit['tls_certificate']['valid_until'] }}</p>
                                @endif
                            </div>
                        @endif

                        @if(!isset($fullAudit['error']) && isset($fullAudit['domain_age']))
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-main);">📅 Возраст сайта</h4>
                                @if(isset($fullAudit['domain_age']['explanation']))
                                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">{{ $fullAudit['domain_age']['explanation'] }}</p>
                                @endif
                            </div>
                        @endif

                        @if(!isset($fullAudit['error']) && isset($fullAudit['html_js_structure']))
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-main);">🌐 Содержимое страницы</h4>
                                @if(isset($fullAudit['html_js_structure']['explanation']))
                                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">{{ $fullAudit['html_js_structure']['explanation'] }}</p>
                                @endif
                            </div>
                        @endif

                        @if(!isset($fullAudit['error']) && isset($fullAudit['content_analysis']))
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-main);">📝 Анализ контента</h4>
                                @if(isset($fullAudit['content_analysis']['explanation']))
                                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">{{ $fullAudit['content_analysis']['explanation'] }}</p>
                                @endif
                            </div>
                        @endif

                        @if(!isset($fullAudit['error']) && isset($fullAudit['dangerous_behavior']))
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-main);">⚠️ Подозрительное поведение</h4>
                                @if(isset($fullAudit['dangerous_behavior']['explanation']))
                                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0.25rem 0;">{{ $fullAudit['dangerous_behavior']['explanation'] }}</p>
                                @endif
                            </div>
                        @endif

                        @if(!isset($fullAudit['error']) && empty($fullAudit['explanation']) && empty($fullAudit['url_analysis']) && empty($fullAudit['tls_certificate']) && empty($fullAudit['domain_age']) && empty($fullAudit['html_js_structure']) && empty($fullAudit['content_analysis']) && empty($fullAudit['dangerous_behavior']))
                            <p style="color: var(--text-muted); font-size: 0.875rem;">Детальная информация недоступна. Проверьте логи для получения дополнительной информации.</p>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    @endif
</div>
@endsection
