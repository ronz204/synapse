# Contract — Retroalimentación visual de la interfaz

**Feature**: [../spec.md](../spec.md) | **Date**: 2026-08-18

FR-001 a FR-004 y FR-009 a FR-012 describen cómo debe comportarse la interfaz mientras espera. Este
contrato los traduce a reglas comprobables, para que "responde de inmediato" deje de ser una
apreciación y pase a ser algo que se puede aprobar o rechazar mirando la pantalla.

Aplica a **todo** control interactivo de la aplicación autenticada. Un control nuevo que no lo
cumpla es un control incompleto.

---

## R-01 — Toda pulsación tiene acuse visual propio

**Regla**: al recibir `pointerdown`, el control cambia de aspecto dentro de los 100 ms, sin esperar
respuesta del servidor.

**Por qué se enuncia así**: `wire:current` marca la entrada activa de la barra lateral sólo *después*
de que la navegación completa. Es correcto para reflejar dónde estás; es insuficiente como acuse de
que el clic se registró. El acuse tiene que ocurrir en el mismo tick del evento, del lado del
navegador.

**Verificación**: capa de navegador del arnés, presupuesto `B-01`. Se mide el intervalo entre
`pointerdown` y la primera mutación de estilo o de DOM en el elemento pulsado o su contenedor.

---

## R-02 — Ninguna espera muestra pantalla en blanco ni pantalla congelada

**Regla**: si una transición no ha entregado contenido a los 100 ms, aparece un esqueleto con la
**geometría del contenido esperado** — encabezado de tabla, N filas del alto real, controles de
paginación. No un spinner, no un texto "Cargando…", no la pantalla anterior inmóvil.

**Detalle de implementación obligatorio**: el esqueleto se muestra con `wire:loading` acotado por
`wire:target` a la acción concreta, y con `wire:loading.delay` para que una respuesta rápida no
produzca un parpadeo. Un esqueleto que aparece y desaparece en 80 ms se percibe peor que ninguno.

**Verificación**: inspección visual guiada por [../quickstart.md](../quickstart.md), más una prueba
de que cada listado renderiza su esqueleto correspondiente.

---

## R-03 — La estructura persistente no parpadea

**Regla**: barra lateral, barra superior e identidad del usuario permanecen montadas y estables
durante toda transición entre módulos. No se reconstruyen, no pierden estado (colapsada/expandida,
grupos abiertos), no parpadean.

**Estado actual**: ya se cumple. La barra lateral usa el atributo `x-persist="sidebar"` sobre el
`<aside>`, y el layout documenta por qué se eligió el atributo en lugar de `@persist` (la directiva
introduce un `<div>` envolvente que rompe el `align-items: stretch` del contenedor flex). Esta regla
existe para que una futura refactorización no lo deshaga sin darse cuenta.

**Verificación**: prueba de navegador que comprueba que el nodo del `<aside>` es el mismo antes y
después de navegar entre dos módulos.

---

## R-04 — Una acción de escritura no se puede disparar dos veces

**Regla**: mientras una acción de escritura está en curso, su botón queda deshabilitado de forma
visible. Al terminar, se rehabilita — también si terminó en error.

**Por qué "de forma visible"**: Livewire ya descarta peticiones superpuestas del mismo componente,
así que el duplicado no llegaría a persistirse. Pero FR-004 no habla de la base de datos, habla de
que el usuario no vea un botón habilitado que no hace nada. La protección tiene que ser observable.

**Verificación**: capa determinista — dos invocaciones consecutivas de la acción producen un solo
registro; más una prueba de que el atributo `disabled` está presente durante la operación.

---

## R-05 — Un rechazo de dominio es tan rápido y tan visible como un éxito

**Regla**: los mensajes de ciclo detectado, contradicción de resoluciones, prerrequisito fuera del
plan y ausencia de resolución de modalidad vigente aparecen dentro del presupuesto `B-05`, con el
formulario intacto y los datos del usuario conservados.

**Restricción que no se negocia**: el contenido del mensaje no cambia. La cadena exacta del ciclo
(`A → B → C → A`) y el par de resoluciones en conflicto se siguen mostrando verbatim desde la capa
de dominio, tal como exige el Principio II de la constitución. Este requerimiento acelera la entrega
del mensaje; no lo simplifica, no lo resume y no lo reemplaza por un error genérico.

**Verificación**: obligatoria en la cobertura del arnés — `save:reject:cycle`,
`save:reject:contradiction`, `save:reject:no-modality-resolution`.

---

## R-06 — Una carga abandonada no puede pisar a la que la reemplazó

**Regla**: si el usuario pide el módulo A y antes de que llegue pide el módulo B, se muestra B. El
resultado tardío de A se descarta, y no queda ningún indicador de carga huérfano en pantalla.

**Verificación**: prueba de navegador que dispara dos navegaciones con menos de 100 ms de
separación y comprueba el contenido final y la ausencia de esqueletos activos.

---

## R-07 — Ningún indicador de carga es indefinido

**Regla**: toda espera tiene desenlace. Ante fallo de red, tiempo agotado o error del servidor, el
indicador se retira y aparece un mensaje comprensible con opción de reintentar. Nada queda girando
para siempre.

**Verificación**: prueba de navegador con la red simulada como caída durante una apertura de módulo.

---

## R-08 — Lo que no puede entregarse a tiempo se acusa de recibido

**Regla**: una operación cuyo resultado no cabe en su presupuesto —hoy, la exportación PDF— confirma
su aceptación de inmediato y entrega después. El usuario nunca queda esperando frente a un botón
pulsado sin saber si el sistema lo escuchó.

**Comportamiento concreto**: al solicitar la exportación, aparece de inmediato una confirmación de
que quedó en proceso; el estado se consulta por sondeo corto; cuando el archivo está listo se ofrece
la descarga. No hay canal de tiempo real en esta aplicación (`BROADCAST_CONNECTION=log`), y no hace
falta montarlo para esto.

**Verificación**: capa determinista — la acción de exportar retorna sin haber arrancado Chromium, y
encola un job.

---

## Regla transversal — Ninguna de estas reglas cambia un resultado funcional

Todo lo anterior es sobre presentación y momento. Ninguna de estas reglas puede alterar qué se
valida, qué se rechaza o qué se persiste (FR-016). Si una mejora de percepción exige relajar una
validación, la mejora se descarta — no la validación.
