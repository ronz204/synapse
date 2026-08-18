# Feature Specification: Rendimiento Percibido Instantáneo

**Feature Branch**: `002-perceived-performance`

**Created**: 2026-08-18

**Status**: Draft

**Input**: User description: "Necesitamos mejorar el rendimiento del sistema al punto que sea impercebtible para el ojo humano, que al presionar algún módulo o botón el sistema cargue de forma rápida"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Abrir un módulo sin espera perceptible (Priority: P1)

Un usuario autenticado (Coordinador Académico, Director de Programa o Administrador) pulsa un
módulo en la navegación lateral — Planes de Estudio, Cursos, Equivalencias, Modalidades,
Acreditación, Roles o Permisos. La interfaz reacciona de inmediato: el elemento pulsado se marca
como activo al instante y el contenido del módulo aparece tan rápido que el usuario no percibe una
"carga" como un evento separado de su clic.

**Why this priority**: Es exactamente la queja de origen. La navegación entre módulos es la acción
más repetida del sistema y hoy es donde la espera se hace visible; si esto no mejora, ninguna otra
optimización cambia la percepción del usuario.

**Independent Test**: Se puede probar de forma aislada midiendo, sobre un conjunto de datos
representativo, el tiempo entre la pulsación de cada entrada de navegación y (a) la primera
reacción visual y (b) el momento en que el contenido del módulo es legible y utilizable,
verificando que ambos caen dentro de los presupuestos definidos.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado en el Dashboard, **When** pulsa el módulo "Planes de Estudio",
   **Then** la interfaz muestra una reacción visual al clic en 100 ms o menos, sin que la pantalla
   quede en un estado idéntico al previo.
2. **Given** un usuario autenticado en cualquier módulo, **When** pulsa otro módulo de la
   navegación, **Then** el contenido del módulo destino queda legible y operable dentro del
   presupuesto de apertura de módulo definido en Success Criteria.
3. **Given** una apertura de módulo que excepcionalmente excede el presupuesto de reacción
   inmediata, **When** el contenido aún no está listo, **Then** el usuario ve un estado de carga
   que ocupa la forma del contenido esperado, y nunca una pantalla en blanco ni la pantalla
   anterior congelada.
4. **Given** un usuario que vuelve a un módulo ya visitado en la misma sesión, **When** pulsa ese
   módulo, **Then** la apertura es al menos tan rápida como la primera vez.

---

### User Story 2 - Operar listados y tablas sin sensación de espera (Priority: P1)

Dentro de un módulo, el usuario ordena una columna, cambia de página, busca o aplica un filtro
sobre el listado (planes, cursos, equivalencias, modalidades, roles, permisos). Cada una de estas
acciones actualiza el listado sin que el usuario perciba una recarga de pantalla.

**Why this priority**: Los listados son la superficie principal de trabajo diario del sistema. Una
navegación de módulo rápida pierde todo su valor si cada filtro u ordenamiento dentro del módulo
vuelve a introducir una espera visible. Junto con User Story 1 forma el MVP de este requerimiento.

**Independent Test**: Se puede probar de forma aislada sobre un único módulo con listado, midiendo
el tiempo de ordenar, paginar, buscar y filtrar contra un volumen de datos representativo, y
verificando además que el resto de la pantalla (navegación, encabezado, filtros aplicados) no
parpadea ni se reconstruye.

**Acceptance Scenarios**:

1. **Given** un listado con datos al volumen objetivo, **When** el usuario ordena por una columna,
   **Then** el listado se actualiza dentro del presupuesto de interacción dentro de módulo y la
   navegación y los filtros permanecen visibles y estables.
2. **Given** un listado paginado, **When** el usuario avanza de página, **Then** el nuevo conjunto
   de filas aparece sin que la pantalla vuelva a construirse desde cero.
3. **Given** un usuario escribiendo en el campo de búsqueda, **When** teclea de forma continua,
   **Then** el sistema no ejecuta una búsqueda por cada tecla y la interfaz nunca se bloquea
   mientras escribe.
4. **Given** una acción de listado que devuelve cero resultados, **When** se completa, **Then** el
   estado vacío aparece dentro del mismo presupuesto que un resultado con datos.

---

### User Story 3 - Guardar y ejecutar acciones con respuesta inmediata (Priority: P2)

El usuario pulsa un botón de acción — guardar un plan, registrar una equivalencia, asignar una
modalidad, subir una resolución, exportar un listado, eliminar un registro. El botón responde de
inmediato, el usuario sabe en todo momento que el sistema está trabajando, y el resultado
(confirmación o mensaje de error) llega sin ambigüedad.

**Why this priority**: Depende de que la navegación y los listados ya sean rápidos para notarse, y
algunas de estas acciones tienen trabajo real e inevitable detrás (detección de ciclos en el grafo
de equivalencias, validación de prerrequisitos, procesamiento de PDF). El objetivo aquí no es
eliminar ese trabajo sino que la espera nunca sea silenciosa ni ambigua.

**Independent Test**: Se puede probar de forma aislada sobre el formulario de equivalencias,
disparando un guardado válido y uno rechazado por ciclo, y verificando el tiempo hasta la primera
reacción del botón, la imposibilidad de doble envío, y el tiempo hasta el mensaje final.

**Acceptance Scenarios**:

1. **Given** un formulario completo, **When** el usuario pulsa "Guardar", **Then** el botón acusa
   la pulsación en 100 ms o menos y queda deshabilitado hasta que la operación termina.
2. **Given** un guardado en curso, **When** el usuario vuelve a pulsar el mismo botón, **Then** el
   sistema no ejecuta la acción por segunda vez ni crea registros duplicados.
3. **Given** un guardado de equivalencia que el dominio rechaza por ciclo o por contradicción,
   **When** la validación termina, **Then** el mensaje de rechazo con la cadena del ciclo o el par
   de resoluciones en conflicto se muestra dentro del presupuesto de acción de escritura, sin
   perder los datos ya cargados en el formulario.
4. **Given** una acción que supera el presupuesto de acción de escritura, **When** sigue en curso,
   **Then** el usuario ve un indicador de progreso y el sistema no aparenta estar congelado.
5. **Given** una exportación de un listado extenso, **When** el usuario la solicita, **Then**
   recibe confirmación inmediata de que la exportación fue aceptada, aunque el archivo se entregue
   después.

---

### User Story 4 - Sostener el rendimiento en el tiempo (Priority: P3)

El equipo dispone de presupuestos de rendimiento explícitos y de una medición repetible que puede
ejecutar a demanda, de modo que una funcionalidad nueva que degrade la experiencia se detecte antes
de llegar a los usuarios y no meses después. La medición informa; no bloquea la integración de
cambios.

**Why this priority**: Aporta valor sólo una vez que existe algo rápido que proteger, pero sin esto
las mejoras de las historias P1 y P2 se erosionan con cada requerimiento nuevo.

**Independent Test**: Se puede probar de forma aislada introduciendo deliberadamente una regresión
de rendimiento en un módulo y verificando que la medición la reporta como incumplimiento del
presupuesto, identificando el módulo y la interacción afectados.

**Acceptance Scenarios**:

1. **Given** los presupuestos de rendimiento definidos, **When** se ejecuta la medición sobre el
   sistema, **Then** se obtiene un resultado por módulo e interacción que indica cumple / no cumple
   con su medición.
2. **Given** un cambio que degrada una interacción por encima de su presupuesto, **When** se
   ejecuta la medición, **Then** el incumplimiento se reporta señalando la interacción concreta.
3. **Given** un módulo nuevo incorporado al sistema, **When** se ejecuta la medición, **Then** ese
   módulo queda cubierto por los mismos presupuestos sin necesidad de definirlos otra vez.

---

### Edge Cases

- ¿Qué ocurre cuando el usuario pulsa un módulo y, antes de que cargue, pulsa otro distinto? El
  sistema debe mostrar el último módulo pedido y descartar el anterior, sin dejar contenido
  mezclado ni indicadores de carga huérfanos.
- ¿Qué ocurre cuando la conexión se degrada o se interrumpe a mitad de una carga? El usuario debe
  recibir un aviso claro y la posibilidad de reintentar, en lugar de un indicador de carga
  indefinido.
- ¿Cómo se comporta un listado cuyo volumen de datos crece muy por encima de lo previsto? El
  sistema debe seguir siendo operable acotando lo que muestra de una vez, nunca intentando
  presentar el conjunto completo.
- ¿Qué ocurre en la primera visita del día, sin nada previamente almacenado en el equipo del
  usuario? Es aceptable que esa primera carga sea más lenta que las siguientes, pero debe seguir
  dentro del presupuesto de carga inicial y no de una espera indefinida.
- ¿Qué ocurre cuando varios usuarios operan el sistema al mismo tiempo? Los presupuestos deben
  cumplirse con la concurrencia esperada, no sólo con un usuario aislado.
- ¿Qué ocurre si una acción de escritura falla por validación de dominio? El rechazo debe ser tan
  rápido y tan visible como una confirmación exitosa; un error nunca debe tardar más que un éxito.
- ¿Cómo se comporta el sistema para un usuario sin permiso sobre un módulo? El rechazo de acceso
  debe presentarse dentro del mismo presupuesto que una apertura permitida.

## Requirements *(mandatory)*

### Functional Requirements

#### Retroalimentación inmediata

- **FR-001**: El sistema MUST producir una reacción visual perceptible en 100 ms o menos ante toda
  pulsación de un elemento de navegación, botón o control interactivo, incluso cuando el resultado
  de la acción tarde más en estar listo.
- **FR-002**: El sistema MUST mantener visible y estable la estructura persistente de la pantalla
  (navegación lateral, encabezado, identidad del usuario) durante toda transición entre módulos,
  sin reconstruirla ni hacerla parpadear.
- **FR-003**: El sistema MUST mostrar, para cualquier espera que supere el umbral de percepción
  inmediata, un estado de carga que anticipe la forma del contenido que llegará, en lugar de una
  pantalla en blanco o la pantalla anterior congelada.
- **FR-004**: El sistema MUST impedir que una misma acción se ejecute dos veces por pulsaciones
  repetidas mientras la primera sigue en curso.

#### Presupuestos de rendimiento

- **FR-005**: El sistema MUST cumplir los presupuestos de tiempo definidos en Success Criteria para
  cada clase de interacción: apertura de módulo, interacción dentro de módulo, acción de escritura
  y carga inicial de la aplicación.
- **FR-006**: El sistema MUST cumplir esos presupuestos con el volumen de datos objetivo y la
  concurrencia esperada definidos en Assumptions, no únicamente con datos de demostración.
- **FR-007**: El sistema MUST acotar la cantidad de información que entrega de una sola vez en
  cualquier listado, de forma que el tiempo de respuesta no crezca con el total de registros
  almacenados.
- **FR-008**: El sistema MUST evitar que la escritura continua del usuario en un campo de búsqueda
  o filtro dispare una consulta por cada pulsación de tecla.

#### Comportamiento bajo espera y error

- **FR-009**: El sistema MUST informar al usuario, ante cualquier fallo o interrupción durante una
  carga, con un mensaje comprensible y una opción de reintento, sin dejar indicadores de carga
  activos de forma indefinida.
- **FR-010**: El sistema MUST descartar el resultado de una carga que el usuario ya abandonó al
  pedir otra, mostrando siempre el contenido correspondiente a la última acción solicitada.
- **FR-011**: El sistema MUST presentar los mensajes de rechazo de las validaciones de dominio
  (ciclos de equivalencia, contradicciones, prerrequisitos inválidos, ausencia de resolución de
  modalidad vigente) dentro del mismo presupuesto que una operación exitosa, conservando los datos
  ya ingresados por el usuario.
- **FR-012**: El sistema MUST confirmar de inmediato la aceptación de operaciones cuyo resultado no
  puede entregarse dentro del presupuesto (por ejemplo exportaciones extensas), aunque la entrega
  final ocurra después.

#### Medición y no regresión

- **FR-013**: El sistema MUST contar con una medición repetible, ejecutable a demanda por el
  equipo, que reporte por módulo y por clase de interacción si el presupuesto se cumple o no. Esta
  medición informa al equipo; no está requerido que bloquee la integración de cambios.
- **FR-014**: La medición MUST cubrir automáticamente los módulos nuevos que se incorporen, sin
  requerir la redefinición de presupuestos por módulo.
- **FR-015**: El equipo MUST poder identificar, ante un incumplimiento reportado, cuál módulo y
  cuál interacción concreta lo provocan.
- **FR-016**: Las mejoras de rendimiento MUST NOT alterar ningún resultado funcional ya
  especificado: las mismas entradas deben producir exactamente las mismas validaciones, rechazos y
  registros persistidos que antes de la optimización.

### Key Entities

- **Clase de interacción**: agrupación de acciones del usuario que comparten un mismo presupuesto
  de tiempo. Las cuatro clases son: carga inicial de la aplicación, apertura de módulo, interacción
  dentro de módulo y acción de escritura.
- **Presupuesto de rendimiento**: límite de tiempo máximo asociado a una clase de interacción,
  expresado como percentil sobre mediciones repetidas (no como un mejor caso aislado).
- **Medición de interacción**: registro de una ejecución observada — módulo, interacción, clase,
  tiempo hasta la primera reacción visual, tiempo hasta contenido utilizable y resultado
  cumple / no cumple.
- **Volumen de datos objetivo**: conjunto de datos representativo contra el cual deben verificarse
  todos los presupuestos, descrito en Assumptions.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100 % de las pulsaciones sobre módulos, botones y controles producen una reacción
  visual en 100 ms o menos.
- **SC-002**: El 95 % de las aperturas de módulo presentan contenido legible y operable en 500 ms o
  menos, y el 99 % en 1 segundo o menos.
- **SC-003**: El 95 % de las interacciones dentro de un módulo (ordenar, paginar, buscar, filtrar)
  actualizan el resultado en 300 ms o menos.
- **SC-004**: El 95 % de las acciones de escritura (guardar, asignar, eliminar) muestran su
  confirmación o su mensaje de rechazo en 1 segundo o menos.
- **SC-005**: Ninguna interacción del sistema supera los 3 segundos sin mostrar un indicador de
  progreso visible al usuario.
- **SC-006**: La carga inicial de la aplicación tras iniciar sesión, en un equipo sin nada
  previamente almacenado, presenta contenido operable en 2 segundos o menos.
- **SC-007**: Todos los criterios anteriores se cumplen con el volumen de datos objetivo
  (aproximadamente 10 planes, 800 cursos, 500 equivalencias, 300 asignaciones de modalidad y 2.000
  estudiantes con historial) y con 30 usuarios operando el sistema de forma simultánea.
- **SC-008**: Ninguna interacción del sistema empeora respecto de su medición previa a este
  trabajo; las cuatro clases de interacción mejoran su percentil 95.
- **SC-009**: El total de escenarios de aceptación ya especificados para los requerimientos
  funcionales existentes (RC-01, RC-02, RC-02b, RC-03) sigue pasando sin modificación tras las
  optimizaciones.
- **SC-010**: En una prueba con usuarios reales del sistema, al menos el 90 % describe la
  navegación entre módulos como inmediata y ninguno reporta esperas visibles al cambiar de módulo.

## Assumptions

- **Alcance de módulos**: quedan cubiertos todos los módulos accesibles desde la navegación del
  sistema autenticado — Dashboard, Planes de Estudio, Cursos, Equivalencias, Modalidades,
  Acreditación, Roles, Permisos y Configuración. Las pantallas de autenticación previas al ingreso
  (login, recuperación de contraseña, segundo factor) quedan fuera del alcance de este
  requerimiento.
- **Volumen de datos objetivo**: los presupuestos deben cumplirse contra el volumen de un programa
  académico real, entendido como aproximadamente 10 planes de estudio, 800 cursos, 500
  equivalencias registradas (activas y superseded), 300 asignaciones de modalidad y 2.000
  estudiantes con historial académico. Este es el conjunto contra el cual se verifica todo criterio
  de Success Criteria; los datos de demostración del proyecto no son suficientes como evidencia de
  cumplimiento. El catálogo institucional completo (varios programas simultáneos) queda fuera del
  alcance de este requerimiento.
- **Alcance de la no regresión**: este requerimiento incluye construir una medición repetible que
  el equipo pueda ejecutar a demanda y que reporte cumple / no cumple por módulo e interacción
  (User Story 4, FR-013 a FR-015). No incluye convertir esa medición en una compuerta automática
  que bloquee la integración de cambios; esa decisión queda para un requerimiento posterior.
- **Concurrencia esperada**: los presupuestos deben cumplirse con hasta 30 usuarios operando el
  sistema simultáneamente, cifra coherente con el cuerpo de coordinadores y personal académico de
  un programa universitario.
- **Condiciones de red y equipo**: se asume acceso desde la red institucional o una conexión de
  banda ancha típica, sobre equipos de escritorio o portátiles de gama media con navegador
  actualizado. El uso desde conexiones móviles degradadas no forma parte de los presupuestos,
  aunque el sistema debe seguir informando correctamente los fallos según FR-009.
- **Percepción humana como referencia**: "imperceptible al ojo humano" se traduce a los umbrales
  estándar de percepción — 100 ms para que una reacción se sienta causada por el propio clic, y
  aproximadamente 1 segundo para que el usuario no pierda el hilo de lo que estaba haciendo. Los
  presupuestos de Success Criteria derivan de esos umbrales.
- **Medición por percentiles**: el cumplimiento se evalúa sobre ejecuciones repetidas y percentiles,
  no sobre un mejor caso aislado, porque la percepción de lentitud la produce el caso malo
  ocasional y no el promedio.
- **Trabajo de dominio inevitable**: algunas operaciones tienen un costo intrínseco (detección de
  ciclos sobre el grafo de equivalencias, validación de prerrequisitos, procesamiento de archivos
  de resolución). Para ellas el objetivo es que la espera sea acotada, informada y nunca ambigua;
  no se asume que puedan reducirse a cero.
- **Integridad funcional por encima del rendimiento**: ninguna optimización puede debilitar las
  validaciones de dominio ni sus mensajes. En particular, la detección de ciclos y de
  contradicciones sigue siendo una verificación completa; no se admite acortarla, aproximarla ni
  omitirla por razones de velocidad.
- **Respeto de la arquitectura vigente**: las mejoras se realizan sin violar el aislamiento entre
  dominio y framework ni la comunicación a través de los puertos y contratos ya acordados; no se
  admite acceder directamente a la capa de persistencia desde la interfaz para ganar velocidad.
- **Preservación del historial**: cualquier estrategia de rendimiento sobre datos históricos
  (equivalencias superseded, acreditaciones, resoluciones de modalidad vencidas) mantiene esos
  registros consultables; no se admite descartarlos ni archivarlos fuera de alcance para acelerar
  consultas.
