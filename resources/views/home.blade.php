@extends('layout')
@section('content')
<section class="hero"><p class="eyebrow">CONCURSO SOCIAL 2026</p><h1>Comparte. Participa.<br><em>Gana.</em></h1><p class="lead">Pega el enlace de tu TikTok, Reel de Instagram o publicación/video de Facebook.</p></section>
<section class="card"><h2>Participa</h2><form method="post" action="{{ route('submissions.store') }}">@csrf<label>URL de tu publicación<input name="url" type="url" required value="{{ old('url') }}" placeholder="https://..."></label>@error('url')<p class="error">{{ $message }}</p>@enderror<div class="optional"><label>Nombre (opcional)<input name="participant_name" value="{{ old('participant_name') }}"></label><label>Email (opcional)<input name="participant_email" type="email" value="{{ old('participant_email') }}"></label></div><button>VALIDAR PARTICIPACIÓN →</button></form><p class="hint">Validamos la visibilidad pública y el hashtag. Si una plataforma limita la consulta, tu participación quedará en revisión, no será rechazada.</p></section>
<div class="platforms"><span>TikTok</span><span>Instagram</span><span>Facebook</span></div>
@endsection
