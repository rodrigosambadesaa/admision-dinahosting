# Prueba técnica Dinahosting

Solución completa de los tres ejercicios del PDF:

- `PHP/`: CLI en PHP con clases, herencia, interfaces y separación por responsabilidades.
- `JAVASCRIPT/`: formulario en Vanilla JS que calcula Fibonacci en la misma vista.
- `CSS/`: formulario de login en HTML5 + Bootstrap sin JavaScript.
- `compose.yaml`: dockerización para facilitar la comprobación de la solución implementada.

La portada [index.html](/C:/Users/rodri/OneDrive/Documentos/GitHub/admision_dinahosting/index.html) enlaza ya a los tres ejercicios, incluido [PHP/index.html](/C:/Users/rodri/OneDrive/Documentos/GitHub/admision_dinahosting/PHP/index.html) como acceso rápido a la solución CLI del ejercicio 1.

En ambos ejercicios de Fibonacci (`PHP` y `JAVASCRIPT`) el rango personalizado se normaliza automáticamente: funciona igual si la primera fecha es anterior, igual o posterior a la segunda.
Además, ambos soportan un modo de pruebas extremas con `ts:<bigint>` para trabajar con timestamps sintéticos arbitrariamente grandes. No hace falta que representen fechas reales ni físicamente plausibles; precisamente sirven para probar límites muy por encima de los enteros nativos e incluso de escalas mayores que la edad teórica del universo.
En `JAVASCRIPT` esos valores se procesan con una implementación propia de `BigInteger`, sin depender de `BigInt` nativo.

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

Abre [JAVASCRIPT/index.html](/C:/Users/rodri/OneDrive/Documentos/GitHub/admision_dinahosting/JAVASCRIPT/index.html) en navegador o usa Docker.

En el formulario puedes introducir:

- Fechas reales: `2026-06-01 00:00:00`
- Timestamps sintéticos extremos: `ts:123456789012345678901234567890`

### CSS

Abre [CSS/index.html](/C:/Users/rodri/OneDrive/Documentos/GitHub/admision_dinahosting/CSS/index.html) en navegador o usa Docker.
