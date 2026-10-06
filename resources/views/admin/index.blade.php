@extends('layout')
@section('content')
<section class="admin-page">
    <div class="admin-heading">
        <div><p class="eyebrow">ADMINISTRACIÓN</p><h1>Publicaciones</h1><p class="lead">Seguimiento de datos públicos recolectados.</p></div>
        <form method="post" action="{{ route('admin.submissions.refresh-all') }}">@csrf<button type="submit">Actualizar todas</button></form>
    </div>
    @if(session('notice'))<p class="notice">{{ session('notice') }}</p>@endif
    <div class="metric-cards primary-cards">
        @foreach(['total' => 'TOTAL', 'complete' => 'COMPLETOS', 'partial' => 'PARCIALES', 'no_data' => 'SIN DATOS'] as $key => $label)
            <div class="metric-card"><span>{{ $label }}</span><b>{{ number_format($stats[$key], 0, ',', '.') }}</b></div>
        @endforeach
    </div>
    <div class="platform-cards">
        @foreach(['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook'] as $key => $label)
            <div><span>{{ $label }}</span><b>{{ number_format($stats[$key], 0, ',', '.') }}</b></div>
        @endforeach
    </div>
    <form class="filters" method="get">
        <input name="q" value="{{ request('q') }}" placeholder="Buscar autor, usuario o URL">
        <select name="platform"><option value="">Todas las plataformas</option>@foreach(['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook'] as $key => $label)<option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>@endforeach</select>
        <select name="data_status"><option value="">Todos los estados</option>@foreach(['complete' => 'Datos obtenidos', 'partial' => 'Datos parciales', 'no_data' => 'Sin datos', 'error' => 'Error'] as $key => $label)<option value="{{ $key }}" @selected(request('data_status') === $key)>{{ $label }}</option>@endforeach</select>
        <select name="sort"><option value="created_at" @selected(request('sort', 'created_at') === 'created_at')>Más recientes</option><option value="published_at" @selected(request('sort') === 'published_at')>Fecha publicación</option><option value="views" @selected(request('sort') === 'views')>Views</option><option value="likes" @selected(request('sort') === 'likes')>Likes</option><option value="last_checked_at" @selected(request('sort') === 'last_checked_at')>Última comprobación</option></select>
        <button class="secondary" type="submit">Filtrar</button>
    </form>
    <form method="post" action="{{ route('admin.submissions.refresh-selected') }}">@csrf
        <div class="bulk-actions"><button class="secondary" type="submit">Actualizar seleccionadas</button><span>Las actualizaciones masivas se procesan en segundo plano.</span></div>
        <div class="table-wrap"><table><thead><tr><th><span class="sr-only">Seleccionar</span></th><th>ID</th><th>Plataforma</th><th>Autor / usuario</th><th>Publicada</th><th>Views</th><th>Likes</th><th>Comentarios</th><th>Estado datos</th><th>Última comprobación</th><th>Acciones</th></tr></thead><tbody>
        @forelse($submissions as $s)
            <tr>
                <td><input class="row-check" type="checkbox" name="submissions[]" value="{{ $s->id }}" aria-label="Seleccionar publicación {{ $s->id }}"></td>
                <td>#{{ $s->id }}</td><td><span class="platform-name">{{ ucfirst($s->platform) }}</span></td>
                <td><b>{{ $s->author ?: 'No disponible' }}</b><small>{{ $s->username ? '@'.$s->username : '—' }}</small></td>
                <td>{{ $s->published_at?->utc()->format('d M Y H:i') ?? 'No disponible' }}</td>
                <td>{{ $s->views === null ? '—' : number_format($s->views, 0, ',', '.') }}</td><td>{{ $s->likes === null ? '—' : number_format($s->likes, 0, ',', '.') }}</td><td>{{ $s->comments === null ? '—' : number_format($s->comments, 0, ',', '.') }}</td>
                <td><span class="pill {{ $s->data_status }}">{{ \App\Presenters\SubmissionDataStatus::label($s->data_status) }}</span></td>
                <td>{{ $s->last_checked_at?->utc()->format('d M Y H:i') ?? '—' }}</td>
                <td class="actions"><a href="{{ route('admin.submissions.show', $s) }}">Ver</a><button class="link-button" type="submit" formaction="{{ route('admin.submissions.refresh', $s) }}" formmethod="post">Actualizar</button><a href="{{ $s->canonical_url ?: $s->original_url }}" target="_blank" rel="noopener">Abrir original</a><button class="link-button danger" type="submit" formaction="{{ route('admin.submissions.destroy', $s) }}" formmethod="post" name="_method" value="DELETE" onclick="return confirm('¿Eliminar esta publicación? Esta acción no se puede deshacer.')">Eliminar</button></td>
            </tr>
        @empty<tr><td colspan="11" class="empty">Aún no hay publicaciones que coincidan.</td></tr>@endforelse
        </tbody></table></div>
    </form>
    {{ $submissions->links() }}
</section>
@endsection
