# Quickstart — Verificar Rendimiento Percibido Instantáneo

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Date**: 2026-08-18

Cómo levantar el volumen objetivo, tomar la línea base, ejecutar la medición y comprobar cada
historia de usuario. Esta guía valida; no implementa. El detalle de implementación va en `tasks.md`.

---

## Bloqueante antes de empezar

**Principio V de la constitución (Contract-First).** Pasar Cursos, Equivalencias y Asignaciones de
Modalidad a modo servidor cambia qué método del caso de uso invoca la capa de presentación:
`all()` pasa a ser `paginate()`. Ese método **ya existe** con firma estable en los cinco
repositorios y sus casos de uso, así que no se inventa un contrato nuevo — pero el cambio cruza el
límite Presentation ↔ Application y requiere acuerdo explícito con el responsable del dominio antes
de escribir código.

Confirmar con Roney que `paginate(?string $search, int $perPage, int $page, ?string $sortBy, string $sortDir): array{items, total}` queda congelado como contrato de listado. Sin ese acuerdo, D-01 no
arranca.

Lo demás del plan (medición, N+1, retroalimentación visual, exportación encolada) no depende de esta
confirmación y puede avanzar en paralelo.

---

## Prerrequisitos

```powershell
# Desde synapse/
composer install
bun install
php artisan migrate:fresh
php artisan db:seed          # roles, permisos, programas, modalidades, períodos
```

MySQL debe estar corriendo, y también la base `synapse_testing` que usa `phpunit.xml`.

Un usuario para la medición en navegador:

```powershell
php artisan tinker --execute 'App\Models\User::factory()->create(["email" => "perf@local.test"]);'
```

---

## Paso 1 — Cargar el volumen objetivo

```powershell
php artisan db:seed --class=PerformanceVolumeSeeder
```

Genera exactamente lo declarado en [data-model.md](./data-model.md): 800 cursos, 500 equivalencias,
300 asignaciones de modalidad, 2.000 estudiantes, con semilla de aleatoriedad fija.

**Verificar que quedó bien**, incluidos los casos negativos que el arnés necesita:

```powershell
php artisan tinker --execute 'echo App\Models\Course::count()." cursos / ".App\Models\Equivalency::count()." equivalencias / ".App\Models\Student::count()." estudiantes";'
```

Se espera `800 cursos / 500 equivalencias / 2000 estudiantes`. Si no coincide, la comparación contra
la línea base no va a significar nada — no seguir hasta que coincida.

**Comprobar idempotencia** (correrlo dos veces debe dejar los mismos números, no el doble):

```powershell
php artisan db:seed --class=PerformanceVolumeSeeder
php artisan tinker --execute 'echo App\Models\Course::count();'
```

---

## Paso 2 — Tomar la línea base (antes de optimizar nada)

Esto se hace **una sola vez**, con el código sin optimizar. SC-008 y SC-009 se evalúan contra ella y
no se regenera después.

```powershell
php artisan perf:measure --repetitions=20 --baseline
```

Escribe `specs/002-perceived-performance/baseline.json`. Se versiona junto al código.

Se espera que la línea base **falle** varios presupuestos — ese es el punto. Con los hallazgos del
diagnóstico, lo esperable es ver `equivalencies/open` muy por encima de `B-02` y con un `queryCount`
del orden de mil.

> Si por lo que sea se optimizó algo antes de este paso, revertirlo o tomar la línea base desde el
> commit anterior. Una línea base tomada después de optimizar deja SC-008 sin forma de demostrarse.

---

## Paso 3 — Ejecutar la medición

**Capa determinista** (consultas, payload, tiempo de render de servidor):

```powershell
php artisan test --compact --filter=Performance
```

**Capa de navegador** (percepción real):

```powershell
composer dev      # en otra terminal: servidor + cola + vite
php artisan perf:measure --repetitions=20
```

Salida en consola según [contracts/measurement-report.md](./contracts/measurement-report.md), y JSON
en `storage/app/performance/`. Código de salida `0` si pasa, `1` si no.

**Con concurrencia** (requerido por SC-007 para `B-02` y `B-04`):

```powershell
php artisan perf:measure --repetitions=20 --concurrency=30
```

---

## Paso 4 — Verificar cada historia de usuario

### User Story 1 — Abrir un módulo sin espera perceptible

| Qué comprobar | Cómo |
|---------------|------|
| Reacción visual ≤ 100 ms en las 9 entradas | Filas `*/open` del reporte, columna `firstPaintMs`, presupuesto `B-01` |
| Contenido utilizable ≤ 500 ms en p95 | Filas `*/open`, presupuesto `B-02` |
| Esqueleto con la forma del contenido, nunca pantalla en blanco | A mano: abrir Cursos con la red limitada a 3G lento en las herramientas del navegador |
| Volver a un módulo ya visitado no es más lento | Comparar la 1.ª repetición contra la mediana de las 20 en `*/open` |

Comprobación manual clave — que la barra lateral no parpadee:

1. Iniciar sesión, colapsar la barra lateral, abrir el grupo "Grupos".
2. Navegar entre Cursos → Equivalencias → Planes de Estudio.
3. La barra debe conservar el estado colapsado y el grupo abierto, sin parpadeo (R-03).

### User Story 2 — Operar listados sin sensación de espera

| Qué comprobar | Cómo |
|---------------|------|
| Ordenar, paginar, buscar ≤ 300 ms en p95 | Filas `sort:*`, `paginate:*`, `search:*`, presupuesto `B-04` |
| Búsqueda sin resultados igual de rápida | Fila `search:miss` contra `search:hit` |
| No hay una consulta por tecla | Panel de red del navegador: escribir "algoritmo" y contar peticiones — debe ser 1, no 10 |
| Los listados grandes están realmente paginados | Presupuesto `S-03`: filas serializadas ≤ `perPage` |
| Los catálogos pequeños siguen en modo cliente | Presupuesto `S-04`; Roles y Modalidades deben ordenar sin ninguna petición de red |

### User Story 3 — Acciones con respuesta inmediata

| Qué comprobar | Cómo |
|---------------|------|
| Botón acusa la pulsación ≤ 100 ms y se deshabilita | A mano en el formulario de equivalencias; y prueba de R-04 |
| Doble pulsación no duplica el registro | Capa determinista: dos invocaciones seguidas → un solo registro |
| Rechazo por ciclo dentro de `B-05`, con el ciclo completo visible | Fila `save:reject:cycle`. **Verificar además que el mensaje sigue mostrando la cadena `A → B → C → A` verbatim** |
| Rechazo por contradicción con ambas resoluciones visibles | Fila `save:reject:contradiction` |
| El formulario conserva los datos tras un rechazo | A mano: llenar, provocar un ciclo, comprobar que nada se perdió |
| La exportación PDF confirma de inmediato | A mano: pulsar exportar y comprobar que la confirmación aparece antes de que exista el archivo |

**La verificación más importante de esta sección no es de tiempo.** Después de optimizar, correr la
suite de dominio completa y confirmar que la detección de ciclos y contradicciones sigue rechazando
exactamente lo mismo:

```powershell
php artisan test --compact tests/Unit/Curriculum/Equivalency
php artisan test --compact tests/Feature/Acceptance
```

Si alguna de estas falla, la optimización se revierte, sin discusión (Principio II, FR-016).

### User Story 4 — Sostener el rendimiento en el tiempo

| Qué comprobar | Cómo |
|---------------|------|
| El reporte da cumple/no cumple por módulo e interacción | Salida del paso 3 |
| Una regresión se detecta y se señala | Introducir una a propósito (ver abajo) |
| Un módulo nuevo queda cubierto sin redefinir presupuestos | Los presupuestos son por clase, no por módulo — comprobar que un módulo nuevo aparece en el reporte sin tocar el contrato |

**Prueba de la prueba** — introducir una regresión deliberada y confirmar que el arnés la caza:

```powershell
# Revertir temporalmente CourseComponent a tableMode = 'client'
php artisan test --compact --filter=Performance
```

Debe fallar `S-04` señalando `courses`, con 800 filas serializadas. Si pasa, el arnés no sirve y hay
que arreglarlo antes de confiar en él.

---

## Paso 5 — Verificaciones transversales

**Aislamiento dominio/framework** (Principio I, presupuesto `S-06`):

```powershell
php artisan test --compact --filter=DomainIsolation
```

Debe dar cero imports de `Illuminate\` o `Livewire\` bajo `src/**/Domain`.

**El grafo de equivalencias no se cachea** (Principio II, D-06):

```powershell
Get-ChildItem -Recurse -Filter *.php src\Curriculum\Equivalency | Select-String -Pattern "Cache::|cache\("
```

No debe haber ninguna coincidencia. Si aparece una, es un incumplimiento de la constitución, no una
optimización.

**Suite completa antes de dar por terminado**:

```powershell
vendor\bin\pint --dirty --format agent
composer test
```

---

## Definición de terminado

- [ ] Contrato de `paginate()` acordado con el responsable del dominio (bloqueante del Paso 0)
- [ ] `baseline.json` versionado, tomado antes de cualquier optimización
- [ ] Las 44 interacciones de la cobertura obligatoria se miden; `notMeasured` vacío
      (9 aperturas de módulo + 7 listados × 4 interacciones + 7 escrituras)
- [ ] Todos los presupuestos `B-01` a `B-07` cumplen al volumen objetivo
- [ ] Todos los presupuestos `S-01` a `S-06` cumplen
- [ ] `B-02` y `B-04` cumplen además con 30 sesiones concurrentes
- [ ] Ninguna interacción empeora respecto de la línea base; las cuatro clases mejoran su p95 (SC-008)
- [ ] Toda la suite existente pasa sin modificación (SC-009) — en particular los rechazos de ciclo y contradicción
- [ ] Las ocho reglas de [ui-feedback-contract.md](./contracts/ui-feedback-contract.md) verificadas
- [ ] El diario de decisiones de IA registra al menos un caso real en que hubo que corregir una salida equivocada

---

## Problemas frecuentes

**`perf:measure` no encuentra Chromium.** Browsershot lo resuelve vía `node_modules`. Correr
`bun install` y confirmar que `node_modules/puppeteer` existe. El comando pasa
`setNodeModulePath(base_path('node_modules'))`, igual que el exportador de PDF.

**La exportación queda "en proceso" para siempre.** No hay worker de cola. `composer dev` levanta
uno; si se está corriendo `php artisan serve` a mano, hace falta además
`php artisan queue:listen`.

**Los tiempos varían mucho entre ejecuciones.** Esperable en la capa de navegador y la razón de que
las afirmaciones duras vivan en la capa determinista. Para comparar contra la línea base, medir con
la misma máquina desocupada y con `php artisan optimize` aplicado en ambos lados.

**La línea base ya no coincide con los identificadores del reporte.** Alguien renombró un `module` o
una `interaction`. Regenerar la línea base y dejar constancia en el diario de decisiones — es un
cambio que rompe la comparación en silencio, y por eso el contrato pide anunciarlo.

---

## Actualización tras la implementación (2026-08-18)

El arnés terminó con **dos capas invocables por separado**, cosa que esta guía no anticipaba:

```powershell
# Capa determinista — no necesita servidor ni navegador. Consultas y tiempo de render.
php artisan perf:measure --repetitions=20 --user=<email>

# Capa de navegador — necesita `composer dev` corriendo. Percepción real.
php artisan perf:measure --layer=browser --repetitions=20 --concurrency=30
```

La línea base versionada en `baseline.json` se tomó con la **capa determinista** contra la base
`synapse_testing`, no contra la base de desarrollo — así el volumen objetivo no contamina los datos
de trabajo. Para comparar, hay que medir contra la misma base:

```powershell
$env:DB_DATABASE="synapse_testing"
php artisan migrate:fresh --force --seed
php artisan db:seed --class=PerformanceVolumeSeeder --force
php artisan perf:measure --repetitions=20 --user=test@example.com
```

**Piso de ruido.** La comparación contra la línea base ignora diferencias menores a 25 ms o al 20 %.
No es una concesión: cuatro corridas consecutivas del mismo código sin cambios dieron
34/37/38/61 ms para la misma apertura de módulo. Sin el piso, el reporte señalaba tres "regresiones"
distintas en cada ejecución, y un reporte que grita lobo siempre es un reporte que nadie lee.

**Concurrencia.** `--concurrency > 1` en la capa determinista ahora **se rechaza** en lugar de
aceptarse en silencio. Un solo proceso PHP no puede abrir sesiones concurrentes, y reportar
"concurrency: 30" sin nada que lo respalde sería peor que no soportarlo.
