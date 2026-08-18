# Phase 0 — Research: Rendimiento Percibido Instantáneo

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Date**: 2026-08-18

Nueve decisiones. Cada una nace de un hallazgo verificado en el código, no de una buena práctica
genérica. Las alternativas descartadas se registran porque varias son la opción que uno tomaría por
reflejo, y conviene que quede escrito por qué no.

---

## D-01 — Modo servidor sólo para los catálogos que crecen, no para los siete

**Decisión**: introducir un umbral explícito. Un listado usa `tableMode = 'server'` cuando su
catálogo puede superar **200 filas** al volumen objetivo; por debajo de eso conserva
`tableMode = 'client'`.

Reparto concreto:

| Módulo | Filas al volumen objetivo | Modo | Cambio |
|--------|---------------------------|------|--------|
| Cursos | ~800 | servidor | sí |
| Equivalencias | ~500 | servidor | sí |
| Asignaciones de modalidad | ~300 | servidor | sí |
| Permisos | ~50 | cliente | no |
| Planes de estudio | ~10 | cliente | no |
| Modalidades | ~10 | cliente | no |
| Roles | ~10 | cliente | no |

**Justificación**: el modo cliente no es un error, es un intercambio. Paga un payload grande una
vez y a cambio ordena, filtra y pagina en cero milisegundos, sin red. A 10 o 50 filas ese
intercambio es claramente favorable y migrar esos módulos los volvería *más lentos*, no menos: cada
ordenamiento pasaría a costar un viaje al servidor donde hoy cuesta nada. A 800 filas el
intercambio se invierte: el payload domina la apertura del módulo, que es exactamente la
interacción que el spec prioriza (User Story 1).

El umbral de 200 filas sale de dónde se cruzan las dos curvas. Alrededor de 200 filas la
serialización JSON de una tabla de este ancho ronda los 40 KB, comparable al bundle de CSS completo
de la aplicación (41 KB); por encima de eso el payload empieza a dominar el tiempo de apertura,
mientras que el viaje al servidor que lo reemplaza se mantiene holgadamente dentro del presupuesto
de 300 ms para interacciones dentro de módulo.

La infraestructura ya existe: `InteractsWithDataTable` implementa ambos modos, `paginate()` está
implementado en los cinco repositorios Eloquent, y `renderServerMode()` ya está escrito en cada
componente. Hoy es código muerto. Esto lo activa; no lo inventa.

**Precisión necesaria sobre la búsqueda**: en modo servidor, escribir en el buscador dispara una
petición. FR-008 prohíbe una consulta por tecla, así que la entrada se estabiliza con un retardo de
250 ms antes de disparar. Eso implica que el presupuesto de 300 ms de SC-003 se mide **desde que la
entrada se estabiliza**, no desde cada pulsación — de otro modo el propio retardo exigido por FR-008
haría imposible cumplir SC-003. Queda formalizado en
[contracts/performance-budgets.md](./contracts/performance-budgets.md).

**Alternativas consideradas**:

- *Migrar los siete listados a modo servidor.* Descartada: empeora medibles cuatro módulos para
  arreglar tres. Uniformidad por uniformidad.
- *Dejar los siete en modo cliente y sólo optimizar las consultas.* Descartada: no ataca el
  problema. El payload de 800 filas viaja igual aunque se resuelva en una sola consulta.
- *Virtualización de filas en el navegador (renderizar sólo lo visible).* Descartada: reduce el
  costo de pintado pero no el del payload, que es el dominante aquí, y agrega una dependencia de
  JavaScript que hoy no existe.

---

## D-02 — El N+1 de Equivalencias se resuelve en lote, en la capa de presentación

**Decisión**: `EquivalencyComponent::toRow()` deja de consultar por fila. Antes de mapear, se
recolectan todos los `source_course_id`/`target_course_id` de la página y se resuelven sus códigos
en una consulta; lo mismo con los `superseded_by_id` y sus números de resolución. Dos consultas por
página, no dos por fila.

**Justificación**: es el hallazgo más caro del diagnóstico (H2) y el más barato de arreglar. El
código actual documenta que la lectura de códigos de curso es un acoplamiento pragmático deliberado
entre agregados de un mismo contexto; esa decisión no cambia. Lo único que cambia es *cuándo* se
hace la lectura: una vez por página en lugar de una vez por fila.

Combinado con D-01, el efecto compuesto es el más grande del plan: de ~1.000 consultas por render
del listado completo, a 3 consultas por página de 10 filas.

**Alternativas consideradas**:

- *Mover la resolución de códigos al repositorio como proyección.* Descartada por Principio V: es
  un contrato nuevo entre Presentation y Application que habría que acordar antes, y la mejora no
  lo necesita. Se puede reconsiderar después, con la medición ya hecha.
- *Eager loading vía `with()` en el repositorio.* Descartada: obligaría al repositorio a devolver
  modelos Eloquent en lugar de entidades de dominio, rompiendo el aislamiento que el repositorio
  mantiene hoy.

---

## D-03 — Retroalimentación inmediata: estado presionado propio + esqueletos con forma

**Decisión**: tres mecanismos, cada uno atacando un requisito distinto.

1. **Estado presionado inmediato (FR-001)**. `wire:current` marca la entrada activa recién *después*
   de que la navegación completa, así que no sirve como reacción inmediata. Se agrega un manejador
   Alpine en la barra lateral que marca la entrada pulsada en el mismo tick del clic, y se limpia
   con `livewire:navigated`.
2. **Esqueletos con la forma del contenido (FR-003)**. Un componente `<x-ui.skeleton>` que replica
   la geometría de la tabla (encabezado, N filas, paginador), mostrado con `wire:loading` acotado
   por `wire:target` a la acción concreta, con `wire:loading.delay` para que una respuesta rápida no
   provoque un parpadeo de esqueleto.
3. **Botones que se protegen solos (FR-004)**. `wire:loading.attr="disabled"` con `wire:target`
   sobre cada acción de escritura. Livewire ya cancela peticiones superpuestas del mismo
   componente, pero eso no impide que el usuario vea el botón habilitado y vuelva a pulsarlo, que
   es lo que FR-004 exige evitar de forma visible.

**Justificación**: el spec distingue entre *ser* rápido y *parecer* rápido, y trata la segunda como
requisito de primer orden. Estas tres piezas son las que cubren el caso en que el trabajo real no
puede reducirse más — que es exactamente lo que ocurre con la detección de ciclos.

**Alternativas consideradas**:

- *Un spinner global.* Descartada: FR-003 pide explícitamente un estado que anticipe la forma del
  contenido. Un spinner centrado no informa nada sobre lo que está por llegar y se percibe más
  lento que un esqueleto por la misma espera.
- *Actualizaciones optimistas (pintar el resultado antes de confirmarlo).* Descartada de plano para
  escrituras: en Equivalencias el resultado puede ser un rechazo por ciclo o contradicción, y
  mostrar un éxito que después se revierte es peor que esperar. Contradiría el Principio II.

---

## D-04 — La exportación PDF sale de la petición HTTP

**Decisión**: `exportPdf()` deja de generar el archivo en línea. Encola un job que lo genera y lo
deja en disco; el componente confirma la aceptación de inmediato y consulta el estado con
`wire:poll` hasta que el archivo está disponible, momento en que ofrece la descarga.

**Justificación**: `SpatiePdfExporter::fromHtml()` arranca un Chromium completo con Browsershot
dentro del ciclo de petición. Ese arranque cuesta segundos y no depende del tamaño de los datos, así
que ninguna optimización de consultas lo toca. SC-005 prohíbe superar 3 segundos sin indicador, y
FR-012 pide exactamente este patrón: confirmar la aceptación de inmediato aunque la entrega ocurra
después.

El costo de infraestructura es nulo: `QUEUE_CONNECTION=database` ya está configurado, la tabla
`jobs` ya existe por migración, y `composer dev` ya levanta `queue:listen` junto al servidor.

`BROADCAST_CONNECTION=log` significa que no hay canal de tiempo real, por lo que la notificación de
"archivo listo" se resuelve por sondeo corto y no por websocket. Es la opción correcta aquí: sondear
cada dos segundos durante una exportación puntual es despreciable, y montar broadcasting sería
infraestructura nueva para un caso que no la necesita.

**Alternativas consideradas**:

- *Mantener Chromium caliente entre peticiones.* Descartada: proceso residente, gestión de ciclo de
  vida y una fuente nueva de fallos, para un caso de uso ocasional.
- *Reemplazar Browsershot por un generador de PDF en PHP puro.* Descartada: cambia la salida visual
  de un reporte ya aprobado y toca una dependencia sin aprobación previa.
- *Encolar sólo si la exportación supera N filas.* Descartada: el costo dominante es el arranque de
  Chromium, que es constante. Una exportación de 10 filas tarda casi lo mismo que una de 500, así
  que el umbral no separaría nada útil.

---

## D-05 — Arnés de medición en dos capas

**Decisión**: dos capas complementarias, con roles distintos y explícitos.

**Capa 1 — determinista, en Pest** (`tests/Feature/Performance/`). Con el volumen objetivo cargado,
cada módulo se ejercita vía `Livewire::test()` y se afirma:

- número de consultas SQL por interacción (contadas con `DB::listen`),
- número de filas serializadas en el payload,
- tiempo de render de servidor, contra un techo holgado.

Las dos primeras son afirmaciones duras: no dependen de la máquina y fallan de forma reproducible.
El tiempo es sensible al hardware, así que se **registra siempre** pero se afirma sólo contra un
techo generoso, para que la suite detecte una regresión de orden de magnitud sin volverse
intermitente.

**Capa 2 — perceptual, en navegador real** (`php artisan perf:measure`). Un comando que conduce
Chromium con Puppeteer, inicia sesión, y por cada módulo repite el clic N veces midiendo con la
Performance API el intervalo clic → primer cambio visual y clic → contenido utilizable. Reporta p95
por módulo e interacción.

**Justificación**: FR-013 pide un resultado cumple / no cumple reproducible, y FR-015 pide poder
señalar el módulo y la interacción culpables. La capa 1 da reproducibilidad; la capa 2 da la métrica
que el spec realmente enuncia (percepción humana). Ninguna sola cumple ambas cosas.

Puppeteer ya está en `package.json` como dependencia de Browsershot, así que la capa 2 no agrega
dependencias — reutiliza el Chromium que la aplicación ya necesita para exportar PDF.

**Alternativas consideradas**:

- *Sólo la capa de navegador.* Descartada: su variabilidad la vuelve inservible como criterio de
  aprobación. Un p95 que oscila con la carga de la máquina no puede decidir si un cambio pasa.
- *Sólo la capa Pest.* Descartada: puede aprobar un módulo con 3 consultas que igual se siente lento
  por payload, JavaScript o pintado. Mide el servidor, no la percepción.
- *Laravel Pulse o Telescope.* Descartada: ninguno está instalado, ambos observan producción en
  lugar de dar un veredicto por ejecución, y Telescope añade sobrecarga que distorsiona justo lo que
  se intenta medir.

---

## D-06 — El grafo de equivalencias no se cachea entre peticiones

**Decisión**: `EloquentEquivalencyRepository::activeGraph()` **no** recibe caché persistente de
ningún tipo. Se permite únicamente memoizar el resultado dentro de una misma petición, cuando la
misma petición lo necesita más de una vez.

**Justificación**: esta es la restricción más importante del plan y merece estar escrita antes de
que alguien la proponga de buena fe. `activeGraph()` es la entrada de la detección de ciclos. Un
grafo servido desde una caché que no refleja la última equivalencia registrada permitiría persistir
un ciclo — exactamente lo que el Principio II declara sagrado, y con consecuencias reales para un
estudiante.

La invalidación correcta sería posible en teoría (invalidar en cada escritura de equivalencia), pero
el margen de error no vale la ganancia: al volumen objetivo son 500 aristas, una consulta indexada
por `status` que ya existe (`equivalencies_status_index`), y ocurre sólo en el camino de escritura,
que tiene un presupuesto de 1 segundo y no de 300 ms.

Si en el futuro el grafo creciera hasta hacer de esto un cuello real, el camino correcto es hacer la
detección de ciclos incremental dentro del dominio, no cachear su entrada desde la infraestructura.

**Alternativas consideradas**:

- *Cachear con invalidación en escritura.* Descartada por lo anterior: el modo de fallo es
  silencioso y grave.
- *Cachear con TTL corto.* Descartada: un TTL convierte la integridad del grafo en una cuestión de
  suerte temporal.

---

## D-07 — Sacar la caché de aplicación de MySQL, y `optimize` en el arranque

**Decisión**: dos cambios de configuración, en este orden de prioridad.

1. **`php artisan optimize` como paso obligatorio de despliegue** (config, rutas y vistas
   cacheadas). Riesgo cero, beneficio inmediato en la sobrecarga por petición, aplicable ya.
2. **`CACHE_STORE` fuera de `database`**: `redis` si el despliegue efectivamente lo provee — está
   declarado en `.env` pero ningún driver lo usa hoy, así que hay que confirmar que responde —, y
   `file` como alternativa si no. Cualquiera de los dos es mejor que competir con las consultas de
   negocio por las mismas conexiones a MySQL.

`SESSION_DRIVER` se deja en `database` a propósito. Es una lectura indexada por petición, es la
opción correcta si algún día hay más de una instancia, y moverla no aparece entre los hallazgos como
un costo relevante. Cambiarla sería tocar algo que funciona sin evidencia de que estorbe.

**Justificación**: H5 no es el hallazgo más caro, pero sí el más barato de corregir, y afecta a
todas las peticiones por igual — incluidas las de apertura de módulo, que son las que el spec
prioriza.

**Alternativas consideradas**:

- *Mover también sesión y cola a Redis.* Descartada por ahora: sin evidencia de que sean un cuello,
  y agrega dependencia dura de Redis en el camino de autenticación. Se puede reconsiderar con
  medición en mano.

---

## D-08 — Evaluar y adoptar `livewire/blaze` donde rinda, con medición antes y después

**Decisión**: tratar la adopción de Blaze como una tarea con criterio de aceptación medible, no como
un cambio dado por bueno. Se mide un componente de listado antes y después; se conserva si la mejora
es reproducible en la capa 1 del arnés, y se revierte si no lo es.

**Justificación**: `livewire/blaze` ^1.0 está instalado y auto-descubierto, pero ningún componente
ni vista lo utiliza. Es una optimización de render disponible y sin explotar, lo que la hace
atractiva; pero adoptarla a ciegas en siete componentes por reputación del paquete es justo el tipo
de cambio que después nadie sabe justificar en la defensa oral. Con el arnés ya construido, medirla
cuesta poco y el resultado es defendible en cualquier dirección.

Por eso esta decisión depende de D-05: no se ejecuta antes de que exista la línea base.

**Alternativas consideradas**:

- *Adoptarlo en todos los componentes de entrada.* Descartada: cambio no medido en siete archivos.
- *Ignorarlo.* Descartada: dejar sin usar una optimización ya instalada y pagada es difícil de
  sostener cuando el requerimiento entero trata de rendimiento.

---

## D-09 — Semilla de volumen determinista y reutilizable

**Decisión**: un `PerformanceVolumeSeeder` que genera exactamente el volumen objetivo del spec, con
semilla de aleatoriedad fija, idempotente, e invocable tanto desde las pruebas de la capa 1 como
desde el entorno local para la medición en navegador.

**Justificación**: SC-007 exige que todo criterio se verifique al volumen objetivo, y SC-008 exige
comparar contra una línea base. Ninguna de las dos cosas es posible si el conjunto de datos cambia
entre ejecuciones. Los seeders existentes (`CurriculumDemoSeeder`, `EquivalencyDemoSeeder`,
`ModalityAssignmentDemoSeeder`) sirven para explorar la UI a mano y su propio docblock advierte que
ejecutarlos dos veces duplica los datos — comportamiento incorrecto para medir.

Los factories necesarios ya existen los dieciocho, así que el seeder es composición, no trabajo
nuevo de modelado.

**Alternativas consideradas**:

- *Reutilizar los seeders de demostración con un multiplicador.* Descartada: no son idempotentes y
  su volumen no es declarativo.
- *Volcar un dump SQL fijo.* Descartada: se desincroniza en silencio en cuanto cambia una migración.

---

## Resumen de dependencias entre decisiones

```text
D-09 (semilla de volumen)
  └─> D-05 (arnés de medición)  ──> línea base  ──> SC-008 / SC-009 verificables
        ├─> D-01 (modo servidor por umbral)
        ├─> D-02 (fin del N+1 en Equivalencias)
        ├─> D-07 (caché fuera de MySQL + optimize)
        └─> D-08 (evaluación de Blaze)

D-03 (retroalimentación inmediata)  ── independiente, se puede avanzar en paralelo
D-04 (exportación encolada)         ── independiente, se puede avanzar en paralelo
D-06 (prohibición de cachear el grafo) ── restricción, no tarea
```

El orden importa: **D-09 y D-05 van primero**. Optimizar antes de tener la línea base deja SC-008 y
SC-009 sin forma de demostrarse, y ese es un incumplimiento del spec tan real como no cumplir un
presupuesto de tiempo.
