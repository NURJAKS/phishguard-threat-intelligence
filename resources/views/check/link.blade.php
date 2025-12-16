@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h1>🌐 Проверка Ссылки / Домена</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Проверьте фишинговые ссылки и репутацию домена.</p>

    <div class="form-group">
        <label for="domain">URL или Домен</label>
        <input type="text" id="domain" placeholder="example.com или https://..." />
    </div>
    <button type="button" class="btn" style="width: 100%;">Проверить Домен</button>
</div>
@endsection
