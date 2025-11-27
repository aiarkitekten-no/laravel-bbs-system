# PUNKTET BBS - Deployment Guide

## Systemkrav

- PHP 8.2+
- MariaDB 10.6+ / MySQL 8.0+
- Composer 2.x
- Node.js 18+ (for frontend)
- Apache/Nginx
- Redis (anbefalt for cache/sessions)
- SSL-sertifikat

## Produksjonsoppsett

### 1. Klon repository

```bash
cd /var/www/vhosts/punktet.no/httpdocs
git clone <repo-url> .
```

### 2. Installer avhengigheter

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Miljøkonfigurasjon

```bash
cp .env.example .env
php artisan key:generate
```

Rediger `.env`:

```env
APP_NAME=PUNKTET
APP_ENV=production
APP_DEBUG=false
APP_URL=https://punktet.no

# Database - VIKTIG: Bruk mysql driver, ikke mariadb!
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=admin_punkteT
DB_USERNAME=admin_punkteT
DB_PASSWORD=<ditt-passord>

# Cache (anbefalt Redis)
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.punktet.no
MAIL_PORT=587
MAIL_USERNAME=noreply@punktet.no
MAIL_PASSWORD=<mail-passord>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@punktet.no
MAIL_FROM_NAME="PUNKTET BBS"

# BBS-spesifikke innstillinger
BBS_NAME=PUNKTET
BBS_SYSOP=SysOp
BBS_NODES=10
BBS_DEBUG_MODE=false
```

### 4. Database

```bash
php artisan migrate --force
php artisan db:seed --force
```

### 5. Optimaliseringer

```bash
# Cache config
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 6. Filrettigheter

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 7. Cron Jobs

Legg til i crontab (`crontab -e`):

```cron
* * * * * cd /var/www/vhosts/punktet.no/httpdocs && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Queue Worker (Valgfritt)

Opprett systemd service `/etc/systemd/system/punktet-worker.service`:

```ini
[Unit]
Description=PUNKTET BBS Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/vhosts/punktet.no/httpdocs
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Aktiver:
```bash
systemctl daemon-reload
systemctl enable punktet-worker
systemctl start punktet-worker
```

## Apache Virtual Host

```apache
<VirtualHost *:443>
    ServerName punktet.no
    ServerAlias www.punktet.no
    DocumentRoot /var/www/vhosts/punktet.no/httpdocs/public

    <Directory /var/www/vhosts/punktet.no/httpdocs/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # SSL
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    SSLCertificateChainFile /path/to/chain.crt

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/punktet-error.log
    CustomLog ${APACHE_LOG_DIR}/punktet-access.log combined
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName punktet.no
    ServerAlias www.punktet.no
    Redirect permanent / https://punktet.no/
</VirtualHost>
```

## Nginx Konfigurasjon (Alternativ)

```nginx
server {
    listen 443 ssl http2;
    server_name punktet.no www.punktet.no;
    root /var/www/vhosts/punktet.no/httpdocs/public;

    index index.php;

    # SSL
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip
    gzip on;
    gzip_types text/plain application/json application/javascript text/css;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

server {
    listen 80;
    server_name punktet.no www.punktet.no;
    return 301 https://punktet.no$request_uri;
}
```

## Oppdateringer

### Standard oppdatering

```bash
cd /var/www/vhosts/punktet.no/httpdocs

# Sett i vedlikeholdsmodus
php artisan down

# Hent oppdateringer
git pull origin main

# Installer avhengigheter
composer install --no-dev --optimize-autoloader

# Kjør migrasjoner
php artisan migrate --force

# Tøm cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
systemctl restart punktet-worker

# Ta ut av vedlikeholdsmodus
php artisan up
```

### Rollback

```bash
php artisan down
php artisan migrate:rollback --step=1
php artisan up
```

## Backup

### Database backup

```bash
mysqldump -u admin_punkteT -p admin_punkteT > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Full backup script

Opprett `/usr/local/bin/punktet-backup.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/punktet"
DATE=$(date +%Y%m%d_%H%M%S)

# Opprett backup-katalog
mkdir -p $BACKUP_DIR

# Database
mysqldump -u admin_punkteT -p'Klokken!12!?!' admin_punkteT > $BACKUP_DIR/db_$DATE.sql

# Filer
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/vhosts/punktet.no/httpdocs/storage/app

# Konfigfiler
tar -czf $BACKUP_DIR/config_$DATE.tar.gz /var/www/vhosts/punktet.no/httpdocs/.env

# Slett gamle backups (eldre enn 7 dager)
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $DATE"
```

Legg til i cron:
```cron
0 3 * * * /usr/local/bin/punktet-backup.sh >> /var/log/punktet-backup.log 2>&1
```

## Monitoring

### Health check

```bash
curl https://punktet.no/api/health/ping
curl https://punktet.no/api/health/status
```

### Log monitoring

```bash
tail -f storage/logs/laravel.log
```

### Disk space

```bash
df -h /var/www/vhosts/punktet.no
```

## Troubleshooting

### 500 Server Error

1. Sjekk Laravel logs: `tail -f storage/logs/laravel.log`
2. Sjekk Apache/Nginx logs
3. Verifiser filrettigheter
4. Sjekk `.env` konfigurasjon

### Database tilkoblingsfeil

1. Verifiser database credentials i `.env`
2. Sjekk at MariaDB kjører: `systemctl status mariadb`
3. Test tilkobling: `php artisan db:show`

### Cache problemer

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Session problemer

```bash
php artisan session:table
php artisan migrate
chmod -R 775 storage/framework/sessions
```

## Sikkerhetshardening

1. **Fjern debug-modus**: `APP_DEBUG=false`
2. **Bruk HTTPS**: Sett opp SSL-sertifikat
3. **Begrens database-tilgang**: Kun lokale tilkoblinger
4. **Oppdater regelmessig**: `composer update`, system patches
5. **Overvåk logs**: Sett opp log-alerting
6. **Backup**: Daglige backups, test restore
7. **Brannmur**: Kun åpne nødvendige porter (80, 443)

## Support

- E-post: support@punktet.no
- SysOp: terje@smartesider.no
