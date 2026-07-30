---
name: github-backlog-issue
description: Crea historias de usuario y tareas técnicas para el backlog de MotoYa gestionado en un GitHub Project, usando como formato canónico los templates en .github/ISSUE_TEMPLATE/ y validando el borrador con scripts/validate_issue.py antes de proponer publicarlo. Usar esta skill siempre que el usuario pida crear una historia de usuario, un issue de backlog, una tarea técnica, o describa una funcionalidad/trabajo pendiente que debería quedar registrado como issue en GitHub — aunque no diga literalmente "issue" o "historia de usuario" (ej. "necesitamos poder cancelar un viaje", "hay que agregar la migración de vehículos", "anota esto para el sprint", "esto es más un chore que una historia"). También usarla si piden validar si un issue ya creado cumple el formato del backlog.
---

# Crear issues de backlog (historias de usuario / tareas técnicas) — MotoYa

## Por qué esta skill existe

El backlog de MotoYa se gestiona desde un GitHub Project, y todos los issues deben
seguir un formato consistente para que sean comparables, priorizables y fáciles de
convertir en trabajo real. La fuente de verdad del formato **no es esta skill**, son
los templates nativos de GitHub en
[`.github/ISSUE_TEMPLATE/`](../../../.github/ISSUE_TEMPLATE/):

- `user-story.md` — funcionalidad con valor directo y visible para un rol de
  usuario (pasajero, conductor).
- `technical-task.md` — trabajo técnico sin esa narrativa de usuario (setup, refactors,
  infraestructura, spikes, migraciones).

Esta skill rellena esos templates con contenido real para el pedido del usuario; no
inventa una estructura distinta ni la memoriza. Si los templates cambian con el tiempo,
esta skill debe seguir el template actualizado.

## Flujo

1. **Lee el template correspondiente** en `.github/ISSUE_TEMPLATE/` antes de escribir
   nada — no reconstruyas el formato de memoria, puede haber cambiado desde la última vez.
2. **Decide el tipo**: si hay un beneficio directo para un rol de usuario, es
   `user-story`; si es trabajo técnico sin esa narrativa (migraciones,
   configuración, refactor, spike), es `technical-task`. Ante la duda, pregunta al
   usuario en vez de asumir — el tipo determina el template completo.
3. **Revisa [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md)** (en esta misma carpeta)
   antes de redactar — ahí se acumulan fallos reales que esta skill ha cometido antes,
   para no repetirlos.
4. **Rellena el template** con contenido específico y verificable, no genérico:
   - En "Criterios de aceptación", escribe condiciones concretas (Dado/cuando/entonces
     o un checklist claro), nunca algo tipo "que funcione bien".
   - En "Notas técnicas", referencia las convenciones de
     [`.claude/STANDARDS.md`](../../STANDARDS.md) cuando aplique (Actions, Policies,
     JWT, Reverb, módulo de dominio afectado: Rides, Drivers, Vehicles, Payments,
     Auth, Realtime).
   - No dejes ningún placeholder del template sin rellenar en el borrador final (nada
     de `[rol]`, `[beneficio]`, `[condición verificable]`, etc.).
5. **Guarda el borrador** en un archivo temporal (por ejemplo en el directorio de
   scratchpad de la sesión) y **valídalo**:
   ```bash
   python .claude/skills/github-backlog-issue/scripts/validate_issue.py \
     --type <user-story|technical-task> --title "<título>" <archivo-borrador.md>
   ```
   Si falla, corrige el borrador según los errores reportados y vuelve a validar. No
   muestres al usuario un borrador que no haya pasado la validación — si un error del
   script no tiene sentido para el caso concreto, dilo explícitamente en vez de
   ignorarlo en silencio.
6. **Muestra el borrador completo** (título + cuerpo, tal como quedaría el issue) al
   usuario en el chat.
7. **Pide confirmación explícita antes de crear el issue en GitHub.** Esto no es
   opcional ni se puede asumir de un "sí" anterior en la conversación: publicar
   contenido en un repositorio compartido siempre requiere confirmación en el turno
   actual. Si el usuario pide varios issues a la vez, se puede pedir una sola
   confirmación para el lote, pero cada borrador completo debe mostrarse antes de esa
   confirmación.
8. **Al confirmar, créalo** con `gh issue create`, por ejemplo:
   ```bash
   gh issue create --title "[US] Solicitar un viaje" --body-file borrador.md
   ```
   Antes de pasar `--label`, revisa las labels que realmente existen con
   `gh label list` — **no asumas** que labels como `user-story` o
   `technical-task` ya están creadas (hoy el repo solo tiene las labels por defecto de
   GitHub). Proponer crear labels nuevas es una acción aparte (`gh label create`
   modifica configuración compartida del repo) que también requiere confirmación
   explícita del usuario antes de ejecutarse.

## Cuando el usuario reporta un fallo

Si el usuario corrige algo que esta skill generó mal — una sección con contenido vago,
un tipo de issue mal elegido, un criterio de aceptación no verificable, algo que
`validate_issue.py` no detectó pero era claramente un mal issue — regístralo en
[`KNOWN_ERRORS.md`](KNOWN_ERRORS.md) siguiendo el formato que ya tiene el
archivo. La idea es que la próxima vez que se invoque esta skill, ese error puntual ya
no se repita. Si el fallo es sistemático, considera además ajustar este `SKILL.md` o
`scripts/validate_issue.py`, no solo documentarlo.

## Referencia rápida de comandos

- Validar un borrador: `python .claude/skills/github-backlog-issue/scripts/validate_issue.py --type user-story borrador.md`
- Validar un issue ya creado en GitHub: `python .claude/skills/github-backlog-issue/scripts/validate_issue.py --issue 42`
- Ver labels existentes: `gh label list`
- Crear el issue: `gh issue create --title "..." --body-file borrador.md [--label ...]`
