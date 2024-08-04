# ベースイメージとしてPHP 7.4とApacheを使用
FROM php:7.4-apache

# 必要なPHP拡張モジュールとその他のツールをインストール
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_mysql

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apacheのドキュメントルートを設定
WORKDIR /var/www/html

# ソースコードと依存関係をコピー
COPY . /var/www/html
RUN composer install

# ポート80を公開
EXPOSE 80
