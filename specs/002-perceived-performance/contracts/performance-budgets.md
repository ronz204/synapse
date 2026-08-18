# Contract — Presupuestos de rendimiento

**Feature**: [../spec.md](../spec.md) | **Date**: 2026-08-18

**Este archivo es la fuente de verdad.** `App\Support\Performance\PerformanceBudget` lo refleja; el
arnés de medición lo consume; las pruebas lo afirman. Si el código y esta tabla divergen, el código
está equivocado.

Cada presupuesto declara el criterio del spec que lo origina. No hay números sin procedencia.

---

## Presupuestos de tiempo

| ID | Clase | Métrica | Percentil | Techo | Origen |
|----|-------|---------|-----------|-------|--------|
| `B-01` | *(todas)* | Clic → primer cambio visual | 100 | 100 ms | SC-001 / FR-001 |
| `B-02` | `ModuleOpen` | Clic → contenido utilizable | 95 | 500 ms | SC-002 |
| `B-03` | `ModuleOpen` | Clic → contenido utilizable | 99 | 1.000 ms | SC-002 |
| `B-04` | `InModule` | Interacción → listado actualizado | 95 | 300 ms | SC-003 |
| `B-05` | `Write` | Pulsación → confirmación o rechazo visible | 95 | 1.000 ms | SC-004 |
| `B-06` | *(todas)* | Interacción → indicador de progreso visible, si aún no terminó | 100 | 3.000 ms | SC-005 |
| `B-07` | `AppBoot` | Ingreso → contenido operable, sin caché previa | 95 | 2.000 ms | SC-006 |

### Reglas de medición

Sin estas reglas los números de arriba son ambiguos y cada quien mediría otra cosa.

1. **Origen del cronómetro.** Se cuenta desde el evento de entrada del usuario (`pointerdown` para
   clic, expiración del retardo para entrada continua), no desde que arranca la petición de red.
2. **Entrada continua.** Para la búsqueda con escritura continua, `B-04` se mide **desde que la
   entrada se estabiliza** (expiración del retardo de 250 ms), no desde cada pulsación de tecla.
   Medirlo desde la tecla haría matemáticamente imposible cumplir `B-04` sin violar FR-008, que
   prohíbe consultar por tecla. Esta es una precisión de medición, no una relajación del techo.
3. **"Contenido utilizable"** significa que las filas del listado están pintadas y los controles
   responden. Un esqueleto de carga visible **no** cuenta como contenido utilizable — cuenta para
   `B-01`, que es otra cosa.
4. **Percentiles sobre repeticiones.** Mínimo 20 repeticiones por interacción. Un p95 sobre menos
   muestras no es un p95.
5. **Volumen.** Toda medición corre contra el volumen objetivo declarado en
   [../data-model.md](../data-model.md). Una medición sobre datos de demostración no es evidencia de
   cumplimiento (SC-007).
6. **Concurrencia.** `B-02` y `B-04` se verifican además con 30 sesiones concurrentes activas.
   El resto se mide con un solo usuario.
7. **Rechazos igual que éxitos.** `B-05` aplica idénticamente a una operación que termina en rechazo
   de dominio (ciclo, contradicción, prerrequisito inválido, modalidad sin resolución vigente). Un
   error nunca puede tardar más que un éxito (FR-011).

---

## Presupuestos estructurales

Estos no miden tiempo. Son las afirmaciones deterministas de la capa 1 del arnés: no dependen del
hardware, fallan de forma reproducible y son las que hacen que FR-013 sea verificable en CI.

| ID | Alcance | Límite | Por qué |
|----|---------|--------|---------|
| `S-01` | Consultas SQL por apertura de módulo | ≤ 10 | Corta de raíz cualquier N+1 reintroducido. Al volumen objetivo, un listado paginado necesita del orden de 3 |
| `S-02` | Consultas SQL por interacción dentro de módulo | ≤ 6 | Ordenar o paginar no debería costar más que la consulta del listado y su conteo |
| `S-03` | Filas serializadas en el payload de un listado en modo servidor | ≤ `perPage` | Es la definición de paginar. Si excede, el modo servidor no está realmente activo |
| `S-04` | Filas serializadas en el payload de un listado en modo cliente | ≤ 200 | Es el umbral de D-01. Si un catálogo en modo cliente lo supera, le toca migrar a modo servidor |
| `S-05` | Consultas SQL en el camino de escritura de una equivalencia | sin techo | **Deliberadamente ausente.** La detección de ciclos carga el grafo activo completo y debe seguir haciéndolo (Principio II, D-06). Poner un techo aquí invitaría a cachearlo |
| `S-06` | Imports de `Illuminate\` o `Livewire\` bajo `src/**/Domain` | 0 | Principio I. Se afirma como prueba, no como revisión manual |

`S-05` está listado precisamente porque su ausencia es una decisión, no un olvido.

---

## Cobertura obligatoria

El arnés debe cubrir, como mínimo:

**Aperturas de módulo** (`ModuleOpen`) — las 9 entradas de navegación autenticada: Dashboard, Planes
de Estudio, Cursos, Equivalencias, Modalidades, Asignaciones de Modalidad, Roles, Permisos,
Configuración.

**Interacciones dentro de módulo** (`InModule`) — por cada uno de los 7 listados: ordenar por una
columna, avanzar de página, buscar un término con resultados, buscar un término sin resultados.

**Escrituras** (`Write`) — como mínimo: crear un plan de estudio, guardar la estructura de un plan,
registrar una equivalencia válida, registrar una equivalencia **rechazada por ciclo**, registrar una
equivalencia **rechazada por contradicción**, asignar una modalidad sin resolución vigente
(rechazo), y solicitar una exportación PDF.

Los tres rechazos son obligatorios, no opcionales: FR-011 y `B-05` sólo se pueden verificar
midiéndolos.

**Cobertura declarada**: si una ejecución del arnés omite alguna interacción de esta lista, el
reporte debe decirlo explícitamente. Un reporte que calla lo que no midió se lee como cobertura
completa, y no lo es.

---

## Qué hace fallar una ejecución

Una ejecución del arnés emite `fail` si se cumple cualquiera de estas:

- Cualquier presupuesto de tiempo `B-0x` se excede en su percentil.
- Cualquier presupuesto estructural `S-0x` se excede.
- Cualquier interacción de la cobertura obligatoria no se pudo medir.
- Cualquier interacción empeora respecto de la línea base (SC-008).

No hay categoría de advertencia. Un presupuesto que sólo advierte deja de ser un presupuesto.
