@extends('layouts.app')

@section('content')
<div style="margin-bottom: 2rem;">
    <h1>Управление Платформой</h1>
    <p style="color: var(--text-muted);">Обзор активности и быстрый доступ к инструментам</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Card 1 -->
    <div class="card">
        <h3 style="color: var(--accent);">🔍 Проверок за сегодня</h3>
        <p style="font-size: 2.5rem; font-weight: 700; margin: 1rem 0;">128</p>
        <span style="color: var(--success);">+12% к вчерашнему дню</span>
    </div>

    <!-- Card 2 -->
    <div class="card">
        <h3 style="color: var(--warning);">⚠️ Высокий Риск</h3>
        <p style="font-size: 2.5rem; font-weight: 700; margin: 1rem 0;">14</p>
        <span style="color: var(--text-muted);">Требует внимания</span>
    </div>

    <!-- Card 3 -->
    <div class="card">
        <h3 style="color: var(--accent);">👥 Новых пользователей</h3>
        <p style="font-size: 2.5rem; font-weight: 700; margin: 1rem 0;">32</p>
        <span style="color: var(--text-muted);">Гос. органы и партнеры</span>
    </div>
</div>

<h2>Быстрый Доступ</h2>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
    <a href="{{ route('check.phone') }}" class="card" style="text-decoration: none; color: inherit; transition: transform 0.2s; display: block;">
        <div style="font-size: 2rem; margin-bottom: 0.5rem;">📞</div>
        <strong>Проверка номера</strong>
        <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.5rem;">Анализ репутации номера</p>
    </a>
    <a href="{{ route('check.listing') }}" class="card" style="text-decoration: none; color: inherit; transition: transform 0.2s; display: block;">
        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔗</div>
        <strong>Проверка объявления</strong>
        <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.5rem;">Анализ текста и ссылок</p>
    </a>
    <a href="{{ route('connections') }}" class="card" style="text-decoration: none; color: inherit; transition: transform 0.2s; display: block;">
        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔗</div>
        <strong>Связи</strong>
        <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.5rem;">Граф связей мошенников</p>
    </a>
</div>

<style>
    .card:hover {
        transform: translateY(-5px);
        border-color: var(--accent);
    }
</style>
@endsection
