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
6. Filament-Benutzer erstellen:
   ```bash
   php artisan make:filament-user
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
3. Reverb als systemd-Service einrichten. Beispielkonfiguration in `/etc/systemd/system/reverb.service`:
   ```ini
   [Unit]
   Description=Laravel Reverb Server
   After=network.target

   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/var/www/dashclip
   ExecStart=/usr/bin/php artisan reverb:start
   Restart=on-failure

   [Install]
   WantedBy=multi-user.target
   ```
   Service aktivieren und starten:
   ```bash
   sudo systemctl enable --now reverb.service
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

    location /reverb/ {
        proxy_pass         http://reverb:8080/;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host $host;
        proxy_read_timeout 120s;
        proxy_send_timeout 120s;
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

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost/"
    </FilesMatch>

    ProxyPass "/reverb/" "ws://reverb:8080/"
    ProxyPassReverse "/reverb/" "ws://reverb:8080/"

    ErrorLog ${APACHE_LOG_DIR}/dashclip-error.log
    CustomLog ${APACHE_LOG_DIR}/dashclip-access.log combined
</VirtualHost>
```
