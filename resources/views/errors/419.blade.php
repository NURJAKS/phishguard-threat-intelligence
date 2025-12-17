@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 700px; margin: 0 auto; text-align: center; padding: 3rem 2rem;">
    <h1 style="font-size: 2rem; margin-bottom: 1rem;">419 | Страница устарела</h1>

    <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
        Срок действия формы истёк или страница была повторно отправлена.
        Это нормальное поведение защиты от CSRF в Laravel (анти‑подделка запросов).
    </p>

    <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
        Для продолжения:
    </p>

    <ul style="list-style: none; padding: 0; margin: 0 0 2rem 0; color: var(--text-muted); font-size: 0.9rem;">
        <li>1. Нажмите кнопку ниже, чтобы вернуться к форме проверки ссылок.</li>
        <li>2. Введите ссылку заново и повторите проверку.</li>
    </ul>

    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <a href="{{ route('check.link') }}" class="btn" style="padding: 0.75rem 1.5rem; background: var(--success); color: #fff; text-decoration: none; border-radius: 4px; font-weight: 600;">
            ⤺ Вернуться к проверке ссылок
        </a>
        <a href="{{ route('home') }}" class="btn" style="padding: 0.75rem 1.5rem; background: var(--bg-card); color: var(--text-main); text-decoration: none; border-radius: 4px; border: 1px solid var(--border); font-weight: 600;">
            ⌂ На главную
        </a>
    </div>

    <div style="margin-top: 2rem; font-size: 0.8rem; color: var(--text-muted);">
        Если ошибка повторяется сразу после отправки формы, проверьте:
        включены ли cookies и не очищается ли сессия (PHP/Laravel session storage).
    </div>
</div>
@endsection
