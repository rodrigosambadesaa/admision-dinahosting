# PHP 8.3.36 fixes PHP2Shell CVE-2026-109618 / CVE-2026-109619.
# CLI only: do not expose this image as php-fpm, mod_php or php-cgi.
FROM php:8.3.36-cli-alpine

WORKDIR /workspace

# Defense in depth; fixed PHP itself is the essential remediation.
COPY docker/php-hardening.ini /usr/local/etc/php/conf.d/zz-php2shell-hardening.ini

# Bundle only the PHP exercise, not the full web root.
COPY --chown=www-data:www-data PHP/ /workspace/PHP/

USER www-data

CMD ["php", "PHP/fibonacci.php", "2026-06-01 00:00:00", "2026-06-30 23:59:59"]
