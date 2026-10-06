@extends('layout')
@section('content')
@php($source = $submission->raw_metadata['published_at_source'] ?? null)
@php($datePrecision = $submission->raw_metadata['published_at_precision'] ?? null)
<section class="result">
    <p class="eyebrow">PARTICIPACIÓN #{{ $submission->id }}</p><h1 class="status">DATOS {{ $submission->status === 'invalid' ? 'NO DISPONIBLES' : 'OBTENIDOS' }}</h1>
    @if(session('notice'))<p class="notice">{{ session('notice') }}</p>@endif
    <div class="result-section"><h2>Datos de publicación</h2><div class="details"><div><b>Autor</b><span>{{ $submission->author ?: 'No disponible' }}</span></div><div><b>Usuario</b><span>{{ $submission->username ? '@'.$submission->username : 'No disponible' }}</span></div><div><b>Publicado</b><span>{{ $submission->published_at ? ($datePrecision === 'date' ? $submission->published_at->utc()->locale('es')->translatedFormat('d M Y') : $submission->published_at->utc()->locale('es')->translatedFormat('d M Y H:i').' UTC') : 'No disponible' }}@if($source === 'tiktok_video_id')<small>Fecha estimada desde ID de TikTok</small>@endif</span></div><div class="wide"><b>Descripción</b><span>{{ $submission->caption ?: 'No disponible' }}</span></div></div></div>
    <div class="result-section"><h2>Métricas</h2><div class="details metrics"><div><b>Views</b><span>{{ $submission->views === null ? 'No disponible' : number_format($submission->views, 0, ',', '.') }}</span></div><div><b>Likes</b><span>{{ $submission->likes === null ? 'No disponible' : number_format($submission->likes, 0, ',', '.') }}</span></div><div><b>Comentarios</b><span>{{ $submission->comments === null ? 'No disponible' : number_format($submission->comments, 0, ',', '.') }}</span></div></div></div>
    <a class="back" href="/">← Inspeccionar otra publicación</a>
</section>
@endsection
