# Known errors — skill `laravel-security-review`

Este archivo registra fallos reales de `security_check.py`: falsos positivos, falsos
negativos, o interpretaciones equivocadas de un hallazgo. No es un changelog de
features: solo entran acá casos concretos encontrados en uso real — no
anticipaciones de errores hipotéticos.

## Cómo agregar una entrada nueva

Cuando se detecte un fallo, agrega una entrada al final con este formato:

```
### <fecha ISO> — <resumen corto del fallo>

**Qué pasó:** descripción concreta (archivo/patrón de código que confundió al script).
**Por qué pasó:** causa raíz, si se conoce.
**Cómo evitarlo:** instrucción concreta para la próxima vez; si el patrón es
sistemático, ajusta directamente `scripts/security_check.py` en vez de solo
documentarlo acá.
```

---

### 2026-07-30 — Secreto con clave de array citada no se detectaba (falso negativo)

**Qué pasó:** al probar el script contra un fixture `config/services.php` con
`'secret' => 'sk_live_51H8xyz...'`, `SENSITIVE_LITERAL_RE` no lo detectó. El mismo
patrón sin comillas en la clave (`secret => '...'`) sí se detectaba.

**Por qué pasó:** el regex esperaba la palabra clave (`secret`, `password`, etc.)
seguida directo de espacio + `=>`/`=`, pero en PHP las claves de array casi siempre
van citadas (`'secret' =>`), así que había una comilla de cierre entre la palabra
clave y el operador que el regex no contemplaba.

**Cómo evitarlo:** corregido en el código — `SENSITIVE_LITERAL_RE` ahora acepta una
comilla opcional (`['"]?`) entre la palabra clave y `=>`/`=`. Si aparece un patrón de
citado distinto (backticks, comillas escapadas, la clave con espacios alrededor
antes de la comilla) que vuelva a producir un falso negativo, verificar primero con
un caso de prueba mínimo antes de tocar el regex — es fácil romper la detección de
casos que sí funcionaban.

### 2026-07-30 — `check_env_consistency` marcaba BCRYPT_ROUNDS/BROADCAST_CONNECTION como obsoletas (falso positivo)

**Qué pasó:** al correr `security_check.py` sobre el repo real, marcó `BCRYPT_ROUNDS`
y `BROADCAST_CONNECTION` (documentadas en `.env.example`) como "nada las usa" —
pero sí se usan, solo que no vía `config/*.php` del proyecto.

**Por qué pasó:** Laravel 11+ ya no publica un archivo `config/*.php` por cada
feature en el skeleton de la app — el framework trae sus propios defaults internos
en `vendor/laravel/framework/config/*.php` (`hashing.php` tiene
`env('BCRYPT_ROUNDS', 12)`, `broadcasting.php` tiene
`env('BROADCAST_CONNECTION', 'null')`) que se usan igual aunque el archivo nunca se
haya publicado a `config/`. El chequeo original solo miraba `config/` del proyecto,
así que no los veía.

**Cómo evitarlo:** corregido en el código — `check_env_consistency` ahora también
escanea `vendor/laravel/framework/config/*.php` (si existe) para el lado "¿alguien
la usa?" del chequeo de variables obsoletas (no para el aviso de "sin default y sin
documentar", que sigue mirando solo `config/` del proyecto). Si en el futuro
aparece otro caso de una variable "fantasma" marcada como obsoleta, confirmar primero
si Laravel la lee desde algún config interno del framework
(`grep -rn NOMBRE_VAR vendor/laravel/framework/config/`) antes de asumir que es un
bug del regex.
