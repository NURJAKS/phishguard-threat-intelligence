@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 800px; margin: 0 auto;">
    <h1>👤 Профиль Продавца</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Комплексный анализ профиля продавца на торговых площадках (OLX, Krisha, Kolesa и др.)</p>

    <form action="#" method="POST" style="margin-bottom: 2rem;">
        @csrf
        <div class="form-group">
            <label for="profile_url">Ссылка на профиль продавца</label>
            <input type="text" id="profile_url" name="profile_url" placeholder="https://olx.kz/list/user/..." />
        </div>
        <button type="button" class="btn" style="width: 100%;">Анализировать Профиль</button>
    </form>

    <div style="border-top: 1px solid var(--border); padding-top: 2rem; display: none;" id="profile-result">
        <h3>🔍 Результаты анализа (Пример)</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
            <div style="background-color: var(--bg-input); padding: 1rem; border-radius: var(--radius-md);">
                <span style="color: var(--text-muted); font-size: 0.8rem;">Дата регистрации</span>
                <div style="font-size: 1.1rem; font-weight: 600;">12.10.2023</div>
            </div>
            <div style="background-color: var(--bg-input); padding: 1rem; border-radius: var(--radius-md);">
                <span style="color: var(--text-muted); font-size: 0.8rem;">Активных объявлений</span>
                <div style="font-size: 1.1rem; font-weight: 600;">24</div>
            </div>
        </div>
    </div>
</div>
@endsection
