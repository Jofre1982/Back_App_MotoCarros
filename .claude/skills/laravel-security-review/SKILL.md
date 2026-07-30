---
name: laravel-security-review
description: Revisión de seguridad dirigida al dominio de MotoYa (JWT, pagos, geolocalización de conductores/pasajeros) sobre código PHP/Laravel — mass assignment ($guarded = []), campos sensibles (password/token/jwt/etc.) expuestos por no estar en $hidden, acciones que mutan datos sin autorización visible, SQL crudo con argumentos dinámicos (posible inyección), secretos hardcodeados en el código fuente, y consistencia entre .env.example y las variables que config/*.php realmente usa. Usar esta skill siempre después de escribir o modificar un Model, un Controller con acciones que escriben datos, una variable de entorno nueva, o cualquier código que maneje autenticación/JWT/tokens/pagos, antes de dar por terminada una tarea de backend, o cuando el usuario pida "revisión de seguridad", "seguridad", "vulnerabilidades", "env", o pregunte si algo "es seguro". Complementa (no reemplaza) al skill genérico /security-review y a laravel-api-review (arquitectura/complejidad/tipos).
---

# Revisión de seguridad — Laravel API (MotoYa)

## Por qué esta skill existe

MotoYa maneja autenticación JWT, ubicación en tiempo real de personas, y (a futuro)
pagos — el tipo de dominio donde un mass assignment sin proteger, un campo sensible
expuesto en un JSON, o una acción de escritura sin autorización tienen consecuencias
reales, no solo estéticas. Esta skill automatiza una primera pasada de detección con
`scripts/security_check.py`, y deja un registro de sus propios errores en
[`KNOWN_ERRORS.md`](KNOWN_ERRORS.md) para mejorar con el uso.

**Depende de la skill `laravel-api-review`**: reusa su AST real de PHP
(`ast_dump.php`/`ast_client.py`) para no duplicar ese análisis — confirma que
`.claude/skills/laravel-api-review/` existe antes de correr esto.

**Importante:** esto es una primera pasada automatizada, no un pentest ni un
reemplazo del juicio humano/de Claude sobre el código. Ver "Limitaciones conocidas"
antes de tratar un resultado limpio como "sin problemas de seguridad".

## Flujo

1. **Decide qué revisar**: archivos que cambiaron
   (`git diff --name-only -- '*.php'`), o lo que el usuario pida explícitamente.
   Prioriza especialmente: Models nuevos/modificados, Controllers con acciones de
   escritura (POST/PUT/PATCH/DELETE), y cualquier código de autenticación/JWT/pagos.
2. **Revisa [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md)** antes de interpretar resultados.
3. **Corre el script**:
   ```bash
   python .claude/skills/laravel-security-review/scripts/security_check.py <ruta...>
   ```
   Sin argumentos analiza `app/` para mass assignment/campos expuestos/
   autorización/SQL dinámico, y `app/` + `config/` + `routes/` para secretos
   hardcodeados (deliberadamente no incluye `tests/`/`database/` por defecto — ahí
   es normal tener valores de prueba literales).
4. **Interpreta cada hallazgo con criterio**:
   - Mass assignment (`$guarded = []`) y campos sensibles expuestos son errores
     (`ERROR`) casi siempre reales — corrígelos.
   - "Acción mutante sin autorización visible" es un **aviso** (`AVISO`), no un
     error: el script solo mira el cuerpo del método, no sabe si la autorización
     está en un Form Request separado, un Policy resuelto automáticamente por route
     model binding, o middleware `can:` en `routes/api.php`. Antes de pedir un
     cambio, confirma dónde vive (o debería vivir) la autorización real.
   - SQL con argumento dinámico es una señal fuerte de riesgo real — verifica si el
     valor viene de input de usuario; si sí, es una inyección SQL real, no una
     hipótesis.
   - Secretos hardcodeados: confirma que no sea un falso positivo (un valor de test
     obviamente falso, una regla de validación) antes de tratarlo como una fuga real
     — pero si es un secreto real, es una emergencia (rotar la credencial), no solo
     un refactor.
   - Variable de `.env.example` "obsoleta" (`ERROR`) es confiable — el script ya
     considera los defaults internos de Laravel (`vendor/laravel/framework/config/`),
     así que si aparece, probablemente sí hay que quitarla (así se encontró y limpió
     `VITE_APP_NAME` al remover Vite del proyecto). Variable sin default y sin
     documentar (`AVISO`) es más ruidosa — Laravel declara muchas env() de drivers
     opcionales sin default donde no tener valor es válido; usa criterio.
5. **Si vas a corregir código**, hazlo con Edit/Write, y vuelve a correr el script
   para confirmar que el hallazgo desapareció.
6. **Si el usuario dice que un hallazgo es un falso positivo/negativo**, o detectas
   uno vos mismo, regístralo en [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md) con el formato
   que ya tiene el archivo. Si es sistemático, ajusta `scripts/security_check.py`
   directamente, no solo lo documentes.

## Limitaciones conocidas (leer antes de confiar en un resultado limpio)

- No reemplaza `/security-review` (el skill genérico de seguridad del sistema) para
  cosas fuera de este alcance específico (CSRF, cabeceras HTTP, configuración de
  CORS, rate limiting, etc.) — son complementarios, no usar solo uno.
- "Acción mutante sin autorización visible" es puramente sintáctico: si la
  autorización real vive en middleware de ruta (`Route::...->middleware('can:...')`)
  o en un Form Request separado, este script no lo ve y avisa igual — por diseño es
  un aviso, no un error, exactamente por esto.
- El detector de campos sensibles expuestos es una lista de palabras clave
  (password, token, secret, jwt, etc.) sobre el nombre del campo — un campo sensible
  con un nombre que no matchee esas palabras (ej. `document_number` para datos de
  identidad) no se detecta.
- El detector de secretos hardcodeados es un regex sobre texto plano: puede tener
  falsos positivos (un valor de test que por casualidad tiene 12+ caracteres
  alfanuméricos) y falsos negativos (un secreto partido en variables, ofuscado, o en
  un formato que el regex no anticipó). No es un reemplazo de un secret scanner real
  (ej. gitleaks, trufflehog) si en algún momento se necesita algo más exhaustivo.
- No analiza rutas fuera de PHP (`.env`, YAML, JSON) ni el historial de git.
- El chequeo de `.env.example` solo mira `config/*.php` del proyecto y los defaults
  internos de `vendor/laravel/framework/config/`; una env() usada directamente en
  `app/` (fuera de convención Laravel) no se ve.

## Referencia rápida de comandos

- Revisión completa (default `app/` + `config/` + `routes/` para secretos): `python .claude/skills/laravel-security-review/scripts/security_check.py`
- Solo una carpeta puntual: `python .claude/skills/laravel-security-review/scripts/security_check.py app/Models`
- Con rutas de secretos distintas: `python .claude/skills/laravel-security-review/scripts/security_check.py app --secrets-paths app config`
- Sin el chequeo de `.env.example`: `python .claude/skills/laravel-security-review/scripts/security_check.py --no-env-check`
