# syntax=docker/dockerfile:1

# ------------------------------------------------------------
# フロントエンドのビルドと、Zod のバリデーションを Extism プラグイン（Wasm）にビルド
# ------------------------------------------------------------
FROM node:24-trixie-slim AS frontend

ARG EXTISM_JS_VERSION=v1.7.0
ARG BINARYEN_VERSION=version_125

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*

# extism-js（JS PDK）と、extism-js が内部で使う binaryen（wasm-merge / wasm-opt）
RUN set -eux; \
    case "$(uname -m)" in \
      x86_64) ARCH=x86_64 ;; \
      aarch64|arm64) ARCH=aarch64 ;; \
      *) echo "unsupported arch: $(uname -m)"; exit 1 ;; \
    esac; \
    curl -fsSL "https://github.com/extism/js-pdk/releases/download/${EXTISM_JS_VERSION}/extism-js-${ARCH}-linux-${EXTISM_JS_VERSION}.gz" \
      | gunzip > /usr/local/bin/extism-js; \
    chmod +x /usr/local/bin/extism-js; \
    curl -fsSL "https://github.com/WebAssembly/binaryen/releases/download/${BINARYEN_VERSION}/binaryen-${BINARYEN_VERSION}-${ARCH}-linux.tar.gz" \
      | tar xz -C /opt; \
    ln -s "/opt/binaryen-${BINARYEN_VERSION}/bin/wasm-merge" /usr/local/bin/wasm-merge; \
    ln -s "/opt/binaryen-${BINARYEN_VERSION}/bin/wasm-opt" /usr/local/bin/wasm-opt

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY tsconfig.json ./
COPY types ./types
COPY shared ./shared
COPY elements ./elements
COPY frontend ./frontend
COPY plugins ./plugins
COPY templates ./templates
RUN npm run typecheck && mkdir -p public wasm && npm run build

# ------------------------------------------------------------
# PHP 8.5 + Extism ランタイム
# ------------------------------------------------------------
FROM php:8.5-cli AS app

ARG EXTISM_VERSION=v1.41.0
ARG ENHANCE_SSR_WASM_VERSION=v0.0.4

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl libffi-dev unzip \
    && docker-php-ext-install ffi \
    && rm -rf /var/lib/apt/lists/*

# Extism ランタイム（libextism.so）。PHP SDK は FFI 経由でこれを読み込む
RUN set -eux; \
    case "$(uname -m)" in \
      x86_64) ARCH=x86_64 ;; \
      aarch64|arm64) ARCH=aarch64 ;; \
      *) echo "unsupported arch: $(uname -m)"; exit 1 ;; \
    esac; \
    curl -fsSL "https://github.com/extism/extism/releases/download/${EXTISM_VERSION}/libextism-${ARCH}-unknown-linux-gnu-${EXTISM_VERSION}.tar.gz" \
      | tar xz -C /usr/local/lib libextism.so; \
    ldconfig

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev

# Enhance SSR の Extism プラグイン
RUN curl -fsSL "https://github.com/enhance-dev/enhance-ssr-wasm/releases/download/${ENHANCE_SSR_WASM_VERSION}/enhance-ssr.wasm.gz" \
      | gunzip > wasm/enhance-ssr.wasm

COPY --from=frontend /app/public/js ./public/js
COPY --from=frontend /app/public/css ./public/css
COPY --from=frontend /app/wasm/validator.wasm ./wasm/validator.wasm
COPY --from=frontend /app/build/elements ./build/elements

RUN mkdir -p var && chown www-data:www-data var
USER www-data

ENV PHP_CLI_SERVER_WORKERS=4
# Extism（wasmtime）は Wasm のコンパイル結果をキャッシュするので、書き込める場所を用意する
ENV XDG_CACHE_HOME=/tmp/cache
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
