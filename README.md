### Synapse

#### Requirements

- [Scoop](https://scoop.sh)
- [Docker Desktop](https://www.docker.com/products/docker-desktop)

```
scoop install php composer bun
```

Make sure the `pdo_mysql` extension is enabled in your `php.ini` (uncomment `extension=pdo_mysql`).

#### Install

```
git clone <repo-url>
cd synapse
composer install
bun install
```

#### Environment

```
copy .env.example .env
php artisan key:generate
```

#### Database

```
docker compose up -d mysql
php artisan migrate
```

#### Run

```
composer dev
```

App runs at `http://localhost:8000`.

#### Deploy

`php artisan optimize` is **required** on every deploy. It caches config, routes and views; without
it every request re-parses them, which shows up directly in the module-open budget the performance
harness measures.

```
php artisan optimize
```

Run `php artisan optimize:clear` when pulling changes locally — a cached config will otherwise hide
`.env` edits.

`CACHE_STORE` must not point at `database`: the application cache would then compete with business
queries for the same MySQL connections. Use `redis` where the deployment provides it, `file`
otherwise. `SESSION_DRIVER` stays on `database` on purpose — it is one indexed read per request and
it is the correct choice if the app ever runs on more than one instance.

#### Performance

```
php artisan db:seed --class=PerformanceVolumeSeeder   # target volume
php artisan test --compact --filter=Performance        # deterministic budgets
php artisan perf:measure --repetitions=20              # perceived budgets, real browser
```

Budgets live in `specs/002-perceived-performance/contracts/performance-budgets.md`. That file is the
source of truth — `App\Support\Performance\PerformanceBudget` mirrors it.
