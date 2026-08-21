### Synapse

#### Requisitos

- [Scoop](https://scoop.sh)
- [Docker Desktop](https://www.docker.com/products/docker-desktop)

```
scoop install php composer bun
```

Asegúrate de tener la extensión `pdo_mysql` habilitada en tu `php.ini` (descomenta `extension=pdo_mysql`).

#### Instalación

```
git clone <repo-url>
cd synapse
composer install
bun install
```

#### Entorno

```
copy .env.example .env
php artisan key:generate
```

#### Base de datos y caché

La app usa Redis para caché, sesión y cola (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` en `.env`), así que hay que levantar los dos servicios, no solo MySQL:

```
docker compose up -d
php artisan migrate
```

---

## Cómo correr la app: desarrollo vs. demo

Hay dos formas de levantar la app, según qué estés haciendo. La diferencia importa porque afecta directamente qué tan rápido se siente la navegación — no es un tema de gustos.

| | `composer dev` | `composer demo` |
|---|---|---|
| Cuándo usarlo | Estás escribiendo código, especialmente JS/CSS/Blade | Vas a hacer una demo, o quieres medir/verificar velocidad real |
| Assets | Vite en modo desarrollo (HMR, cambios en vivo) | Bundle compilado de antemano (`bun run build`) |
| Primera navegación | Lenta (~1-1.5s extra) la primera vez que se piden `app.css`/`app.js`/etc. después de arrancar — Vite y Tailwind los compilan bajo demanda | Rápida desde la primera petición, sin importar qué tan "frío" esté todo lo demás |
| Cola (exportación PDF) | Sí, incluida (`queue:listen`) | Sí, incluida (`queue:listen`) |

Esta diferencia está confirmada con mediciones reales (Debugbar + Network Timing del navegador): en modo `dev`, el backend responde en ~100-140ms incluso con 3000+ cursos y 1200+ equivalencias sembrados — el volumen de datos nunca fue el problema. Lo que sí toma ~700-800ms por archivo es que Vite compile `resources/css/app.css` y los `.js` la primera vez que el navegador los pide tras un arranque en frío. Es comportamiento normal de Vite, no una regresión de la app, y solo pasa una vez por sesión de `bun run dev`.

### Modo desarrollo — paso a paso

Úsalo cuando vas a tocar código.

```
docker compose up -d
php artisan migrate
composer dev
```

- `composer dev` levanta tres procesos en paralelo: `php artisan serve` (servidor), `php artisan queue:listen` (procesa la exportación PDF en background) y `bun run dev` (Vite con HMR).
- App disponible en `http://localhost:8000`.
- La primera vez que navegues a cualquier página vas a notar una carga más lenta (~1-1.5s extra) mientras Vite compila los assets — es esperado, ya no navegues y verás que el resto de la sesión es rápido.
- Para detener todo, `Ctrl+C` en la terminal donde corre `composer dev`.

### Modo demo — paso a paso

Úsalo para hacer una demo o para verificar que la app responde rápido de verdad, sin el ruido de Vite compilando en caliente.

```
docker compose up -d
php artisan migrate
composer demo
```

- `composer demo` primero corre `bun run build` (compila y deja listo el bundle en `public/build/`), y luego levanta `php artisan serve` + `php artisan queue:listen` en paralelo — sin Vite en modo desarrollo.
- App disponible en `http://localhost:8000`.
- La navegación es rápida desde la primera petición, incluyendo la primera vez que entras a cualquier módulo.
- La cola sigue corriendo, así que la exportación a PDF funciona igual que en producción (encolada, con progreso).
- Para detener todo, `Ctrl+C` en la terminal donde corre `composer demo`.

**Importante:** si antes corriste `composer dev` en este mismo checkout, Vite deja un archivo `public/hot` que le indica a Laravel que use el servidor de Vite en vez del bundle compilado. `composer demo` no lo borra automáticamente — si after correrlo la app sigue sintiéndose en modo desarrollo, borra `public/hot` a mano:

```
rm public/hot
```

### Verificar con un arranque en frío real

Para probar cualquiera de los dos modos exactamente como se comporta la primera vez que alguien clona el repo y levanta todo (contenedores recién creados, base de datos recién sembrada — el escenario que de verdad importa para una demo de rendimiento):

```
docker compose down
docker compose up -d
php artisan migrate:fresh --seed
```

Y después arranca `composer dev` o `composer demo` según lo que quieras comparar. Con `composer dev` la primera navegación se siente lenta (~1-1.5s extra) y luego rápida; con `composer demo` se siente rápida desde el primer clic.

### Probar el flujo completo punta a punta

Para validar toda la funcionalidad (no solo velocidad) en modo demo:

1. `composer demo`
2. Entra a `http://localhost:8000/login` y autentícate con el usuario de prueba (`test@example.com`, contraseña `password`, sembrado por `DatabaseSeeder`).
3. Navega entre módulos: Cursos, Equivalencias, Planes de estudio, Roles, Permisos, Modalidades, Asignaciones de modalidad, Historial académico.
4. Prueba una exportación PDF desde cualquier catálogo (botón "Descargar") — como la cola está corriendo (`queue:listen`), el job se procesa y el archivo se puede descargar cuando termina, con la barra de progreso.
5. Prueba una exportación Excel — esta es síncrona, se descarga de inmediato.
