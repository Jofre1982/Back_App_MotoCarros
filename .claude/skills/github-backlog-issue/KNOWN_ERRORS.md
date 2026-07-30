# Errores conocidos — skill `github-backlog-issue`

Este archivo registra fallos reales que la skill ha cometido al generar historias de
usuario o tareas técnicas, para que no se repitan. No es un changelog de features:
solo entran acá errores concretos detectados en uso real (por el usuario, o
encontrados por `validate_issue.py` después del hecho) — no anticipaciones de errores
hipotéticos.

## Cómo agregar una entrada nueva

Cuando se detecte un fallo, agrega una entrada al final con este formato:

```
### <fecha ISO> — <resumen corto del fallo>

**Qué pasó:** descripción concreta del error (con ejemplo si ayuda).
**Por qué pasó:** causa raíz, si se conoce.
**Cómo evitarlo:** instrucción concreta y accionable para la próxima vez.
```

Si el fallo es sistemático (no un descuido puntual), considera además ajustar
`SKILL.md` o `scripts/validate_issue.py` para que quede prevenido estructuralmente, no
solo documentado acá.

---

_Todavía no hay errores registrados. Cuando el usuario corrija algo que esta skill
generó, documéntalo acá siguiendo el formato de arriba._
