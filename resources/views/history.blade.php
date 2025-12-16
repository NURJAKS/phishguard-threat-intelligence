@extends('layouts.app')

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h1>История Проверок</h1>
        <a href="{{ route('home') }}" class="btn">Новая проверка</a>
    </div>

    @if($checks->isEmpty())
        <p style="color: var(--text-muted);">История пуста</p>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 180px;">DATE / TIME</th>
                        <th>SUBJECT (PHONE / LINK)</th>
                        <th style="width: 100px; text-align: center;">RISK</th>
                        <th style="width: 150px; text-align: center;">VERDICT</th>
                        <th style="width: 100px;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($checks as $check)
                        @php
                            $risk = $check->risk_score ?? 0;
                            $riskColor = '#22c55e';
                            if ($risk > 20) $riskColor = '#facc15';
                            if ($risk > 70) $riskColor = '#ef4444';
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="font-family: var(--font-mono); color: var(--text-muted);">
                                {{ $check->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td style="font-family: var(--font-mono); color: var(--text-main);">
                                {{ $check->phone ?: Str::limit($check->link, 50) }}
                            </td>
                            <td style="text-align: center; font-weight: bold; color: {{ $riskColor }};">
                                {{ $risk }}%
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background-color: {{ $riskColor }}20; color: {{ $riskColor }}; border: 1px solid {{ $riskColor }}40;">
                                    {{ strtoupper($check->verdict ?? 'UNKNOWN') }}
                                </span>
                            </td>
                            <td>
                                <!-- Currently no detail view link, but could add later -->
                                <button class="btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">VIEW</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
