# Setup der Anwendung

Diese Anleitung beschreibt die Installation der Anwendung und die Einrichtung der notwendigen Dienste.

## Voraussetzungen

- PHP ≥ 8.4 und Composer
- Node.js & npm
- Datenbank (z. B. MySQL oder SQLite)
- Git

## Laravel installieren

1. Repository klonen und ins Projektverzeichnis wechseln:
   ```bash
   git clone <REPO_URL>
   cd dashclip-delivery
   ```
2. Abhängigkeiten installieren:
   ```bash
   composer install
   npm install
   ```
3. Beispieldatei kopieren und Anwendungsschlüssel generieren:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Datenbank konfigurieren und Migrationen ausführen:
   ```bash
   php artisan migrate
   ```
5. Assets kompilieren:
   ```bash
   npm run build
   ```

## Reverb einrichten

1. Paket installieren (falls noch nicht vorhanden):
   ```bash
   composer require laravel/reverb
   php artisan reverb:install
   ```
2. In der `.env` die Broadcast-Einstellungen setzen:
   ```
   BROADCAST_DRIVER=reverb
   REVERB_APP_ID=app-id
   REVERB_APP_KEY=app-key
   REVERB_APP_SECRET=app-secret
   REVERB_HOST=localhost
   REVERB_PORT=8080
   ```
3. Server starten:
   ```bash
   php artisan reverb:start
   ```

## Webserver konfigurieren

### Nginx

```nginx
server {
    listen 80;
    server_name example.test;
    root /var/www/dashclip/public;

    index index.php;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName example.test
    DocumentRoot /var/www/dashclip/public

    <Directory /var/www/dashclip/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/dashclip-error.log
    CustomLog ${APACHE_LOG_DIR}/dashclip-access.log combined
</VirtualHost>
```

## Anwendung starten

```bash
php artisan serve
# optional: Warteschlange und Reverb
php artisan queue:listen
php artisan reverb:start
```
