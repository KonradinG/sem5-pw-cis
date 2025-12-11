FROM python:3.14

LABEL maintainer="Johann Hipolito"
LABEL org.opencontainers.image.title="python-3.14-secured"

RUN apt-get update && \
    apt-get remove -y --no-install-recommends \
    imagemagick \
    imagemagick-7-common \
    imagemagick-7.q16 \
    libbluetooth-dev \
    libbluetooth3 \
    libmagickcore-7-arch-config \
    libmagickcore-7-headers \
    libmagickcore-7.q16-10 \
    libmagickcore-7.q16-10-extra \
    libmagickcore-7.q16-dev \
    libmagickcore-dev \
    libmagickwand-7-headers \
    libmagickwand-7.q16-10 \
    libmagickwand-7.q16-dev \
    libmagickwand-dev \
    libpython3.13-minimal \
    libpython3.13-stdlib \
    libxml2 \
    libxml2-dev \
    libxslt1-dev \
    libxslt1.1 \
    linux-libc-dev \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

    