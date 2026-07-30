---
name: laravel-api-review
description: Revisa código PHP/Laravel de MotoYa contra las buenas prácticas de arquitectura de API REST definidas en .claude/STANDARDS.md — complejidad ciclomática, anidación excesiva, Controllers con lógica de negocio, Models con lógica que debería estar en una Action, Actions que conocen HTTP, SQL crudo disperso, y versionado de rutas. Usar esta skill siempre después de escribir o modificar un Controller, Model o Action en PHP, antes de dar por terminada una tarea de backend, o cuando el usuario pida "revisar" código, "buenas prácticas", "refactor", "complejidad", o si el código "sigue la arquitectura". También usarla si piden validar la arquitectura o complejidad de un archivo o carpeta específica.
---

# Revisión de arquitectura y complejidad — Laravel API (MotoYa)

## Por qué esta skill existe

`.claude/STANDARDS.md` define cómo debe estar organizado el código de este backend
(Actions para lógica de negocio, Controllers finos, Models sin lógica de negocio,
Actions sin conocimiento de HTTP, rutas versionadas). Es fácil que ese estándar se
erosione con el tiempo si nadie lo revisa activamente. Esta skill automatiza una
primera pasada de esa revisión con dos scripts en `scripts/`, y deja un registro de
sus propios errores en [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md) para mejorar con el uso.

**Importante:** los scripts son heurísticos (análisis basado en texto, no un AST real
de PHP). Son una señal, no un veredicto. Ver "Limitaciones conocidas" más abajo antes
de tratar un hallazgo como un hecho incuestionable.

## Flujo

1. **Decide qué revisar**: si el usuario acaba de pedir escribir/modificar código PHP,
   revisa los archivos que cambiaron (`git diff --name-only -- '*.php'` es un buen
   punto de partida). Si el usuario pide una revisión explícita de una carpeta o
   archivo, usa eso.
2. **Revisa [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md)** antes de interpretar resultados —
   ahí se acumulan patrones que estos scripts han marcado mal antes (falsos
   positivos/negativos conocidos), para no repetir la misma mala interpretación.
3. **Corre los dos scripts** sobre los archivos/carpetas relevantes:
   ```bash
   python .claude/skills/laravel-api-review/scripts/complexity_check.py <ruta...>
   python .claude/skills/laravel-api-review/scripts/architecture_check.py <ruta...>
   ```
   Si no se pasan rutas, `complexity_check.py` analiza `app/` completo y
   `architecture_check.py` analiza `app/` + `routes/api.php`.
4. **Interpreta cada hallazgo con criterio, no mecánicamente**:
   - Un método largo con complejidad alta casi siempre se beneficia de un guard
     clause / early return, o de extraerse a métodos privados más chicos, o (si es
     lógica de negocio real) a su propia Action.
   - Un Model marcado por complejidad puede ser un accessor legítimamente un poco
     elaborado — usa juicio, no fuerces mover todo a una Action si no tiene sentido.
   - Un Controller marcado por persistencia directa o validación inline sí es,
     casi siempre, una violación real del patrón Actions/Form Requests de este
     proyecto — proponer el refactor concreto (extraer una Action, crear el Form
     Request) en vez de solo señalar el problema.
5. **Si vas a corregir el código**, hazlo con Edit/Write como en cualquier tarea, y
   vuelve a correr los scripts para confirmar que el hallazgo desapareció.
6. **Si el usuario dice que un hallazgo es un falso positivo** (o si tú mismo
   detectas que un script marcó algo mal), regístralo en
   [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md) siguiendo el formato que ya tiene el archivo.
   Si el patrón es sistemático (no un caso aislado), ajusta directamente el script en
   `scripts/` en vez de solo documentarlo — el objetivo es que la herramienta mejore,
   no solo que quede anotado que falla.

## Limitaciones conocidas (leer antes de confiar ciegamente en un hallazgo)

- No es un parser real de PHP: no entiende expresiones `match` de PHP 8.1+, no cuenta
  operadores ternarios (`? :`, `?:`) como puntos de decisión, y maneja mal strings
  heredoc/nowdoc (`<<<EOT ... EOT`).
- Solo analiza métodos con nombre; no analiza closures anónimos ni arrow functions
  (`fn() => ...`), incluyendo los closures de rutas en `routes/*.php`.
- La "anidación" cuenta profundidad de `{ }` en general, así que un closure o una
  clase anónima dentro de un método suman a la profundidad igual que un `if` anidado.
- Las reglas de arquitectura se basan en la ruta del archivo (`Controllers/`,
  `Models/`, `Actions/`) y en coincidencias de texto (nombres de métodos, llamadas);
  un archivo fuera de esa estructura de carpetas no se categoriza y no se revisa con
  esas reglas.

## Referencia rápida de comandos

- Complejidad/anidación de todo `app/`: `python .claude/skills/laravel-api-review/scripts/complexity_check.py`
- Complejidad de un archivo puntual con umbrales propios: `python .claude/skills/laravel-api-review/scripts/complexity_check.py app/Actions/Rides/CreateRideAction.php --max-complexity 8 --max-nesting 2`
- Arquitectura de todo `app/` + rutas: `python .claude/skills/laravel-api-review/scripts/architecture_check.py`
- Arquitectura de una carpeta puntual: `python .claude/skills/laravel-api-review/scripts/architecture_check.py app/Http/Controllers`
