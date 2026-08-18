# Feature Specification: Repositorio de Planes de Estudio (RC-01)

**Feature Branch**: `001-study-plan-repository`

**Created**: 2026-08-02

**Status**: Draft

**Input**: User description: "RC-01 - Repositorio de Planes de Estudio: sistema para almacenar planes de estudio por programa académico, con sus niveles, los cursos dentro de cada nivel, prerrequisitos entre cursos del mismo plan, y clasificación Vigente/Terminal. Los planes Terminal requieren obligatoriamente una fecha de cierre de matrícula. Antes de guardar, el sistema debe validar que todo curso citado como prerrequisito exista dentro del mismo plan (bloqueo, no advertencia). La vista debe mostrar la estructura completa (niveles → cursos → prerrequisitos) y la clasificación, con la fecha de cierre visible para planes Terminal. También debe mostrarse un indicador de estudiantes de prueba activos por plan y nivel."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear un plan de estudio nuevo (Priority: P1)

Un Director de Programa / Coordinador Académico selecciona un programa y crea un nuevo plan de
estudio, indicando su nombre, año de implementación y clasificación (Vigente o Terminal). Si el
plan es Terminal, el sistema exige una fecha de cierre de matrícula en el mismo formulario.

**Why this priority**: Sin poder crear un plan y clasificarlo, ninguna otra funcionalidad de este
requerimiento (ni de RC-02/RC-02b, que dependen de que existan cursos) tiene dónde apoyarse. Es la
base de todo el sistema.

**Independent Test**: Se puede probar de forma aislada creando un plan Vigente (sin fecha de
cierre) y un plan Terminal (con fecha de cierre obligatoria), y verificando que ambos se guardan
con la clasificación correcta.

**Acceptance Scenarios**:

1. **Given** un programa académico existente, **When** el coordinador crea un plan con
   clasificación "Vigente", **Then** el plan se guarda sin exigir fecha de cierre de matrícula.
2. **Given** un programa académico existente, **When** el coordinador crea un plan con
   clasificación "Terminal" y no indica fecha de cierre de matrícula, **Then** el sistema bloquea
   el guardado y señala que la fecha es obligatoria para planes Terminal.
3. **Given** un plan Terminal con fecha de cierre indicada, **When** el coordinador guarda el
   plan, **Then** el plan se persiste junto con su fecha de cierre de matrícula.

---

### User Story 2 - Estructurar el plan con niveles, cursos y prerrequisitos (Priority: P1)

Sobre un plan ya creado, el coordinador agrega niveles en orden, y dentro de cada nivel agrega
cursos (código, nombre, créditos). Luego registra prerrequisitos entre cursos que ya existen
dentro de ese mismo plan.

**Why this priority**: Es el contenido real del plan — sin niveles/cursos/prerrequisitos, la
"clasificación" del User Story 1 no tiene estructura curricular que respaldar. Es igual de crítico
que crear el plan, y ambos forman el MVP de RC-01.

**Independent Test**: Se puede probar de forma aislada creando un plan con dos niveles, dos cursos
por nivel, y un prerrequisito válido entre dos cursos del mismo plan; luego intentando registrar un
prerrequisito hacia un curso de otro plan o hacia un curso inexistente y confirmando que se
rechaza.

**Acceptance Scenarios**:

1. **Given** un plan con al menos un nivel y dos cursos en ese nivel, **When** el coordinador
   registra un prerrequisito entre esos dos cursos, **Then** el prerrequisito se guarda
   correctamente.
2. **Given** un plan con cursos únicamente en el Plan A, **When** el coordinador intenta registrar
   un prerrequisito que referencia un curso del Plan B, **Then** el sistema bloquea el guardado (no
   solo advierte) indicando que el curso no pertenece al mismo plan.
3. **Given** un formulario de prerrequisito, **When** el coordinador referencia un curso que aún no
   ha sido guardado en el plan, **Then** el sistema bloquea el guardado en lugar de descartarlo
   silenciosamente.

---

### User Story 3 - Visualizar la estructura completa de un plan (Priority: P2)

Cualquier usuario autorizado selecciona un plan existente y visualiza su estructura completa:
niveles, con sus cursos, y los prerrequisitos entre ellos, junto con la clasificación
Vigente/Terminal. Si el plan es Terminal, la fecha de cierre de matrícula se muestra junto a la
clasificación.

**Why this priority**: Depende de que exista un plan estructurado (User Stories 1 y 2), pero es el
punto de consulta que hace útil todo lo anterior — sin esta vista, la información capturada no es
consultable por quien la necesita.

**Independent Test**: Se puede probar de forma aislada seleccionando un plan ya poblado con datos
de prueba y verificando que la vista muestra niveles → cursos → prerrequisitos, la clasificación,
y (si aplica) la fecha de cierre.

**Acceptance Scenarios**:

1. **Given** un plan Vigente con niveles, cursos y prerrequisitos ya registrados, **When** el
   usuario abre la vista del plan, **Then** ve la estructura completa y la clasificación "Vigente",
   sin fecha de cierre.
2. **Given** un plan Terminal con fecha de cierre registrada, **When** el usuario abre la vista del
   plan, **Then** ve la clasificación "Terminal" junto con la fecha de cierre de matrícula visible.

---

### User Story 4 - Indicador de estudiantes de prueba activos por plan y nivel (Priority: P3)

El coordinador consulta, para un plan y nivel dados, cuántos estudiantes de prueba están
actualmente activos en ese nivel.

**Why this priority**: Es información de apoyo/demostración (los propios "estudiantes de prueba"
son datos simulados para fines de este proyecto), útil pero no bloqueante para las demás
funcionalidades de RC-01.

**Independent Test**: Se puede probar de forma aislada cargando datos de estudiantes de prueba
asociados a un nivel de un plan y verificando que el indicador refleja el conteo correcto.

**Acceptance Scenarios**:

1. **Given** un plan y nivel con estudiantes de prueba activos asociados, **When** el usuario
   consulta ese nivel, **Then** el indicador muestra el número correcto de estudiantes activos.
2. **Given** un nivel sin estudiantes de prueba asociados, **When** el usuario lo consulta,
   **Then** el indicador muestra explícitamente cero, no un espacio vacío o un error.

### Edge Cases

- ¿Qué pasa si se intenta cambiar la clasificación de un plan de "Terminal" a "Vigente" cuando ya
  tiene una fecha de cierre registrada? (fuera de alcance de este spec: no se especifica edición de
  clasificación, solo creación — ver Assumptions).
- ¿Qué pasa si un prerrequisito formaría un ciclo dentro del mismo plan (Curso A requiere B, B
  requiere A)? Este spec no impone esa validación explícitamente — la detección de ciclos como
  invariante dura de grafo pertenece a RC-02 (equiparencias entre planes), no a los prerrequisitos
  intra-plan de RC-01.
- ¿Qué pasa si se intenta guardar un nivel sin ningún curso? Se permite — un nivel puede crearse
  antes de poblarlo con cursos, siempre que la vista lo refleje como vacío, no como error.
- ¿Qué pasa si dos cursos del mismo plan tienen el mismo código? Debe bloquearse: el código de
  curso debe ser único dentro del plan, porque otros módulos (RC-02, RC-03) lo referencian como
  identificador.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir crear un plan de estudio indicando programa académico,
  nombre del plan, año de implementación y clasificación (Vigente o Terminal).
- **FR-002**: El sistema DEBE exigir una fecha de cierre de matrícula cuando la clasificación sea
  "Terminal", y bloquear el guardado si falta.
- **FR-003**: El sistema NO DEBE exigir ni mostrar una fecha de cierre de matrícula para planes
  "Vigente".
- **FR-004**: El sistema DEBE permitir agregar niveles ordenados a un plan existente.
- **FR-005**: El sistema DEBE permitir agregar cursos (código, nombre, créditos) dentro de un
  nivel de un plan.
- **FR-006**: El código de curso DEBE ser único dentro de un mismo plan.
- **FR-007**: El sistema DEBE permitir registrar un prerrequisito entre dos cursos, indicando cuál
  curso es requerido y cuál es el dependiente.
- **FR-008**: El sistema DEBE validar, antes de guardar, que tanto el curso requerido como el
  curso dependiente de un prerrequisito existan dentro del mismo plan.
- **FR-009**: El sistema DEBE bloquear (no solo advertir) el guardado de un prerrequisito que
  referencie un curso de otro plan o un curso inexistente.
- **FR-010**: El sistema DEBE mostrar, al consultar un plan, su estructura completa: niveles, los
  cursos de cada nivel, y los prerrequisitos entre esos cursos.
- **FR-011**: El sistema DEBE mostrar la clasificación (Vigente/Terminal) del plan junto a su
  estructura.
- **FR-012**: El sistema DEBE mostrar la fecha de cierre de matrícula junto a la clasificación
  cuando el plan sea Terminal.
- **FR-013**: El sistema DEBE mostrar, para un plan y nivel dados, un indicador con el número de
  estudiantes de prueba actualmente activos en ese nivel, incluyendo el caso de cero.

### Key Entities *(include if feature involves data)*

- **Plan de Estudio**: representa el currículo de un programa académico en un momento dado.
  Atributos: programa, nombre, año de implementación, clasificación (Vigente/Terminal), fecha de
  cierre de matrícula (solo si Terminal). Es el contenedor raíz de niveles y, más adelante, el
  punto de referencia que usan las equiparencias entre planes (RC-02).
- **Nivel**: una posición ordenada dentro de un plan (ej. "Nivel 1", "Nivel 2"). Pertenece a
  exactamente un plan y contiene una lista de cursos.
- **Curso**: una unidad curricular con código (único dentro del plan), nombre y créditos.
  Pertenece a exactamente un nivel de un plan. Es la entidad compartida que RC-02 (equiparencias),
  RC-02b (acreditación) y RC-03 (modalidad) referencian.
- **Prerrequisito**: una relación dirigida entre dos cursos del mismo plan — "curso requerido" →
  "curso dependiente". No puede cruzar planes.
- **Estudiante de Prueba (activo)**: un registro simulado usado únicamente para fines de
  demostración, asociado a un plan y nivel, que alimenta el indicador de estudiantes activos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un coordinador puede crear un plan completo (datos generales + al menos un nivel con
  cursos y un prerrequisito) en una sola sesión de trabajo, sin necesitar soporte técnico externo.
- **SC-002**: El 100% de los intentos de guardar un prerrequisito hacia un curso fuera del plan o
  inexistente son bloqueados — cero excepciones observadas en pruebas.
- **SC-003**: El 100% de los planes Terminal mostrados en la vista incluyen su fecha de cierre de
  matrícula visible junto a la clasificación; el 100% de los planes Vigentes la omiten.
- **SC-004**: Un usuario puede identificar la estructura completa de cualquier plan (niveles,
  cursos, prerrequisitos, clasificación) en una sola vista, sin tener que consultar pantallas
  adicionales.

## Assumptions

- La edición/cambio de clasificación de un plan ya creado (por ejemplo, de Vigente a Terminal) no
  está en el alcance de este spec; solo se especifica la clasificación al momento de creación. Si
  se requiere edición posterior, será una extensión futura.
- "Estudiantes de prueba" son datos simulados para fines de demostración de este proyecto
  académico; este spec no asume ninguna integración con un sistema oficial de registro estudiantil.
- El orden de los niveles dentro de un plan es significativo y se preserva tal como el coordinador
  los registra (no se re-ordenan automáticamente).
- Un plan puede tener niveles sin cursos, y niveles/cursos sin prerrequisitos — ninguno de estos
  casos es un estado inválido, solo una estructura parcial.
- La autenticación/autorización de quién puede crear o editar un plan reutiliza el sistema de auth
  ya existente en la aplicación (Fortify) y no se redefine en este spec.