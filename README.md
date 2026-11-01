# Prueba técnica Dinahosting

Solución completa de los tres ejercicios del PDF:

- `PHP/`: CLI en PHP con clases, herencia, interfaces y separación por responsabilidades.
- `JAVASCRIPT/`: formulario en Vanilla JS que calcula Fibonacci en la misma vista.
- `CSS/`: formulario de login en HTML5 + Bootstrap sin JavaScript.
- `compose.yaml`: dockerización para facilitar la comprobación de la solución implementada.

La portada [index.html](index.html) enlaza ya a los tres ejercicios, incluido [PHP/index.html](PHP/index.html) como acceso rápido a la solución CLI del ejercicio 1.

En ambos ejercicios de Fibonacci (`PHP` y `JAVASCRIPT`) el rango personalizado se normaliza automáticamente: funciona igual si la primera fecha es anterior, igual o posterior a la segunda.
Además, ambos soportan un modo de pruebas extremas con `ts:<bigint>` para trabajar con timestamps sintéticos arbitrariamente grandes. No hace falta que representen fechas reales ni físicamente plausibles; precisamente sirven para probar límites muy por encima de los enteros nativos e incluso de escalas mayores que la edad teórica del universo.
En `JAVASCRIPT` esos valores se procesan con una implementación propia de `BigInteger`, sin depender de `BigInt` nativo.

## Pruebas automatizadas

El workflow **Functional tests and web smoke** ejecuta pruebas funcionales de PHP (8.3, 8.4 y 8.5), JavaScript (Node 22 y 24), validación estática de HTML y una prueba HTTP real de Nginx. La comprobación de la versión de PHP sigue separada en **PHP critical runtime security**.

Ejecutar localmente sin instalar dependencias de terceros:

```bash
php tests/php_test.php
node --test tests/javascript.test.cjs
python3 -m unittest discover -s tests -p 'test_*.py'
```

En ambas implementaciones, los valores sintéticos aceptan hasta **256 dígitos**; se conserva la precisión arbitraria para cálculos mayores que los enteros nativos, con un límite práctico de entrada para evitar denegación de servicio. El formulario HTML/CSS es solo una demostración sin backend: **no utilice credenciales reales** y no espere autenticación al enviarlo.

## Seguridad

Se han aplicado medidas de hardening inspiradas en OWASP Top 10 en las superficies entregadas:

- Validación estricta de entradas en `PHP` y `JAVASCRIPT`, rechazando formatos inesperados y reduciendo riesgo de abuso de entrada.
- Eliminación de renderizado inseguro con `innerHTML` en la parte JS, sustituyéndolo por construcción segura del DOM para minimizar XSS.
- Content Security Policy en las páginas y también en Nginx para limitar orígenes permitidos de scripts, estilos, fuentes, formularios y frames.
- Cabeceras `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy` y `Cross-Origin-Resource-Policy`.
- Restricción de `form-action` para el login, permitiendo envío únicamente al destino esperado.
- Desactivación práctica de capacidades no necesarias del navegador y de superficies embebibles para reducir exposición.

Estas medidas no convierten un entregable estático en una aplicación completamente securizada de extremo a extremo, pero sí dejan una base bastante más robusta y alineada con buenas prácticas reales.

## Requisitos

- Docker y Docker Compose

## Comprobación rápida con Docker

Levantar la parte web:

```bash
docker compose up web
```

Después abre [http://localhost:8087](http://localhost:8087).

Si el puerto `8087` ya está ocupado, puedes usar otro:

```bash
WEB_PORT=8081 docker compose up web
```

En PowerShell:

```powershell
$env:WEB_PORT=8081; docker compose up web
```

Y abrir [http://localhost:8081](http://localhost:8081).

Ejecutar el ejercicio PHP:

```bash
docker compose run --rm php-cli php PHP/fibonacci.php "2026-06-01 00:00:00" "2026-06-30 23:59:59"
```

## Ejecución local sin Docker

### PHP

```bash
php PHP/fibonacci.php "2026-06-01 00:00:00" "2026-06-30 23:59:59"
```

La salida se muestra en JSON con:

- Rango del mes actual en UTC
- Rango del año actual en UTC
- Rango personalizado en UTC
- Timestamps Fibonacci comprendidos en cada rango

También puedes probar un rango sintético extremo:

```bash
php PHP/fibonacci.php "ts:123456789012345678901234567890" "ts:123456789012345678901234568500"
```

En este modo:

- No se interpreta una fecha real de calendario.
- Se trata cada valor como un timestamp sintético de precisión arbitraria.
- La salida devuelve los límites y los Fibonacci encontrados como cadenas para evitar pérdidas de precisión.

### JavaScript

Abre [JAVASCRIPT/index.html](JAVASCRIPT/index.html) en navegador o usa Docker.

En el formulario puedes introducir:

- Fechas reales: `2026-06-01 00:00:00`
- Timestamps sintéticos extremos: `ts:123456789012345678901234567890`

### CSS

Abre [CSS/index.html](CSS/index.html) en navegador o usa Docker.


## PHP2Shell (octubre de 2026)

**PHP2Shell no es un fallo del algoritmo de Fibonacci**: son errores de seguridad del propio intérprete PHP. CVE-2026-109618 y CVE-2026-109619 pueden provocar corrupción de memoria y ejecución remota de código en servicios PHP vulnerables. Véase el [aviso oficial de PHP](https://www.php.net/archive/2026.php#2026-10-21-5).

La **solución es actualizar el runtime PHP**. Versiones mínimas publicadas el 21 de octubre de 2026: 8.2.35, 8.3.36, 8.4.27 y 8.5.12; no deben usarse ramas antiguas sin soporte.

En este repositorio se aplican cinco garantías:

1. **PHP 8.3.36 CLI corregido.** Docker utiliza explícitamente el parche correspondiente; Actions compila con --pull y compara la versión real de la imagen frente a las versiones mínimas seguras.
2. **PHP no es accesible desde HTTP.** El servidor web usa Nginx únicamente para los siete recursos HTML, JavaScript y CSS publicados por los ejercicios. No hay Apache/mod_php, php-fpm, php-cgi ni configuración FastCGI. El resto de las rutas, incluidos los archivos fuente PHP, devuelven 404, y las peticiones multipart POST no se procesan.
3. **Contenedor PHP aislado.** Solo copia el directorio PHP y su configuración, ejecuta como usuario no privilegiado y el servicio Compose no dispone de red, monta el sistema de archivos como solo lectura, elimina capacidades, prohíbe escalada de privilegios y limita los procesos.
4. **Configuración PHP defensiva.** El archivo docker/php-hardening.ini desactiva session.upload_progress.enabled, file_uploads, expose_php y display_errors, cargándolo antes del inicio del intérprete. No sustituye los parches y no cubre por sí solo todas las vías de explotación.
5. **Verificación continua.** GitHub Actions comprueba la versión y las opciones de PHP efectivamente cargadas, la ejecución del CLI en el sandbox, el bloqueo HTTP de código fuente, el rechazo multipart y el funcionamiento de los archivos públicos.

Tras actualizar la aplicación, reconstruye la imagen PHP; el código ya se incluye en la imagen y no se monta desde el host:

~~~bash
docker compose build --pull php-cli
docker compose run --rm php-cli php PHP/fibonacci.php "ts:0" "ts:21"
docker compose up -d web
~~~

Si despliegas una instancia diferente de PHP como servidor web, **debes actualizarla y reiniciar sus procesos** (PHP-FPM, Apache o similar). Comprueba la versión expuesta en el servidor y no solo la versión del ejecutable CLI. La mitigación adicional de desactivar session.upload_progress.enabled a nivel del servidor antes del análisis de la petición no sustituye el parche; tampoco lo hace un WAF.

El memory_limit=128M del CLI limita recursos **después de aplicar el parche**. No constituye una mitigación para CVE-2026-109618 en un intérprete vulnerable: un límite finito de memoria puede incluso contribuir a activar la ruta defectuosa.
