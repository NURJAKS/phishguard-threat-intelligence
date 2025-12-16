@extends('layouts.app')

@section('content')
<div style="margin-bottom: 2rem;">
    <h1>📊 Аналитика и Отчеты</h1>
    <p style="color: var(--text-muted);">Статистика по выявленным угрозам</p>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <div class="card" style="height: 300px;">
        <h3>Типы Мошенничества</h3>
        <!-- Chart placeholder -->
        <div style="height: 200px; background: linear-gradient(45deg, #1e293b, #334155); border-radius: var(--radius-md); margin-top: 1rem;"></div>
    </div>
    <div class="card" style="height: 300px;">
        <h3>Активность по Регионам</h3>
        <!-- Map placeholder -->
        <div style="height: 200px; background: linear-gradient(45deg, #1e293b, #334155); border-radius: var(--radius-md); margin-top: 1rem;"></div>
    </div>
</div>
@endsection
