---
name: sdd
description: "Usar al analizar, especificar, planificar o verificar una historia de usuario de SFATSA mediante desarrollo guiado por especificaciones (SDD), antes de implementar o al revisar su cumplimiento."
---

# SDD para SFATSA

## Flujo

1. **Analizar fuentes:** leer `AGENTS.md`, `docs/constitution.md` y la documentación aprobada de la HU y su sprint en `Documentos/`. Revisar requisitos, casos de uso, modelo de datos y wireframes disponibles; señalar los artefactos faltantes.
2. **Aclarar contradicciones:** identificar las fuentes y la diferencia concreta, y solicitar aclaración al usuario. No decidir qué versión es correcta; suspender el trabajo que dependa de esa decisión y continuar solo con lo independiente y autorizado.
3. **Especificar:** describir alcance, requisitos verificables y criterios de aceptación con identificadores. Usar EARS cuando aporte claridad, por ejemplo: «Cuando [evento], el sistema deberá [respuesta observable]». Derivar cada requisito de una fuente aprobada; registrar preguntas pendientes.
4. **Planificar:** proponer una solución acorde con las tres capas, MVC, servicios y repositorios; identificar datos, permisos, dependencias, riesgos y verificaciones aplicables. No cambiar el modelo de datos aprobado sin autorización.
5. **Dividir en tareas:** definir tareas pequeñas, vinculadas a los criterios de aceptación, con resultado esperado y forma de verificación.
6. **Implementar con aprobación:** presentar especificación, plan y tareas para revisión. Implementar únicamente cuando la autorización del usuario cubra el alcance y los entregables pertinentes. Crear una especificación no autoriza su implementación.
7. **Verificar:** comprobar los criterios de aceptación y las garantías pertinentes de seguridad, datos y concurrencia. Registrar evidencia, resultados, limitaciones y pendientes; no declarar verificado lo que no se comprobó.

## Organización y entregables sugeridos

- Organizar las especificaciones por sprint y HU según las asignaciones aprobadas; acordar la ubicación antes de crear archivos y respetar la estructura existente.
- Referenciar las fuentes de `Documentos/` mediante ruta e identificador; no duplicar los documentos académicos.
- Cuando la HU lo requiera y su creación esté autorizada, proponer:
  - `spec.md`: alcance, fuentes, requisitos, criterios de aceptación y dudas.
  - `plan.md`: solución técnica, dependencias, riesgos y estrategia de verificación.
  - `tasks.md`: tareas, relación con criterios de aceptación y estado.
- Mantener trazabilidad con una tabla: HU | CU | requisito | criterio de aceptación | tarea | prueba | resultado. Usar los identificadores existentes y marcar vínculos pendientes.

## Límites

- No inventar requisitos, wireframes, relaciones de datos ni asignaciones de sprint.
- No modificar `Documentos/`, modelos de datos o especificaciones aprobadas sin autorización.
- No instalar herramientas, dependencias ni MCP, ni realizar commits, push o merges sin autorización.
- La invocación de esta skill no amplía el alcance autorizado ni crea automáticamente especificaciones o código.
