# Contract — Formato del reporte de medición

**Feature**: [../spec.md](../spec.md) | **Date**: 2026-08-18

FR-015 exige que, ante un incumplimiento, el equipo pueda identificar **qué módulo** y **qué
interacción concreta** lo provocan. Eso convierte al formato del reporte en un contrato: si la forma
cambia entre ejecuciones, la comparación contra la línea base (SC-008) deja de ser posible.

Dos representaciones de los mismos datos: JSON para comparar entre ejecuciones, tabla en consola
para leer.

---

## Representación canónica (JSON)

Escrita en `storage/app/performance/report-<timestamp>.json`. La línea base se versiona aparte, en
`specs/002-perceived-performance/baseline.json`.

```json
{
  "takenAt": "2026-08-18T14:32:10-06:00",
  "layer": "browser",
  "repetitions": 20,
  "volume": {
    "programs": 2,
    "studyPlans": 10,
    "courses": 800,
    "prerequisites": 1200,
    "equivalencies": 500,
    "modalityAssignments": 300,
    "students": 2000,
    "academicRecords": 24000
  },
  "concurrency": 1,
  "verdict": "fail",
  "rows": [
    {
      "module": "equivalencies",
      "interaction": "open",
      "class": "ModuleOpen",
      "budget": "B-02",
      "percentile": 95,
      "observedMs": 1840,
      "maxMs": 500,
      "verdict": "fail",
      "firstPaintMs": 62,
      "queryCount": 1003,
      "serializedRows": 500
    },
    {
      "module": "courses",
      "interaction": "sort:code",
      "class": "InModule",
      "budget": "B-04",
      "percentile": 95,
      "observedMs": 118,
      "maxMs": 300,
      "verdict": "pass",
      "firstPaintMs": 41,
      "queryCount": 3,
      "serializedRows": 10
    }
  ],
  "notMeasured": [
    { "module": "modality-assignments", "interaction": "export:pdf", "reason": "queue worker not running" }
  ]
}
```

### Reglas del formato

1. **`rows` es exhaustivo sobre lo medido.** Una interacción medida aparece, cumpla o no. Un reporte
   que sólo lista fallos no permite comparar contra la línea base.
2. **`notMeasured` nunca se omite.** Si está vacío, va como `[]`. Un reporte sin este campo se lee
   como cobertura completa, y el punto de tenerlo es que no se pueda mentir por omisión.
3. **`verdict` de nivel superior es `fail`** si cualquier fila es `fail` **o** si `notMeasured`
   contiene alguna interacción de la cobertura obligatoria definida en
   [performance-budgets.md](./performance-budgets.md).
4. **Campos nulos según la capa.** La capa determinista no observa pintado: `firstPaintMs` va
   `null`. La capa de navegador no ve SQL: `queryCount` y `serializedRows` van `null`. Nunca se
   rellenan con ceros — un cero afirmaría que se midió y dio cero.
5. **`observedMs` es el percentil, no una muestra.** El campo `percentile` dice cuál.
6. **Identificadores estables.** `module` e `interaction` son claves, no texto para humanos. Si
   cambian, la comparación contra la línea base se rompe en silencio. Cambiarlos obliga a regenerar
   la línea base y a decirlo en el diario de decisiones.

### Vocabulario de `interaction`

| Patrón | Significado |
|--------|-------------|
| `open` | Apertura del módulo desde la barra lateral |
| `sort:<columna>` | Ordenar por esa columna |
| `paginate:next` / `paginate:prev` / `paginate:<n>` | Cambio de página |
| `search:hit` / `search:miss` | Búsqueda con resultados / sin resultados |
| `filter:<campo>` | Aplicación de un filtro |
| `save:create` / `save:update` | Guardado exitoso |
| `save:reject:<motivo>` | Guardado rechazado por dominio (`cycle`, `contradiction`, `prerequisite`, `no-modality-resolution`) |
| `export:pdf` / `export:excel` | Solicitud de exportación |
| `boot` | Carga inicial tras iniciar sesión |

---

## Representación en consola

Lo que imprime `php artisan perf:measure`. Los mismos datos, ordenados por severidad: los
incumplimientos primero, porque son lo que se va a actuar.

```text
Medición de rendimiento — 2026-08-18 14:32:10
Capa: navegador | Repeticiones: 20 | Concurrencia: 1
Volumen: 800 cursos · 500 equivalencias · 2.000 estudiantes

INCUMPLE (2)
  módulo            interacción      clase        p95        techo    exceso
  equivalencies     open             ModuleOpen   1.840 ms   500 ms   +268 %
  equivalencies     search:hit       InModule       410 ms   300 ms    +37 %

CUMPLE (34)
  courses           open             ModuleOpen     287 ms   500 ms
  courses           sort:code        InModule       118 ms   300 ms
  ...

NO MEDIDO (1)
  modality-assignments  export:pdf   — queue worker not running

Comparación con la línea base (baseline.json):
  mejoran 31 · sin cambio 3 · EMPEORAN 2   <-- SC-008 incumplido

Veredicto: FAIL
```

### Reglas de la salida en consola

1. **Los incumplimientos van primero**, con el exceso en porcentaje. Saber que algo tarda 1.840 ms
   es menos accionable que saber que va casi al triple de su techo.
2. **La sección "NO MEDIDO" se imprime siempre**, incluso vacía, por la misma razón que el campo
   JSON: el silencio se lee como éxito.
3. **La comparación contra la línea base es parte del reporte**, no un paso aparte. SC-008 es un
   criterio de aceptación como cualquier otro y debe verse en la misma pantalla.
4. **Código de salida**: `0` si `pass`, `1` si `fail`. Así el reporte sirve como paso de verificación
   sin envoltorio adicional, aunque D-02 de las clarificaciones dejó fuera de alcance convertirlo en
   compuerta automática.
