@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto; text-align: center;">
    <h1 style="font-size: 2.5rem; margin-bottom: 2rem;">Анализ Номера</h1>

    @if (session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('check.perform') }}" method="POST">
        @csrf
        
        <div class="form-group">
            <label for="phone">Номер телефона</label>
            <input type="text" id="phone" name="phone" placeholder="+7 701 123 45 67" value="{{ old('phone') }}" required />
            <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Введите номер для проверки по базам мошенников и активности.</small>
        </div>
        
        <!-- Feature: Number Activity (Hidden, always active) -->
        <input type="hidden" name="check_activity" value="on">

        <button type="submit" class="btn" style="width: 100%; padding: 1rem; font-size: 1.125rem; margin-top: 1.5rem;">Запустить Анализ</button>
    </form>
</div>
@endsection
