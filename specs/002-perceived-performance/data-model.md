# Phase 1 — Data Model: Rendimiento Percibido Instantáneo

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Date**: 2026-08-18

Este requerimiento no agrega ninguna entidad de negocio. No hay migraciones nuevas y no se altera
ninguna tabla existente: cursos, planes, equivalencias, modalidades y estudiantes quedan
exactamente como están.

Lo que sí introduce es un modelo pequeño de **medición**, que vive en `app/Support/Performance/`
como objetos de valor de PHP. No se persiste en base de datos — se calcula en cada ejecución del
arnés y se escribe como reporte. Persistirlo sería inventar un almacén nuevo para un dato que sólo
tiene sentido comparado contra la línea base de un momento dado.

---

## Entidad 1 — Clase de interacción (`InteractionClass`)

Agrupación de acciones del usuario que comparten presupuesto. Es una enumeración cerrada: cuatro
valores, ni uno más. Agregar un quinto sería señal de que un presupuesto está mal definido.

| Valor | Qué agrupa | Dónde se observa |
|-------|------------|------------------|
| `AppBoot` | Primera carga de la aplicación tras iniciar sesión, sin nada cacheado en el equipo | Una vez por sesión |
| `ModuleOpen` | Pulsar una entrada de la barra lateral y llegar a contenido utilizable | 9 entradas de navegación |
| `InModule` | Ordenar, paginar, buscar o filtrar dentro de un listado ya abierto | 7 listados |
| `Write` | Guardar, asignar, eliminar, subir resolución, solicitar exportación | Todos los formularios y acciones de fila |

**Invariante**: toda interacción medible pertenece a exactamente una clase. Una interacción que no
encaje en ninguna indica que el arnés está midiendo algo que el spec no cubre, y debe rechazarse en
lugar de forzarse dentro de una clase.

---

## Entidad 2 — Presupuesto de rendimiento (`PerformanceBudget`)

Límite de tiempo asociado a una clase de interacción, expresado como percentil. Nunca como mejor
caso, nunca como promedio.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `class` | `InteractionClass` | La clase a la que aplica |
| `percentile` | `int` (50–100) | Percentil sobre el que se evalúa |
| `maxMilliseconds` | `int` | Techo en milisegundos |
| `criterion` | `string` | Identificador del criterio del spec que lo origina (p. ej. `SC-002`) |

**Invariantes**:

- Todo presupuesto declara el criterio del spec que lo origina. Un presupuesto sin trazabilidad a un
  `SC-` no puede existir: sería un número inventado.
- Una misma clase puede tener más de un presupuesto en percentiles distintos (`ModuleOpen` tiene uno
  en p95 y otro en p99).
- Los valores son datos, no código: la fuente de verdad es
  [contracts/performance-budgets.md](./contracts/performance-budgets.md), y `PerformanceBudget` la
  refleja. Si divergen, gana el contrato y el código está roto.

---

## Entidad 3 — Medición de interacción (`InteractionMeasurement`)

Una ejecución observada. Es el grano más fino del modelo.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `module` | `string` | Módulo observado (`courses`, `equivalencies`, `study-plans`, …) |
| `interaction` | `string` | Interacción concreta (`open`, `sort:code`, `paginate:next`, `search`, `save`, `export:pdf`) |
| `class` | `InteractionClass` | Clase a la que pertenece |
| `firstPaintMs` | `?int` | Clic → primer cambio visual. Nulo en la capa determinista, que no observa pintado |
| `contentReadyMs` | `int` | Clic → contenido legible y operable |
| `queryCount` | `?int` | Consultas SQL ejecutadas. Nulo en la capa de navegador, que no las ve |
| `serializedRows` | `?int` | Filas incluidas en el payload. Nulo en la capa de navegador |
| `layer` | `deterministic` \| `browser` | Capa del arnés que la produjo |

**Invariantes**:

- Al menos uno de `firstPaintMs` o `queryCount` está presente. Una medición sin ninguno de los dos
  no observó nada.
- `contentReadyMs >= firstPaintMs` cuando ambos están presentes. Lo contrario indica un error de
  instrumentación, no un resultado válido.
- Una medición aislada nunca decide cumplimiento. El veredicto se calcula sobre el percentil de un
  conjunto de repeticiones, según el presupuesto correspondiente.

---

## Entidad 4 — Volumen de datos objetivo (`TargetVolume`)

Conjunto de datos contra el que se verifica todo. Es una declaración, no una consulta: el seeder lo
produce y las pruebas lo afirman.

| Entidad | Cantidad |
|---------|----------|
| Programas | 2 |
| Planes de estudio | 10 |
| Niveles por plan | 8 |
| Cursos | 800 |
| Prerrequisitos | 1.400 |
| Equivalencias | 500 (≈350 `Active`, ≈150 `Superseded`) |
| Modalidades | 5 |
| Asignaciones de modalidad con resolución | 301 (300 vigentes + 1 vencida) |
| Estudiantes | 2.000 |
| Registros de historial académico | 24.000 |

**Invariantes**:

- Generado con semilla de aleatoriedad fija. Dos ejecuciones producen exactamente los mismos datos,
  o la comparación contra la línea base no significa nada.
- Idempotente: ejecutarlo dos veces deja la base en el mismo estado, no en el doble.
- **Contiene los casos negativos del dominio**, no sólo volumen: al menos una cadena de
  equivalencias que forma ciclo si se cierra, al menos un par de resoluciones en contradicción, al
  menos una resolución de modalidad vencida y al menos un plan Terminal con fecha de cierre. Medir
  únicamente el camino feliz dejaría sin cubrir FR-011, que exige que los rechazos sean tan rápidos
  como los éxitos.

---

## Entidad 5 — Reporte de medición (`MeasurementReport`)

Salida de una ejecución completa del arnés. Su formato está fijado en
[contracts/measurement-report.md](./contracts/measurement-report.md) porque FR-015 depende de él:
sin un formato estable no se puede señalar qué módulo e interacción incumplen.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `takenAt` | `string` (ISO 8601) | Momento de la ejecución |
| `volume` | `TargetVolume` | Volumen contra el que se midió |
| `repetitions` | `int` | Repeticiones por interacción |
| `rows` | `array<ReportRow>` | Un resultado por módulo e interacción |
| `verdict` | `pass` \| `fail` | `fail` si cualquier fila incumple |

Cada `ReportRow` lleva módulo, interacción, clase, el percentil observado, el presupuesto aplicable
y el veredicto de esa fila.

---

## Entidad 6 — Línea base (`Baseline`)

Un `MeasurementReport` marcado como referencia, tomado **antes** de cualquier optimización.

**Invariantes**:

- Se toma una sola vez, con el volumen objetivo, y se versiona junto al código.
- SC-008 se evalúa exclusivamente contra ella: ninguna interacción puede empeorar respecto de su
  valor en la línea base, y las cuatro clases deben mejorar su p95.
- No se regenera para "actualizarla" después de optimizar. Reemplazarla vaciaría de sentido a SC-008,
  que es precisamente la comparación contra el estado previo.

---

## Transiciones de estado

El modelo tiene una sola transición, y es la del veredicto de una fila del reporte:

```text
                    percentil observado <= presupuesto
   [ medido ] ─────────────────────────────────────────> [ cumple ]
        │
        │           percentil observado > presupuesto
        └─────────────────────────────────────────────> [ incumple ]
```

No hay estados intermedios y no hay tolerancia. Un `incumple` no se convierte en `cumple` ajustando
el presupuesto: los presupuestos derivan de criterios del spec y cambiarlos requiere cambiar el
spec.

---

## Qué NO se modela

Vale la pena dejarlo escrito para que no se agregue por inercia:

- **Histórico de mediciones en base de datos.** Cada ejecución produce un reporte en disco. Un
  almacén histórico sería infraestructura nueva sin requisito que la pida.
- **Alertas o umbrales por usuario.** Fuera de alcance: el spec pide una medición ejecutable a
  demanda (D-02 de las clarificaciones), no monitoreo en producción.
- **Métricas por sesión de usuario real.** Sería telemetría, con implicaciones de privacidad que el
  spec no aborda y que nadie pidió.
