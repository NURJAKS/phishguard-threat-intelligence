@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h1>🔗 Проверка Объявления</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Вставьте ссылку или текст объявления для анализа на мошенничество.</p>

    <form action="{{ route('check.listing.perform') }}" method="POST" id="listingCheckForm">
        @csrf
        <div class="form-group">
            <label for="url">Ссылка на объявление</label>
            <input type="text" id="url" name="url" placeholder="https://krisha.kz/..." value="{{ old('url') }}" />
        </div>
        <div class="form-group">
            <label for="content">Или текст объявления</label>
            <textarea id="content" name="content" rows="6" style="width: 100%; background-color: var(--bg-input); border: 1px solid var(--border); color: var(--text-main); padding: 0.75rem; border-radius: var(--radius-md); font-family: inherit;">{{ old('content') }}</textarea>
        </div>
        <button type="submit" class="btn" id="submitBtn" style="width: 100%;">
            <span id="btnText">Анализировать</span>
            <span id="btnLoading" style="display: none;">Обработка...</span>
        </button>
    </form>
    
    @if(session('error'))
        <div class="alert alert-error" style="margin-top: 1rem;">
            {{ session('error') }}
        </div>
    @endif

    <script>
        document.getElementById('listingCheckForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            
            // Проверка что хотя бы одно поле заполнено
            const url = document.getElementById('url').value.trim();
            const content = document.getElementById('content').value.trim();
            
            if (!url && !content) {
                e.preventDefault();
                alert('Введите ссылку на объявление или текст объявления');
                return false;
            }
            
            // Показываем индикатор загрузки
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
        });
    </script>
</div>
@endsection
