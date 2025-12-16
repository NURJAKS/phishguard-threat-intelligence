@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto; text-align: center;">
    <h1>🖼 Проверка Изображений</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Загрузите одно или несколько изображений/файлов для поиска дубликатов и базовой проверки.</p>

    @if(session('error'))
        <div class="alert alert-error" style="margin-bottom: 1rem;">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error" style="margin-bottom: 1rem;">
            {{ $errors->first() }}
        </div>
    @endif

    <form id="imageCheckForm" action="{{ route('check.image.perform') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <label for="images" style="display: block; border: 2px dashed var(--border); padding: 3rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; cursor: pointer;">
            <span style="font-size: 3rem;">📁</span>
            <p style="margin-top: 1rem;">Перетащите файлы сюда или нажмите для выбора</p>
            <input type="file" id="images" name="images[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" style="display: none;">
        </label>

    <div style="text-align: left; margin-bottom: 1.5rem; background-color: var(--bg-card); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
        <h3 style="font-size: 1rem; margin-bottom: 1rem;">🔍 Проверки</h3>
        <label style="display: flex; align-items: center; cursor: pointer; padding: 0.5rem; background: var(--bg-input); border-radius: 4px; margin-bottom: 0.5rem;">
            <input type="checkbox" name="check_photo_repetition" checked style="width: auto; margin-right: 0.5rem;">
            <span style="font-size: 0.85rem;">Искать дубликаты (внутренняя проверка по хешу)</span>
        </label>
        <label style="display: flex; align-items: center; cursor: pointer; padding: 0.5rem; background: var(--bg-input); border-radius: 4px;">
            <input type="checkbox" name="check_osint" checked style="width: auto; margin-right: 0.5rem;" disabled>
            <span style="font-size: 0.85rem;">Внешний reverse-image поиск (не настроен, нужно подключить API)</span>
        </label>
    </div>

    <button type="submit" class="btn" id="submitBtn" style="width: 100%;">
        <span id="btnText">Загрузить и Проверить</span>
        <span id="btnLoading" style="display: none;">Обработка...</span>
    </button>
    </form>

    <script>
        const form = document.getElementById('imageCheckForm');
        const input = document.getElementById('images');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoading = document.getElementById('btnLoading');

        form.addEventListener('submit', function(e) {
            if (!input.files.length) {
                e.preventDefault();
                alert('Выберите хотя бы один файл для проверки');
                return false;
            }
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
        });
    </script>
</div>
@endsection
