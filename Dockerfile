FROM dolibarr/dolibarr:latest

COPY custom/ /tmp/custom/
COPY dolibarr-src/ /tmp/dolibarr-patches/

RUN set -eux; \
    cp -a /tmp/custom/. /var/www/html/custom/; \
    cp -a /tmp/dolibarr-patches/. /var/www/html/; \
    rm -rf /tmp/custom /tmp/dolibarr-patches
