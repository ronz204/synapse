# Implementation Plan: Rendimiento Percibido Instantáneo

**Branch**: `002-perceived-performance` | **Date**: 2026-08-18 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-perceived-performance/spec.md`

## Summary

El sistema ya tiene la base correcta para navegación tipo SPA: `wire:navigate` en toda la barra
lateral, `x-persist` sobre el `<aside>` y tablas que resuelven orden/filtro/página en el navegador.
Lo que rompe la percepción de instantaneidad al volumen objetivo no es la ausencia de esa base, sino
**cuánta información viaja en cada render** y **cuántas consultas cuesta armarla**.

Los siete componentes de listado corren en `tableMode = 'client'`, lo que significa que cada render
llama a `$useCase->all()` y serializa la colección completa dentro del payload de Livewire. A
volumen de demostración es la decisión correcta y por eso está así. A 800 cursos y 500
equivalencias deja de serlo. Y en Equivalencias el costo se multiplica: `toRow()` ejecuta dos
consultas por fila, de modo que listar 500 equivalencias dispara del orden de mil consultas en un
solo render.

El plan ataca eso en cuatro frentes, en orden de impacto medido:

1. **Medir primero.** Sin línea base no se puede demostrar SC-008 ni SC-009. Se construye el arnés
   de medición antes de tocar una sola consulta.
2. **Reducir el payload y las consultas** de los listados grandes: modo servidor con paginación
   (ya implementado en el trait y en los repositorios, hoy sin usar) para los catálogos que crecen,
   y eliminación del N+1 de Equivalencias.
3. **Hacer visible la reacción**: estados de carga con la forma del contenido, botones que se
   deshabilitan solos, y confirmación inmediata para la exportación PDF, que hoy arranca Chromium
   dentro de la petición HTTP.
4. **Fijar los presupuestos como pruebas** para que no se erosionen.

Ninguna de las cuatro toca la capa de dominio ni debilita la detección de ciclos o contradicciones.

## Technical Context

**Language/Version**: PHP 8.5 en ejecución (`composer.json` declara `^8.3`); JavaScript ESM sin
framework adicional.

**Primary Dependencies**: Laravel 13, Livewire 4, `livewire/blaze` 1.0 (instalado y auto-descubierto,
**sin usarse en ningún componente todavía**), Flux 2, Alpine.js (embebido en Livewire), Tailwind 4,
Vite 8, Spatie Browsershot 5 + laravel-pdf 2 + simple-excel 3, Pest 5, Larastan 3, Pint 1.

**Storage**: MySQL. `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`
— las tres capas de infraestructura de soporte pegan contra la misma base de datos que las
consultas de negocio. Redis está declarado en `.env` (`REDIS_HOST=127.0.0.1`) pero ningún driver lo
usa. Documentos en disco local.

**Testing**: Pest 5 (`tests/Unit`, `tests/Feature`, `tests/Feature/Acceptance`), 30 archivos de
prueba. `composer test` encadena `pint --test`, `phpstan analyse` y `artisan test`.

**Target Platform**: navegador de escritorio actualizado sobre red institucional o banda ancha;
servidor PHP-FPM con MySQL.

**Project Type**: monolito modular. Cada módulo es un hexágono
(`src/<Contexto>/<Módulo>/{Domain,Application,Infrastructure,Presentation}`) y Laravel actúa como
adaptador en `app/` y `resources/`.

**Performance Goals**: SC-001 a SC-010 del spec. Los tres que gobiernan el diseño: reacción visual
≤ 100 ms en el 100 % de las pulsaciones, apertura de módulo ≤ 500 ms en p95, interacción dentro de
módulo ≤ 300 ms en p95.

**Constraints**:

- El aislamiento dominio/framework es innegociable: nada de `Illuminate\` ni `Livewire\` bajo
  `src/**/Domain`. Toda caché, paginación y memoización vive en Infrastructure o Presentation.
- La detección de ciclos y contradicciones no se aproxima, no se acorta y no se sirve desde una
  caché que pueda estar desactualizada.
- Ningún registro histórico se archiva ni se borra para acelerar consultas.
- Los cambios de firma en los `List*UseCase` cruzan el límite Presentation ↔ Application y
  requieren acuerdo previo con el responsable del dominio (Principio V).

**Scale/Scope**: volumen objetivo del spec — ~10 planes, ~800 cursos, ~500 equivalencias, ~300
asignaciones de modalidad, ~2.000 estudiantes con historial; 30 usuarios concurrentes. Superficie
afectada: 9 entradas de navegación, 7 componentes Livewire de listado, 1 sub-vista de estructura,
2 rutas de exportación.

### Puntos calientes identificados en el código actual

Estos son hallazgos verificados, no hipótesis. Ordenan el trabajo de las fases siguientes.

| # | Hallazgo | Evidencia | Costo al volumen objetivo |
|---|----------|-----------|---------------------------|
| H1 | Los 7 listados corren en modo cliente y serializan la colección completa por render | `tableMode = 'client'` en los 7 componentes; `freshRows()` llama `$useCase->all()` | Cursos: 800 filas en cada payload; Equivalencias: 500 |
| H2 | N+1 en el listado de Equivalencias | `EquivalencyComponent::toRow()` hace un `whereIn` de cursos **y** un `find()` de la resolución que supersede, por fila | ~1.000 consultas por render del listado |
| H3 | `render()` se re-ejecuta en cada petición Livewire y vuelve a consultar toda la tabla | `freshRows()` se llama desde `renderClientMode()` | Abrir un modal re-consulta el catálogo entero |
| H4 | La exportación PDF arranca Chromium dentro de la petición HTTP | `SpatiePdfExporter::fromHtml()` → `Browsershot` síncrono | Segundos de bloqueo; viola SC-005 y FR-012 |
| H5 | Caché, sesión y cola comparten MySQL con las consultas de negocio | `.env`: los tres en `database` | Round-trips extra por petición |
| H6 | La sub-vista de estructura carga el catálogo completo de cursos en cada render | `StudyPlanComponent::courseOptions()` sin filtro ni límite | ~800 `<option>` por render |
| H7 | ~~`livewire/blaze` está instalado pero ningún componente lo aprovecha~~ **HALLAZGO INCORRECTO** | Se dedujo de la ausencia de atributos `#[Blaze]`, pero el paquete no es opt-in: `config/blaze.php` trae `enabled => true` y engancha el compilador de Blade globalmente | Ninguno. Ya estaba activo, incluida la línea base. Medición controlada con `BLAZE_ENABLED=false` no muestra diferencia reproducible — ver el diario de decisiones |
| H8 | No existe ninguna medición de rendimiento ni línea base | Sin Pulse, sin Telescope, sin pruebas de presupuesto | SC-008 y SC-009 hoy no son verificables |
| H9 | `courses.active` sin índice; las búsquedas usan `LIKE %…%` | `2026_08_03_100010_create_courses_table.php` | Menor al volumen objetivo; se registra, no se prioriza |

## Constitution Check

*GATE: debe pasar antes de la investigación de Fase 0. Re-evaluado tras el diseño de Fase 1.*

| Principio | Evaluación previa | Evaluación posterior al diseño |
|-----------|-------------------|-------------------------------|
| **I. Domain-Framework Isolation** | PASA con guarda. El trabajo se concentra en Presentation (componentes Livewire, Blade) e Infrastructure (repositorios Eloquent). El riesgo real es introducir caché o paginación dentro de `src/**/Domain`. | PASA. El diseño ubica la paginación en los repositorios (adaptadores, ya la implementan) y la memoización en Presentation. Ningún artefacto de Fase 1 agrega imports de framework al dominio. Se añade una prueba que falla si aparece uno. |
| **II. Equivalency Graph Integrity** | **RIESGO ALTO.** Cachear `activeGraph()` entre peticiones es la optimización más tentadora y la más peligrosa: un grafo desactualizado deja pasar un ciclo. | PASA con restricción explícita. `activeGraph()` **no** se cachea entre peticiones. Sólo se permite memoización dentro de una misma petición. La detección sigue siendo el DFS completo. Ver [research.md](./research.md) D-06. |
| **III. Acceptance Criteria Are the Tests** | PASA por diseño. User Story 4 y FR-013 a FR-015 exigen que los presupuestos sean ejecutables. | PASA. El arnés de Fase 1 codifica cada presupuesto como prueba Pest; un presupuesto sólo documentado se considera no implementado. |
| **IV. Non-Destructive History** | PASA. Nada en el spec pide archivar ni purgar. | PASA. Se prohíbe explícitamente en Assumptions del spec y se refleja aquí: las equivalencias `Superseded` siguen consultables desde el listado. |
| **V. Contract-First** | **REQUIERE ACUERDO.** Pasar listados a modo servidor cambia qué método del caso de uso invoca la Presentation (`all()` → `paginate()`). | PASA condicionado. `paginate()` **ya existe** en los repositorios y casos de uso, con firma estable; no se inventa un contrato nuevo. Se requiere confirmación del responsable del dominio antes de implementar, no antes de planificar. Registrado como bloqueante en [quickstart.md](./quickstart.md). |
| **VI. Whole-System Understanding** | PASA. | PASA. Los presupuestos y el arnés quedan documentados en `contracts/` y en el diario de decisiones. |

**Veredicto**: no hay violaciones que justificar. Hay una restricción dura (Principio II, sin caché
del grafo entre peticiones) y una dependencia de acuerdo previo (Principio V) que se resuelve antes
de escribir código, no antes de planificar.

## Project Structure

### Documentation (this feature)

```text
specs/002-perceived-performance/
├── plan.md                          # Este archivo
├── spec.md                          # Especificación de entrada
├── research.md                      # Fase 0 — decisiones técnicas y alternativas descartadas
├── data-model.md                    # Fase 1 — entidades de medición
├── quickstart.md                    # Fase 1 — cómo verificar la feature de punta a punta
├── contracts/
│   ├── performance-budgets.md       # Presupuestos por clase de interacción (fuente de verdad)
│   ├── measurement-report.md        # Formato del reporte cumple/no cumple
│   └── ui-feedback-contract.md      # Contrato de retroalimentación visual de la UI
├── checklists/
│   └── requirements.md              # Validación de calidad del spec
└── tasks.md                         # Fase 2 — generado por /speckit-tasks, NO por este comando
```

### Source Code (repository root: `synapse/`)

```text
app/
├── Console/Commands/
│   └── MeasurePerformanceCommand.php        # NUEVO — arnés de medición en navegador real
├── Jobs/
│   └── GenerateTableExportJob.php           # NUEVO — exportación PDF fuera de la petición
├── Livewire/Concerns/
│   ├── InteractsWithDataTable.php           # MODIFICADO — umbral cliente/servidor, debounce
│   └── InteractsWithExports.php             # MODIFICADO — encolar en vez de bloquear
└── Support/Performance/
    ├── PerformanceBudget.php                # NUEVO — presupuesto por clase de interacción
    └── InteractionMeasurement.php           # NUEVO — una medición observada

src/
├── Curriculum/
│   ├── Course/Presentation/Livewire/CourseComponent.php            # MODIFICADO — modo servidor
│   ├── Equivalency/Presentation/Livewire/EquivalencyComponent.php  # MODIFICADO — servidor + fin del N+1
│   ├── Modality/Presentation/Livewire/ModalityAssignmentComponent.php # MODIFICADO — modo servidor
│   ├── Modality/Presentation/Livewire/ModalityComponent.php        # sin cambios (catálogo pequeño)
│   └── StudyPlan/Presentation/Livewire/StudyPlanComponent.php      # MODIFICADO — selector de cursos bajo demanda
└── IdentityAccess/
    ├── Permission/Presentation/Livewire/PermissionComponent.php    # sin cambios (catálogo pequeño)
    └── Role/Presentation/Livewire/RoleComponent.php                # sin cambios (catálogo pequeño)

resources/
├── js/
│   ├── data-table.js                        # MODIFICADO — debounce de búsqueda
│   └── perf-probe.js                        # NUEVO — sonda de medición en navegador (Puppeteer)
└── views/components/ui/
    ├── data-table.blade.php                 # MODIFICADO — esqueletos de carga
    └── skeleton.blade.php                   # NUEVO — placeholder con la forma del contenido

database/seeders/
└── PerformanceVolumeSeeder.php              # NUEVO — genera el volumen objetivo del spec

tests/
├── Feature/Performance/
│   ├── QueryBudgetTest.php                  # NUEVO — consultas y payload por módulo
│   └── RenderBudgetTest.php                 # NUEVO — tiempo de render de servidor por módulo
└── Feature/Architecture/
    └── DomainIsolationTest.php              # NUEVO — el dominio no importa framework
```

**Structure Decision**: se conserva íntegra la estructura existente. Los componentes Livewire y los
adaptadores Eloquent ya están en su hexágono correspondiente bajo `src/`, y el andamiaje compartido
de Laravel vive en `app/`. Lo único nuevo a nivel de carpetas son `app/Support/Performance/`,
`app/Jobs/`, `tests/Feature/Performance/` y `tests/Feature/Architecture/` — todas dentro de
convenciones estándar de Laravel, ninguna carpeta base nueva en la raíz. Los presupuestos viven en
`contracts/performance-budgets.md` como fuente de verdad legible y se leen desde `PerformanceBudget`,
para que documento y prueba no se separen.

## Phase 0 — Research

Completado. Ver [research.md](./research.md), que resuelve nueve decisiones:

- **D-01** Umbral cliente/servidor por tamaño de catálogo, en lugar de migrar los siete listados.
- **D-02** Eliminación del N+1 de Equivalencias por resolución en lote.
- **D-03** Esqueletos de carga con `wire:loading` en vez de spinners genéricos.
- **D-04** Exportación PDF encolada con confirmación inmediata.
- **D-05** Arnés de medición en dos capas: determinista en Pest, perceptual en navegador real.
- **D-06** Prohibición de cachear el grafo de equivalencias entre peticiones.
- **D-07** Caché de aplicación fuera de MySQL, con degradación segura si no hay Redis.
- **D-08** Adopción de `livewire/blaze` en los componentes de listado.
- **D-09** Semilla de volumen determinista y reutilizable como dato de prueba.

## Phase 1 — Design & Contracts

Completado. Artefactos generados:

- [data-model.md](./data-model.md) — las cuatro entidades de medición del spec, sus campos,
  invariantes y la única transición de estado que existe (cumple / no cumple).
- [contracts/performance-budgets.md](./contracts/performance-budgets.md) — la tabla de presupuestos
  como fuente de verdad única, consumida tanto por el reporte como por las pruebas.
- [contracts/measurement-report.md](./contracts/measurement-report.md) — formato del reporte de
  medición, para que FR-015 (identificar módulo e interacción culpables) sea verificable.
- [contracts/ui-feedback-contract.md](./contracts/ui-feedback-contract.md) — qué debe cumplir todo
  control interactivo para satisfacer FR-001 a FR-004, escrito como reglas comprobables.
- [quickstart.md](./quickstart.md) — cómo levantar el volumen objetivo, tomar la línea base,
  ejecutar la medición y verificar cada historia de usuario.

## Complexity Tracking

> Se completa sólo si el Constitution Check arrojó violaciones que haya que justificar.

No hay violaciones de la constitución. Sí hay dos incorporaciones que agregan piezas móviles y que
conviene justificar explícitamente para que la revisión no las lea como sobreingeniería:

| Incorporación | Por qué es necesaria | Alternativa más simple, y por qué se descartó |
|---------------|----------------------|-----------------------------------------------|
| Job en cola + entrega diferida para la exportación PDF | Browsershot arranca Chromium dentro de la petición. Ninguna optimización de consultas evita ese bloqueo de segundos, y SC-005 y FR-012 lo prohíben tal como está. | Mantenerla síncrona y sólo mostrar un indicador de progreso. Descartada: cumple FR-003 pero no FR-012, que exige confirmar la aceptación de inmediato. La cola ya está configurada (`QUEUE_CONNECTION=database`) y `composer dev` ya levanta un `queue:listen`, así que el costo de infraestructura es cero. |
| Arnés de medición de dos capas en vez de una | La capa determinista (consultas, tamaño de payload, tiempo de render) es estable en CI pero no mide lo que el usuario percibe. La capa de navegador mide la percepción real pero es ruidosa. Cada una cubre el punto ciego de la otra. | Sólo la capa de navegador. Descartada: su variabilidad la vuelve inútil como criterio de aprobación reproducible, y FR-013 pide un resultado cumple/no cumple, no una tendencia. Chromium ya está instalado como dependencia de Browsershot, así que la segunda capa no agrega dependencias. |
