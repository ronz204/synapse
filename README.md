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
