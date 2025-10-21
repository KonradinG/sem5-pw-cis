FROM postgres:latest

LABEL org.opencontainers.image.title="postgres-hardened"
LABEL org.opencontainers.image.description="Postgres mit aktualisierten OpenSSL-Paketen und ohne XSLT-Toolchain"
LABEL org.opencontainers.image.source="https://github.com/konrading/sem5-pw-cis"
LABEL org.opencontainers.image.licenses="PostgreSQL"

ENV DEBIAN_FRONTEND=noninteractive
#Test
RUN set -eux; \
    apt-get update; \
    apt-get install -y --only-upgrade \
        openssl \
        libssl3t64 \
        openssl-provider-legacy \
        || true; \
    apt-get purge -y \
        libxslt1.1 \
        xsltproc \
        libxml2-utils \
        || true; \
    apt-get autoremove -y; \
    rm -rf /var/lib/apt/lists/*; \
    dpkg-query -W openssl libssl3t64 openssl-provider-legacy || true; \
    ! dpkg -s libxslt1.1 >/dev/null 2>&1 || (echo "WARN: libxslt1.1 noch installiert" && exit 1)
