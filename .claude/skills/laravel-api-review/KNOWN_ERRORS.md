# Known errors — skill `laravel-api-review`

Este archivo registra fallos reales de `complexity_check.py` y
`architecture_check.py` al analizar código PHP real: falsos positivos, falsos
negativos, o interpretaciones equivocadas de un hallazgo. No es un changelog de
features: solo entran acá casos concretos encontrados en uso real — no
anticipaciones de errores hipotéticos.

## Cómo agregar una entrada nueva

Cuando se detecte un fallo, agrega una entrada al final con este formato:

```
### <fecha ISO> — <resumen corto del fallo>

**Qué pasó:** descripción concreta (archivo/patrón de código que confundió al script).
**Por qué pasó:** causa raíz, si se conoce (referencia a la heurística responsable).
**Cómo evitarlo:** instrucción concreta para la próxima vez; si el patrón es
sistemático, ajusta directamente `scripts/php_lite.py`,
`scripts/complexity_check.py` o `scripts/architecture_check.py` en vez de solo
documentarlo acá.
```

---

### 2026-07-30 — Limitaciones heurísticas conocidas desde el diseño inicial

**Qué pasó:** al construir `php_lite.py` se identificaron de antemano varios casos
que el análisis basado en texto (sin AST real) no maneja bien: expresiones `match`
de PHP 8.1+ y operadores ternarios (`? :`, `?:`) no cuentan como puntos de decisión
en `cyclomatic_complexity()`; strings heredoc/nowdoc (`<<<EOT ... EOT`) no se
"vacían" correctamente en `strip_comments_and_strings()`; closures anónimos y arrow
functions (`fn() => ...`) no se detectan en `parse_methods()`, así que su
complejidad/anidación nunca se mide; y `max_nesting_depth()` cuenta profundidad de
`{ }` en general, sin distinguir un `if` anidado de un closure o clase anónima que
también abre llaves.

**Por qué pasó:** son limitaciones deliberadas del diseño v1 (parsing con regex +
conteo de llaves, no un parser real de PHP) para mantener el script simple y sin
dependencias externas.

**Cómo evitarlo:** si un método usa `match`, ternarios, heredoc, o tiene lógica
relevante dentro de closures/arrow functions, no confíes en el número que reportan
los scripts para ese método — revísalo manualmente. Si este tipo de código se vuelve
común en el proyecto (por ejemplo, empezamos a usar `match` seguido en Actions), vale
la pena extender `php_lite.py` para soportarlo en vez de seguir documentando el
límite acá.
