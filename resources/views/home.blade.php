@extends('layout')
@section('content')
<section class="hero"><p class="eyebrow">INSPECCIÓN SOCIAL</p><h1>Comparte datos.<br><em>Sin reglas.</em></h1><p class="lead">Pega el enlace de tu TikTok, Reel de Instagram o publicación/video de Facebook.</p></section>
<section class="card"><h2>Obtener datos públicos</h2><form method="post" action="{{ route('submissions.store') }}">@csrf<label>URL de la publicación<input name="url" type="url" required value="{{ old('url') }}" placeholder="https://..."></label>@error('url')<p class="error">{{ $message }}</p>@enderror<div class="optional"><label>Nombre (opcional)<input name="participant_name" value="{{ old('participant_name') }}"></label><label>Email (opcional)<input name="participant_email" type="email" value="{{ old('participant_email') }}"></label></div><button>INSPECCIONAR PUBLICACIÓN →</button></form><p class="hint">Intentamos obtener metadata pública disponible. Si una plataforma limita la consulta, conservamos la URL y los datos parciales obtenidos.</p></section>
<div class="platforms"><span>TikTok</span><span>Instagram</span><span>Facebook</span></div>
@endsection
