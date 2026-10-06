@extends('layout')
@section('content')
@php($source = $submission->raw_metadata['published_at_source'] ?? null)
<section class="admin-detail">
    <a class="back" href="{{ route('admin.index') }}">← Volver al listado</a>
    <div class="detail-heading"><div><p class="eyebrow">PUBLICACIÓN #{{ $submission->id }}</p><h1>Datos de publicación</h1></div><span class="pill {{ $submission->data_status }}">{{ \App\Presenters\SubmissionDataStatus::label($submission->data_status) }}</span></div>
    @if(session('notice'))<p class="notice">{{ session('notice') }}</p>@endif
    <div class="detail-grid">
        <div><b>Plataforma</b><span>{{ ucfirst($submission->platform) }}</span></div><div><b>Autor</b><span>{{ $submission->author ?: 'No disponible' }}</span></div>
        <div><b>Username</b><span>{{ $submission->username ? '@'.$submission->username : 'No disponible' }}</span></div><div><b>External ID</b><span>{{ $submission->external_id ?: 'No disponible' }}</span></div>
        <div class="wide"><b>URL</b><a href="{{ $submission->original_url }}" target="_blank" rel="noopener">{{ $submission->original_url }}</a></div><div class="wide"><b>Canonical URL</b><a href="{{ $submission->canonical_url ?: $submission->original_url }}" target="_blank" rel="noopener">{{ $submission->canonical_url ?: 'No disponible' }}</a></div>
        <div class="wide"><b>Caption</b><span>{{ $submission->caption ?: 'No disponible' }}</span></div>
        <div><b>Fecha publicación</b><span>{{ $submission->published_at?->utc()->translatedFormat('d M Y H:i').' UTC' ?? 'No disponible' }}</span></div><div><b>Fuente fecha</b><span>@if($source === 'tiktok_video_id') ID TikTok <em>(inferido)</em>@elseif($source === 'public_metadata') Metadata pública <em>(directo)</em>@else No disponible @endif</span></div>
        <div><b>Views</b><span>{{ $submission->views === null ? 'No disponible' : number_format($submission->views, 0, ',', '.') }}</span></div><div><b>Likes</b><span>{{ $submission->likes === null ? 'No disponible' : number_format($submission->likes, 0, ',', '.') }}</span></div><div><b>Comentarios</b><span>{{ $submission->comments === null ? 'No disponible' : number_format($submission->comments, 0, ',', '.') }}</span></div><div><b>Accesibilidad</b><span>{{ $submission->is_public === true ? 'Pública y accesible' : ($submission->is_public === false ? 'No accesible' : 'No confirmada') }}</span></div>
    </div>
    <section class="diagnostics"><h2>Diagnóstico técnico</h2><div><b>Provider</b><span>{{ $submission->provider ?: 'No disponible' }}</span></div><div><b>Status</b><span>{{ $submission->status }}</span></div><div><b>Validation message / error</b><span>{{ $submission->validation_message ?: '—' }}</span></div><div><b>Última comprobación</b><span>{{ $submission->last_checked_at?->utc()->format('d M Y H:i').' UTC' ?? 'No disponible' }}</span></div><details><summary>Raw metadata</summary><pre>{{ $rawMetadata ?: '{}' }}</pre></details></section>
    <div class="detail-actions"><form method="post" action="{{ route('admin.submissions.refresh', $submission) }}">@csrf<button type="submit">Actualizar</button></form><a class="button secondary-button" href="{{ $submission->canonical_url ?: $submission->original_url }}" target="_blank" rel="noopener">Abrir original</a><form method="post" action="{{ route('admin.submissions.destroy', $submission) }}" onsubmit="return confirm('¿Eliminar esta publicación? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button class="danger-button" type="submit">Eliminar</button></form></div>
</section>
@endsection
