---
name: laravel-api-review
description: Revisa código PHP/Laravel de MotoYa contra las buenas prácticas de arquitectura de API REST definidas en .claude/STANDARDS.md — complejidad ciclomática (AST real vía nikic/php-parser), anidación excesiva, Controllers con lógica de negocio, Models con lógica que debería estar en una Action, Actions que conocen HTTP, SQL crudo disperso, versionado de rutas, y análisis de tipos real con PHPStan/Larastan (composer stan). Usar esta skill siempre después de escribir o modificar un Controller, Model o Action en PHP, antes de dar por terminada una tarea de backend, o cuando el usuario pida "revisar" código, "buenas prácticas", "refactor", "complejidad", "tipos", "phpstan", o si el código "sigue la arquitectura". También usarla si piden validar la arquitectura o complejidad de un archivo o carpeta específica.
---

# Revisión de arquitectura y complejidad — Laravel API (MotoYa)

## Por qué esta skill existe

`.claude/STANDARDS.md` define cómo debe estar organizado el código de este backend
(Actions para lógica de negocio, Controllers finos, Models sin lógica de negocio,
Actions sin conocimiento de HTTP, rutas versionadas). Es fácil que ese estándar se
erosione con el tiempo si nadie lo revisa activamente. Esta skill automatiza una
primera pasada de esa revisión con dos scripts en `scripts/`, y deja un registro de
sus propios errores en [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md) para mejorar con el uso.

`scripts/complexity_check.py` y `scripts/architecture_check.py` corren sobre un AST
real de PHP (`nikic/php-parser`, ya instalado como dependencia transitiva del
proyecto) vía `scripts/ast_dump.php` — no son regex sobre texto. Aun así son una
señal, no un veredicto: entienden la sintaxis correctamente, pero las reglas de
arquitectura siguen siendo heurísticas sobre nombres de métodos y llamadas. Ver
"Limitaciones conocidas" antes de tratar un hallazgo como un hecho incuestionable.
Complementan (no reemplazan) a PHPStan/Larastan para tipos, y a la skill
`laravel-security-review` para seguridad — ver `.claude/STANDARDS.md`.

## Flujo

1. **Decide qué revisar**: si el usuario acaba de pedir escribir/modificar código PHP,
   revisa los archivos que cambiaron (`git diff --name-only -- '*.php'` es un buen
   punto de partida). Si el usuario pide una revisión explícita de una carpeta o
   archivo, usa eso.
2. **Revisa [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md)** antes de interpretar resultados —
   ahí se acumulan patrones que estos scripts han marcado mal antes (falsos
   positivos/negativos conocidos), para no repetir la misma mala interpretación.
3. **Corre los tres checks** sobre los archivos/carpetas relevantes:
   ```bash
   python .claude/skills/laravel-api-review/scripts/complexity_check.py <ruta...>
   python .claude/skills/laravel-api-review/scripts/architecture_check.py <ruta...>
   composer stan   # PHPStan/Larastan — análisis de tipos real, complementa lo anterior
   ```
   Si no se pasan rutas, `complexity_check.py` analiza `app/` completo y
   `architecture_check.py` analiza `app/` + `routes/api.php`. `composer stan` corre
   PHPStan a nivel 5 (`phpstan.neon`) sobre `app/` y `routes/` — detecta bugs de tipos
   (métodos inexistentes, tipos de retorno incorrectos) que ninguna heurística de
   `complexity_check.py`/`architecture_check.py` puede ver, así que no es opcional
   cuando hay código PHP nuevo.
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

- Requiere `php` en el PATH y `composer install` ya corrido (usa el
  `nikic/php-parser` instalado en `vendor/`). Sin eso, los scripts salen con
  exit 2 y un mensaje explicando qué falta — no fallan en silencio.
- La complejidad/anidación de un método **incluye** lo que pasa dentro de closures y
  arrow functions definidos en su cuerpo (porque ejecutarlos es parte de ejecutar el
  método), pero esos closures no se reportan como entradas separadas — así que un
  método con un closure grande puede aparecer con un número alto sin que quede claro
  a simple vista que el problema está "adentro" del closure, no en el método en sí.
- Las reglas de arquitectura (persistencia en Controllers, SQL crudo, HTTP en
  Actions, etc.) siguen siendo heurísticas: matchean nombres de método/clase
  (`::create`, `->save`, `DB::raw`, tipos de parámetro) sin resolver a qué clase
  pertenecen realmente en tiempo de ejecución — un método `save()` de una clase que
  no es un Eloquent Model daría un falso positivo si viviera en un Controller.
- Se basan en la ruta del archivo (`Controllers/`, `Models/`, `Actions/`) para decidir
  qué reglas aplicar; un archivo fuera de esa estructura de carpetas no se categoriza
  y no se revisa con esas reglas.
- No resuelven herencia/traits entre archivos: un método heredado de una clase base
  en otro archivo no se ve al analizar el archivo hijo.

## Referencia rápida de comandos

- Complejidad/anidación de todo `app/`: `python .claude/skills/laravel-api-review/scripts/complexity_check.py`
- Complejidad de un archivo puntual con umbrales propios: `python .claude/skills/laravel-api-review/scripts/complexity_check.py app/Actions/Rides/CreateRideAction.php --max-complexity 8 --max-nesting 2`
- Arquitectura de todo `app/` + rutas: `python .claude/skills/laravel-api-review/scripts/architecture_check.py`
- Arquitectura de una carpeta puntual: `python .claude/skills/laravel-api-review/scripts/architecture_check.py app/Http/Controllers`
