FROM postgres:18.2

LABEL org.opencontainers.image.title="postgres-secured"
LABEL org.opencontainers.image.description="Postgres mit aktualisierten OpenSSL-Paketen und ohne XSLT-Toolchain"
LABEL org.opencontainers.image.licenses="PostgreSQL"
LABEL org.opencontainers.image.pipeline-test="pr-security-compare-trigger"

ENV DEBIAN_FRONTEND=noninteractive

RUN set -eux; \
    apt-get update; \
    # (A) sicherheitsrelevante Updates (best effort)
    apt-get install -y --only-upgrade \
        openssl \
        libssl3t64 \
        openssl-provider-legacy \
        || true; \
    # (B) XSLT-Stack entfernen (für Postgres nicht notwendig)
    apt-get purge -y \
        libxslt1.1 \
        xsltproc \
        libxml2-utils \
        || true; \
    # (C) Aufräumen
    apt-get autoremove -y; \
    rm -rf /var/lib/apt/lists/*; \
    # (D) kurze Sichtprüfung
    dpkg-query -W openssl libssl3t64 openssl-provider-legacy || true; \
    ! dpkg -s libxslt1.1 >/dev/null 2>&1 || (echo "WARN: libxslt1.1 noch installiert" && exit 1)