---
name: "Tarea técnica"
about: "Trabajo del backlog que no es una historia de usuario (setup, refactor, infraestructura, spike)"
title: "[TASK] "
labels: []
assignees: ""
---

## Objetivo

Qué hay que hacer y por qué, en un par de frases. Es una tarea técnica (no una historia
de usuario) porque no tiene un beneficio directo y visible para pasajero/conductor —
por ejemplo, configurar el guard JWT, una migración, un ajuste de infraestructura o un
spike de investigación.

## Contexto
<!-- opcional -->



## Criterios de aceptación

- [ ] [condición verificable 1]
- [ ] [condición verificable 2]

## Notas técnicas
<!-- opcional -->

- Módulo: `Rides` | `Drivers` | `Vehicles` | `Payments` | `Auth` | `Realtime` | `Infraestructura`
- Referencias: enlaces relevantes, decisiones en `.claude/STANDARDS.md`.

## Definition of Done

- [ ] Implementado siguiendo `.claude/STANDARDS.md`.
- [ ] Tests donde aplique, en verde.
- [ ] `./vendor/bin/pint --test` sin errores.

**Estimación:** [talla o puntos]
