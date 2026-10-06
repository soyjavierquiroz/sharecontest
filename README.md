# ShareContest

MVP Laravel para registrar publicaciones públicas de TikTok, Instagram y Facebook en un concurso. Guarda lo que se puede confirmar de forma pública y deja en `review_required` lo que una plataforma no permite comprobar.

## Arquitectura

`Traefik existente -> sharecontest_web -> PostgreSQL / Redis existentes`.

El worker `sharecontest_worker` consume la cola Redis para actualizaciones masivas. El stack usa las redes externas `general_network` y `traefik_public`; no crea infraestructura compartida ni publica puertos.

## Configuración

```bash
cp .env.example .env.production
chmod 600 .env.production
```

Edite `.env.production` localmente e introduzca `DB_PASSWORD`, `ADMIN_PASSWORD` y una `APP_KEY` generada por Laravel (`docker run --rm -v "$PWD:/app" -w /app composer:2 php artisan key:generate --show`). Ese archivo está ignorado por Git y nunca debe contenerse en un commit. `APP_HOST` cambia el hostname de Traefik. La sesión, caché y cola usan Redis.

## Build y despliegue

```bash
docker build -t sharecontest:latest .
docker run --rm --network general_network --env-file .env.production sharecontest:latest php artisan migrate --force --seed
docker stack deploy --resolve-image never -c stack.yml sharecontest
```

Antes de migrar confirme que `DB_DATABASE=sharecontest`. Nunca use `migrate:fresh` en producción.

Compruebe el despliegue con:

```bash
docker stack services sharecontest
docker service ps sharecontest_web
docker service logs -f sharecontest_web
curl -fsS https://sharecontest.kuruk.in/health
```

## Operación

Actualizar participaciones:

```bash
docker run --rm --network general_network --env-file .env.production sharecontest:latest php artisan contest:refresh --all
docker run --rm --network general_network --env-file .env.production sharecontest:latest php artisan contest:refresh --status=review_required
docker run --rm --network general_network --env-file .env.production sharecontest:latest php artisan contest:refresh --top=100
```

Actualizar el stack: construya la misma etiqueta y ejecute de nuevo `docker stack deploy --resolve-image never -c stack.yml sharecontest`.

Detener sólo ShareContest: `docker stack rm sharecontest`. Rollback básico: reconstruya/etiquete la imagen anterior disponible y redespliegue el mismo stack.

## Datos por red

- TikTok: consulta primero oEmbed público; puede obtener autor y título/caption cuando TikTok lo devuelve. No inventa métricas ausentes.
- Instagram y Facebook: consulta HTML público y metadatos OpenGraph; muros de login, 403, 429 o metadata insuficiente dejan la participación en revisión.

Las consultas tienen timeout, redirecciones limitadas, whitelist de hosts y bloqueo de URLs/protocolos no permitidos. No hay OAuth, scraping con evasión, proxies ni bypass de CAPTCHA.
