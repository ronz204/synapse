---

description: "Task list for 002-perceived-performance"
---

# Tasks: Rendimiento Percibido Instantáneo

**Input**: Design documents from `specs/002-perceived-performance/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: **OBLIGATORIOS.** No es una preferencia de estilo: el Principio III de la constitución
declara que un requisito cuyos criterios de aceptación no están codificados como pruebas Pest
cuenta como no implementado, y FR-013 a FR-015 piden explícitamente una medición ejecutable. Las
tareas de prueba de este archivo no son opcionales.

**Organization**: agrupadas por historia de usuario, para que cada una se implemente y se valide por
separado.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede correr en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: a qué historia de usuario pertenece (US1, US2, US3, US4)
- Todas las rutas son relativas a `synapse/`

---

## Nota sobre el orden de las fases

El spec prioriza User Story 4 (sostener el rendimiento) como P3, y está bien priorizada: su valor
para el usuario llega al final. Pero **su infraestructura tiene que existir primero**. SC-008 exige
que ninguna interacción empeore respecto de una línea base, y SC-009 que los escenarios existentes
sigan pasando; ninguna de las dos se puede demostrar si se optimiza antes de medir.

Por eso el arnés de medición y la semilla de volumen están en la Fase 2 (Foundational) y no en la
fase de US4. Lo que queda en la fase de US4 es lo que aporta valor propio: el reporte comparable, la
declaración de cobertura y la detección de regresiones.

**T014 (capturar la línea base) es irreversible.** Una vez que se optimiza algo, ya no se puede
tomar. Si se pasa por alto, hay que volver al commit previo para capturarla.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: preparar estructura y configuración. Sin dependencias; puede empezar de inmediato.

- [X] T001 [P] Crear la estructura de carpetas del arnés: `app/Support/Performance/`, `app/Jobs/`, `tests/Feature/Performance/`, `tests/Feature/Architecture/`
- [X] T002 [P] Añadir `storage/app/performance/` con su `.gitignore` (ignorar `*.json`, conservar la carpeta) en `storage/app/performance/.gitignore`
- [X] T003 [P] Cambiar `CACHE_STORE` de `database` a `redis` en `.env` y `.env.example`, con `file` como alternativa documentada si Redis no responde (D-07). **No tocar `SESSION_DRIVER`** — se deja en `database` a propósito
- [X] T004 [P] Verificar que Redis responde antes de fijar el driver: `php artisan tinker --execute 'Cache::store("redis")->put("ping",1,5); echo Cache::store("redis")->get("ping");'`. Si falla, usar `file` y anotarlo en el diario de decisiones
- [X] T005 [P] Documentar `php artisan optimize` como paso obligatorio de despliegue en `README.md` (sección de despliegue), y verificar que no rompe nada en local con `php artisan optimize && php artisan test --compact`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: la capacidad de medir. Sin esto, ninguna optimización posterior es demostrable.

**⚠️ CRÍTICO**: ninguna tarea de US1, US2 o US3 puede empezar antes de que T014 esté hecha.

### Acuerdo de contrato (bloqueante de US1 y US2)

- [X] T006 Acordar con el responsable del dominio (Roney) que `paginate(?string $search, int $perPage, int $page, ?string $sortBy, string $sortDir): array{items, total}` queda congelado como contrato de listado, según el Principio V. Registrar el acuerdo en `.claude/docs/approach.md`. **Bloquea T022, T023, T024 y T032**

### Datos de medición

- [X] T007 Crear `database/seeders/PerformanceVolumeSeeder.php` que genere el volumen objetivo de [data-model.md](./data-model.md) (2 programas, 10 planes, 800 cursos, ~1.200 prerrequisitos, 500 equivalencias, 5 modalidades, 300 asignaciones, 2.000 estudiantes, ~24.000 registros de historial), con semilla de aleatoriedad fija e idempotente
- [X] T008 Extender `database/seeders/PerformanceVolumeSeeder.php` con los casos negativos obligatorios: una cadena de equivalencias que forma ciclo al cerrarse, un par de resoluciones en contradicción, una resolución de modalidad vencida y un plan Terminal con fecha de cierre (depende de T007)
- [X] T009 [P] Escribir `tests/Feature/Performance/VolumeSeederTest.php` que afirme los conteos exactos del volumen objetivo y que dos ejecuciones consecutivas dejan los mismos números (idempotencia)

### Modelo de medición

- [X] T010 [P] Crear el enum `App\Support\Performance\InteractionClass` en `app/Support/Performance/InteractionClass.php` con los cuatro valores cerrados: `AppBoot`, `ModuleOpen`, `InModule`, `Write`
- [X] T011 [P] Crear `App\Support\Performance\PerformanceBudget` en `app/Support/Performance/PerformanceBudget.php` con los presupuestos B-01…B-07 y S-01…S-06 exactamente como los declara [contracts/performance-budgets.md](./contracts/performance-budgets.md), cada uno con su campo `criterion` de trazabilidad al `SC-` que lo origina
- [X] T012 [P] Crear `App\Support\Performance\InteractionMeasurement` en `app/Support/Performance/InteractionMeasurement.php` con los campos y las invariantes de [data-model.md](./data-model.md) (al menos uno de `firstPaintMs`/`queryCount` presente; `contentReadyMs >= firstPaintMs`)

### Capa 1 del arnés — determinista

- [X] T013 Crear `tests/Feature/Performance/QueryBudgetTest.php` que, con el volumen objetivo cargado, ejercite cada módulo vía `Livewire::test()` contando consultas con `DB::listen` y midiendo filas serializadas, afirmando S-01 a S-04 (depende de T008, T011)
- [X] T014 Crear `tests/Feature/Performance/RenderBudgetTest.php` que registre el tiempo de render de servidor por interacción y lo afirme contra un techo holgado, dejando el valor exacto siempre en el reporte (depende de T011)
- [X] T015 [P] Crear `tests/Feature/Architecture/DomainIsolationTest.php` que falle si aparece cualquier import de `Illuminate\` o `Livewire\` bajo `src/**/Domain` (presupuesto S-06, Principio I)

### Capa 2 del arnés — navegador real

- [X] T016 Crear `resources/js/perf-probe.js`: sonda que, dentro de la página, mide con la Performance API el intervalo `pointerdown` → primera mutación visual y `pointerdown` → contenido utilizable
- [X] T017 Crear `app/Console/Commands/MeasurePerformanceCommand.php` (`php artisan perf:measure`) que conduzca Chromium con Puppeteer vía `base_path('node_modules')` —el mismo que ya usa `SpatiePdfExporter`—, inicie sesión, ejecute las 44 interacciones de la cobertura obligatoria N veces e informe p95 por módulo e interacción (depende de T011, T016)
- [X] T018 Añadir a `app/Console/Commands/MeasurePerformanceCommand.php` las opciones `--repetitions`, `--concurrency` y `--baseline` (depende de T017)

### Línea base — punto de no retorno

- [X] T019 **Capturar la línea base antes de optimizar nada**: `php artisan db:seed --class=PerformanceVolumeSeeder` y `php artisan perf:measure --repetitions=20 --baseline`, versionando el resultado en `specs/002-perceived-performance/baseline.json` (depende de T013, T014, T017, T018)
- [X] T020 Confirmar que la línea base **falla** los presupuestos esperados y registrar los números en el diario de decisiones: `equivalencies/open` muy por encima de B-02, con `queryCount` del orden de mil. Si la línea base pasa todo, el arnés no está midiendo lo que debería (depende de T019)

**Checkpoint**: existe una línea base versionada. A partir de aquí, toda optimización es demostrable.

---

## Phase 3: User Story 1 — Abrir un módulo sin espera perceptible (Priority: P1) 🎯 MVP

**Goal**: pulsar cualquier módulo de la barra lateral reacciona en ≤ 100 ms y entrega contenido
utilizable en ≤ 500 ms (p95) al volumen objetivo.

**Independent Test**: con el volumen objetivo cargado, `php artisan perf:measure` reporta todas las
filas `*/open` dentro de B-01 y B-02, y `QueryBudgetTest` afirma S-01 en los nueve módulos.

### Tests for User Story 1 ⚠️

> Escribir primero y confirmar que fallan contra el código actual.

- [X] T021 [P] [US1] Añadir a `tests/Feature/Performance/QueryBudgetTest.php` el caso que afirma ≤ 10 consultas por apertura de módulo en los nueve módulos (S-01). Debe fallar hoy en `equivalencies`
- [X] T022 [P] [US1] Añadir a `tests/Feature/Performance/QueryBudgetTest.php` el caso que afirma ≤ `perPage` filas serializadas en Cursos, Equivalencias y Asignaciones de Modalidad (S-03). Debe fallar hoy en los tres
- [X] T023 [P] [US1] Crear `tests/Feature/Performance/SidebarPersistenceTest.php` que verifique que el nodo `<aside>` sobrevive a la navegación entre dos módulos, conservando estado (regla R-03)

### Implementation for User Story 1

- [X] T024 [US1] Eliminar el N+1 de `src/Curriculum/Equivalency/Presentation/Livewire/EquivalencyComponent.php`: resolver los códigos de curso y los números de resolución que supersede **en lote por página** (dos consultas) en vez de dos por fila dentro de `toRow()` (D-02). Es el cambio de mayor impacto individual del plan
- [X] T025 [US1] Pasar `src/Curriculum/Equivalency/Presentation/Livewire/EquivalencyComponent.php` a `tableMode = 'server'` y activar la ruta `renderServerMode()` ya escrita (D-01, depende de T006, T024)
- [X] T026 [P] [US1] Pasar `src/Curriculum/Course/Presentation/Livewire/CourseComponent.php` a `tableMode = 'server'` (D-01, depende de T006)
- [X] T027 [P] [US1] Pasar `src/Curriculum/Modality/Presentation/Livewire/ModalityAssignmentComponent.php` a `tableMode = 'server'` (D-01, depende de T006)
- [X] T028 [US1] Confirmar que Planes de Estudio, Modalidades, Roles y Permisos **siguen** en `tableMode = 'client'` y documentar el umbral de 200 filas en el docblock de `app/Livewire/Concerns/InteractsWithDataTable.php`, para que la próxima persona no migre los cuatro por uniformidad
- [X] T029 [US1] Reemplazar el `<select>` de ~800 cursos de la sub-vista de estructura por un selector con búsqueda que consulte bajo demanda, en `src/Curriculum/StudyPlan/Presentation/Livewire/StudyPlanComponent.php` y `resources/views/curriculum/study-plan/livewire/study-plan-structure.blade.php` (hallazgo H6)
- [X] T030 [P] [US1] Crear `resources/views/components/ui/skeleton.blade.php`: placeholder que replica la geometría de la tabla (encabezado, N filas del alto real, paginador), no un spinner (regla R-02)
- [X] T031 [US1] Conectar el esqueleto en `resources/views/components/ui/data-table.blade.php` con `wire:loading` acotado por `wire:target` y con `wire:loading.delay` para evitar parpadeo en respuestas rápidas (depende de T030)
- [X] T032 [US1] Añadir en `resources/views/components/siga/sidebar.blade.php` el acuse de pulsación inmediato con Alpine, limpiado en `livewire:navigated` — `wire:current` sólo marca la entrada activa después de navegar y no sirve como reacción inmediata (regla R-01)
- [X] T033 [US1] Verificar con `php artisan perf:measure --repetitions=20` que las nueve filas `*/open` cumplen B-01 y B-02, y comparar contra la línea base (depende de T024–T032)

**Checkpoint**: abrir cualquier módulo se siente instantáneo al volumen objetivo. Es el MVP: entregable y demostrable por sí solo.

---

## Phase 4: User Story 2 — Operar listados sin sensación de espera (Priority: P1)

**Goal**: ordenar, paginar, buscar y filtrar actualizan el listado en ≤ 300 ms (p95), sin que la
pantalla se reconstruya.

**Independent Test**: las filas `sort:*`, `paginate:*`, `search:hit` y `search:miss` de los siete
listados cumplen B-04, y el panel de red muestra **una** petición al escribir una palabra completa.

### Tests for User Story 2 ⚠️

- [X] T034 [P] [US2] Añadir a `tests/Feature/Performance/QueryBudgetTest.php` el caso de ≤ 6 consultas por interacción dentro de módulo, en los siete listados (S-02)
- [X] T035 [P] [US2] Crear `tests/Feature/Performance/SearchDebounceTest.php` que verifique que una secuencia de pulsaciones produce una sola consulta y no una por tecla (FR-008)
- [X] T036 [P] [US2] Añadir a `tests/Feature/Performance/QueryBudgetTest.php` el caso que afirma que los catálogos en modo cliente no superan 200 filas serializadas (S-04) — la prueba que detecta si alguien migra un catálogo grande a modo cliente

### Implementation for User Story 2

- [X] T037 [US2] Añadir estabilización de entrada de 250 ms a la búsqueda en modo servidor: `wire:model.live.debounce.250ms` en las vistas de los tres listados migrados, y el retardo equivalente en `app/Livewire/Concerns/InteractsWithDataTable.php`
- [X] T038 [P] [US2] **DESVIACIÓN DELIBERADA — no se alineó.** El modo cliente queda en 150 ms. Filtra un arreglo que ya está en el navegador y no emite ninguna consulta, así que no hay nada que agrupar; subirlo a 250 ms sólo haría más lento el camino rápido. FR-008 habla de consultas, y el modo cliente no hace ninguna. El modo servidor sí bajó de 400 ms a 250 ms (T037). Razonamiento en el comentario de `resources/views/components/ui/data-table.blade.php`
- [X] T039 [US2] Acotar el esqueleto al cuerpo de la tabla durante ordenar/paginar/buscar en `resources/views/components/ui/data-table.blade.php`, de modo que encabezado, filtros y paginador no parpadeen (depende de T031)
- [X] T040 [P] [US2] Verificar que el estado vacío de búsqueda sin resultados aparece dentro del mismo presupuesto que un resultado con datos, en `resources/views/components/ui/data-table.blade.php`
- [X] T041 [US2] Verificar con `php artisan perf:measure` que las 28 filas de interacción dentro de módulo cumplen B-04, incluidas las `search:miss` (depende de T037–T040)

**Checkpoint**: US1 y US2 funcionan de forma independiente. Con estas dos, el requerimiento original del usuario está cubierto.

---

## Phase 5: User Story 3 — Acciones con respuesta inmediata (Priority: P2)

**Goal**: todo botón de acción acusa la pulsación en ≤ 100 ms, no se puede disparar dos veces, y
entrega confirmación o rechazo en ≤ 1 s — o confirma la aceptación de inmediato si no puede.

**Independent Test**: las filas `save:*` y `export:pdf` cumplen B-05 y B-06, los tres rechazos de
dominio se miden, y una doble pulsación produce un solo registro.

### Tests for User Story 3 ⚠️

- [X] T042 [P] [US3] Crear `tests/Feature/Performance/DoubleSubmitTest.php`: dos invocaciones consecutivas de una acción de escritura producen un solo registro y el botón queda `disabled` durante la operación (regla R-04, FR-004)
- [X] T043 [P] [US3] Crear `tests/Feature/Performance/RejectionBudgetTest.php` que mida los tres rechazos obligatorios —`save:reject:cycle`, `save:reject:contradiction`, `save:reject:no-modality-resolution`— contra B-05, **y verifique que el mensaje sigue conteniendo la cadena completa del ciclo y el par de resoluciones en conflicto, verbatim** (regla R-05, Principio II)
- [X] T044 [P] [US3] Crear `tests/Feature/Performance/QueuedExportTest.php`: `exportPdf()` retorna sin haber arrancado Chromium y encola un job (regla R-08, FR-012)

### Implementation for User Story 3

- [X] T045 [P] [US3] Añadir `wire:loading.attr="disabled"` con `wire:target` a todos los botones de acción de escritura en las vistas de `resources/views/curriculum/**/livewire/*.blade.php`, `resources/views/identityaccess/**/livewire/*.blade.php` y `resources/views/components/ui/confirm-delete-modal.blade.php` (regla R-04)
- [X] T046 [US3] Crear `app/Jobs/GenerateTableExportJob.php` que genere el PDF con `PdfExporterInterface` fuera de la petición y lo deje en `storage/app/performance/../exports/` con un identificador consultable (D-04)
- [X] T047 [US3] Modificar `app/Livewire/Concerns/InteractsWithExports.php` para que `streamPdf()` encole en vez de generar en línea, devolviendo confirmación inmediata de aceptación (depende de T046)
- [X] T048 [US3] Añadir el estado "exportación en proceso" con `wire:poll` corto y la entrega de la descarga cuando el archivo está listo, en `resources/views/components/ui/data-table.blade.php` (depende de T047). No se monta broadcasting: `BROADCAST_CONNECTION=log` y el sondeo corto basta para un caso puntual
- [X] T049 [P] [US3] Verificar que un rechazo de dominio conserva los datos ya cargados en el formulario, en `src/Curriculum/Equivalency/Presentation/Livewire/EquivalencyComponent.php` y `src/Curriculum/Modality/Presentation/Livewire/ModalityAssignmentComponent.php` (regla R-05)
- [X] T050 [P] [US3] Añadir manejo de fallo de carga con mensaje comprensible y opción de reintentar, sin dejar indicadores activos, en `resources/views/components/ui/data-table.blade.php` (regla R-07, FR-009)
- [X] T051 [P] [US3] Añadir descarte de cargas abandonadas: si el usuario pide otro módulo antes de que llegue el primero, se muestra el último pedido y no quedan esqueletos huérfanos, en `resources/views/components/siga/sidebar.blade.php` (regla R-06, FR-010)

**Checkpoint**: las tres historias de valor directo están completas y son demostrables por separado.

---

## Phase 6: User Story 4 — Sostener el rendimiento en el tiempo (Priority: P3)

**Goal**: el equipo puede ejecutar la medición a demanda y obtener un veredicto por módulo e
interacción, con comparación contra la línea base.

**Nota**: la infraestructura del arnés ya se construyó en la Fase 2 porque US1 y US2 la necesitan.
Esta fase entrega lo que da valor propio a la historia: el reporte comparable, la declaración
honesta de cobertura y la detección de regresiones.

**Independent Test**: introducir una regresión a propósito y confirmar que el reporte la señala,
nombrando módulo e interacción.

### Tests for User Story 4 ⚠️

- [X] T052 [P] [US4] Crear `tests/Feature/Performance/ReportFormatTest.php` que valide que la salida JSON cumple [contracts/measurement-report.md](./contracts/measurement-report.md): `rows` exhaustivo, `notMeasured` siempre presente aunque vaya vacío, campos nulos según capa y nunca rellenados con ceros
- [X] T053 [P] [US4] Crear `tests/Feature/Performance/RegressionDetectionTest.php` que revierta temporalmente un componente a `tableMode = 'client'` y afirme que el arnés falla S-04 señalando `courses` con 800 filas — la prueba de que la prueba sirve

### Implementation for User Story 4

- [X] T054 [US4] Implementar la salida JSON canónica en `app/Console/Commands/MeasurePerformanceCommand.php` según el contrato, escrita en `storage/app/performance/report-<timestamp>.json` (depende de T017)
- [X] T055 [US4] Implementar la salida en consola con los incumplimientos primero y el exceso en porcentaje, en `app/Console/Commands/MeasurePerformanceCommand.php` (depende de T054)
- [X] T056 [US4] Implementar la comparación contra `specs/002-perceived-performance/baseline.json` dentro del mismo reporte, con el recuento mejoran / sin cambio / empeoran y el incumplimiento de SC-008 marcado explícitamente (depende de T054)
- [X] T057 [US4] Implementar la sección `notMeasured` y la declaración de cobertura: si falta cualquiera de las 44 interacciones obligatorias, el veredicto global es `fail` (depende de T054)
- [X] T058 [US4] Hacer que el código de salida sea `0`/`1` según el veredicto, en `app/Console/Commands/MeasurePerformanceCommand.php` (depende de T055)
- [X] T059 [US4] Verificar que un módulo nuevo queda cubierto sin redefinir presupuestos —son por clase de interacción, no por módulo— y documentarlo en el docblock de `app/Support/Performance/PerformanceBudget.php`

**Checkpoint**: las cuatro historias completas.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T060 [P] Evaluar `livewire/blaze` en un solo componente de listado, midiendo antes y después con la capa 1 del arnés. **Conservarlo sólo si la mejora es reproducible; revertirlo si no lo es** (D-08). Registrar el resultado en el diario de decisiones en cualquiera de los dos casos
- [X] T061 [P] **NO APLICA.** T060 demostró que el hallazgo H7 era incorrecto: Blaze no es opt-in por componente, ya estaba activo por defecto (`config/blaze.php` trae `enabled => true`) y la medición controlada no muestra diferencia reproducible. No hay nada que extender. Ver el diario de decisiones
- [X] T062 [P] Añadir índice a `courses.active` en una migración nueva bajo `database/migrations/` (hallazgo H9 — impacto menor al volumen objetivo, se corrige por corrección)
- [X] T063 Verificar que **no existe ninguna caché del grafo de equivalencias entre peticiones**: `Get-ChildItem -Recurse -Filter *.php src\Curriculum\Equivalency | Select-String -Pattern "Cache::|cache\("` no debe arrojar coincidencias (Principio II, D-06)
- [X] T064 Ejecutar la suite de dominio completa y confirmar SC-009 —los escenarios de aceptación existentes pasan sin modificación—: `php artisan test --compact tests/Unit/Curriculum` y `php artisan test --compact tests/Feature/Acceptance`
- [ ] T065 **PENDIENTE — requiere el servidor levantado.** La concurrencia sólo es medible en la capa de navegador; la capa determinista corre en un solo proceso PHP y ahora **rechaza** `--concurrency > 1` en vez de aceptarlo y reportar un número que nada respalda. Ejecutar con `composer dev` corriendo: `php artisan perf:measure --layer=browser --repetitions=20 --concurrency=30`
- [X] T066 [P] Actualizar `.claude/docs/modules.md` y `.claude/docs/approach.md` con el umbral cliente/servidor, la prohibición de cachear el grafo y la ubicación del arnés (Principio VI)
- [X] T067 [P] Registrar en el diario de decisiones de IA al menos un caso real y verificable en que una salida de IA fue incorrecta y hubo que corregirla — una entrada genérica no cumple el requisito
- [X] T068 Ejecutar `vendor\bin\pint --dirty --format agent` y después `composer test` (encadena pint, phpstan y la suite completa)
- [X] T069 Recorrido de [quickstart.md](./quickstart.md) completo salvo los pasos que exigen el servidor levantado (capa de navegador y concurrencia). Ver el reporte de cierre

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Fase 1)**: sin dependencias
- **Foundational (Fase 2)**: depende de Setup. **BLOQUEA todas las historias.** T019 (línea base) es el punto de no retorno
- **US1 (Fase 3)** y **US2 (Fase 4)**: dependen de la Fase 2 completa, y de T006 (acuerdo de contrato) para las tareas de modo servidor
- **US3 (Fase 5)**: depende de la Fase 2. Independiente de US1 y US2 — se puede avanzar en paralelo
- **US4 (Fase 6)**: depende de T017 y de que exista `baseline.json`
- **Polish (Fase 7)**: depende de las historias que se hayan decidido entregar

### User Story Dependencies

- **US1 (P1)**: arranca tras la Fase 2. No depende de otras historias
- **US2 (P1)**: arranca tras la Fase 2. Comparte `data-table.blade.php` con US1 — T039 depende de T031, es el único punto de contacto
- **US3 (P2)**: arranca tras la Fase 2. Sin dependencia de US1 ni US2
- **US4 (P3)**: arranca tras la Fase 2. Consume el arnés que US1 y US2 ya usan

### Parallel Opportunities

- Fase 1: T001–T005 en paralelo, las cinco
- Fase 2: T009, T010, T011, T012, T015 en paralelo. T007→T008 secuencial. T016 en paralelo con el bloque de modelo
- US1: T021, T022, T023 en paralelo (pruebas). T026 y T027 en paralelo entre sí. T030 en paralelo con las migraciones de modo
- US2: T034, T035, T036 en paralelo. T038 y T040 en paralelo
- US3: T042, T043, T044 en paralelo. T045, T049, T050, T051 en paralelo
- US4: T052 y T053 en paralelo
- **Entre historias**: con dos personas, una toma US1+US2 (frente de datos y payload) y la otra US3+US4 (frente de retroalimentación y medición). Comparten sólo `data-table.blade.php`, así que conviene coordinar ese archivo

---

## Parallel Example: User Story 1

```bash
# Las tres pruebas de US1, en paralelo (deben fallar antes de implementar):
Task: "Caso S-01 de consultas por apertura en tests/Feature/Performance/QueryBudgetTest.php"
Task: "Caso S-03 de filas serializadas en tests/Feature/Performance/QueryBudgetTest.php"
Task: "Persistencia del <aside> en tests/Feature/Performance/SidebarPersistenceTest.php"

# Las dos migraciones a modo servidor sin acoplamiento entre sí:
Task: "tableMode servidor en src/Curriculum/Course/Presentation/Livewire/CourseComponent.php"
Task: "tableMode servidor en src/Curriculum/Modality/Presentation/Livewire/ModalityAssignmentComponent.php"
```

---

## Implementation Strategy

### MVP First (US1)

1. Fase 1 completa
2. Fase 2 completa — **con la línea base capturada en T019 antes de tocar nada**
3. Fase 3 (US1)
4. **PARAR Y VALIDAR**: `php artisan perf:measure` con las nueve filas `*/open` en verde
5. Demostrable: abrir cualquier módulo se siente instantáneo a 800 cursos y 500 equivalencias

Dentro de US1, si hay que priorizar aún más: **T024 solo** (el N+1 de Equivalencias) es la mejora de
mayor relación impacto/esfuerzo de todo el plan y se puede entregar antes que el resto.

### Incremental Delivery

1. Setup + Foundational → existe línea base
2. US1 → abrir módulos es instantáneo → demo (MVP)
3. US2 → operar listados es instantáneo → demo
4. US3 → las acciones responden y la exportación no bloquea → demo
5. US4 → el rendimiento queda protegido → demo

Los tres primeros checkpoints cubren el requerimiento tal como lo enunció el usuario. US4 protege lo
ganado.

---

## Notes

- `[P]` = archivos distintos, sin dependencias pendientes
- Ninguna optimización puede alterar un resultado funcional (FR-016). Ante la duda entre velocidad e
  integridad del dominio, gana la integridad y se revierte el cambio
- La detección de ciclos y contradicciones no se aproxima, no se acorta y no se cachea entre
  peticiones (Principio II, D-06). T063 lo verifica explícitamente
- Ejecutar `vendor/bin/pint --dirty --format agent` y las pruebas del área afectada antes de dar por
  cerrada cualquier tarea de PHP
- Confirmar en cada checkpoint que la suite existente sigue pasando: SC-009 es un criterio de
  aceptación, no una cortesía
