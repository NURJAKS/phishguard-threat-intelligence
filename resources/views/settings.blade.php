@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 800px; margin: 0 auto;">
    <h1>⚙️ Настройки и Источники</h1>
    
    <div style="margin-bottom: 2rem;">
        <h3>🔌 Подключенные API</h3>
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid var(--border);">
            <span>Kaspi.kz Checker</span>
            <span style="color: var(--success);">● Активен</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid var(--border);">
            <span>Google Vision API</span>
            <span style="color: var(--success);">● Активен</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem;">
            <span>AntiFraud ML Core</span>
            <span style="color: var(--warning);">● Обучение</span>
        </div>
    </div>

    <div>
        <h3>📜 Логи системы</h3>
        <div style="background-color: #0f172a; padding: 1rem; border-radius: var(--radius-md); font-family: monospace; font-size: 0.875rem; color: var(--text-muted);">
            [INFO] System started at 12:00:00<br>
            [INFO] Connected to Database<br>
            [WARN] API latency increased for Source #2
        </div>
    </div>
</div>
@endsection
