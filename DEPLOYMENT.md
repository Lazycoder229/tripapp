# 🚀 Trip PHP Framework — Production Deployment Guide

Naglalaman ang gabay na ito ng kumpletong instructions para sa pag-deploy ng Trip PHP Framework sa Production, pati na ang gabay kung aling files ang kailangan at pwedeng tanggalin depende sa napiling deployment strategy.

---

## 📌 Talaan ng Nilalaman
1. [Paano Pumili ng Tamang Deployment Option?](#1-paano-pumili-ng-tamang-deployment-option)
2. [Option A: Docker & Docker Compose (Inirerekomenda)](#option-a-docker--docker-compose-inirerekomenda)
3. [Option B: Nginx Web Server sa Linux VPS (Bare Metal)](#option-b-nginx-web-server-sa-linux-vps-bare-metal)
4. [Option C: Apache / Shared Hosting / cPanel (Gamit ang `.htaccess`)](#option-c-apache--shared-hosting--cpanel)
5. [🧹 File Cleanup Guide (Kung Docker ang Napili)](#5--file-cleanup-guide-kung-docker-ang-napili)
6. [Automated Deployment (`deploy.sh`)](#6-automated-deployment-deploysh)
7. [Pre-Flight Production Checklist](#7-pre-flight-production-checklist)

---

## 1. Paano Pumili ng Tamang Deployment Option?

| Deployment Method | Kailan Ito Dapat Gamitin? | Mga Kinakailangang Files |
|---|---|---|
| **Option A: Docker & Docker Compose** *(Recommended)* | Modernong VPS o Cloud (AWS, DigitalOcean, Hetzner) kung saan gusto mo ng container isolation para sa PHP 8.4, Nginx, at MySQL. | • `Dockerfile`<br>• `docker-compose.yml`<br>• `deployment/nginx/docker.conf`<br>• `deployment/php/opcache.ini`<br>• `deployment/php/php.ini` |
| **Option B: Nginx Native (Bare Metal)** | Linux VPS kung saan naka-install ang PHP-FPM at Nginx nang direkta sa mismong OS (walang Docker). | • `deployment/nginx/trip.conf`<br>• `deployment/php/opcache.ini`<br>• `deployment/php/php.ini` |
| **Option C: Apache / cPanel / Shared Hosting** | Shared hosting (Hostinger, Namecheap, cPanel) o lumang Apache servers kung saan bawal mag-run ng Docker. | • `public/.htaccess`<br>• `.htaccess` (Root fallback) |

---

## Option A: Docker & Docker Compose (Inirerekomenda)

Ito ang pinakamalinis at standardized na paraan para sa production.

### Hakbang 1: Ihanda ang Production `.env`
```bash
cp .env.example .env
```
I-configure ang Docker environment variables sa `.env`:
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:4v+6y6hN61Yw+qD2p7t3e2hW/8l0sP3Z8q0v1w2x3y4=
JWT_SECRET=your-production-jwt-secret-key-32-chars-long

# Docker Service Hostnames
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=trip_db
DB_USERNAME=trip_user
DB_PASSWORD=trip_password
```

### Hakbang 2: Simulan ang mga Containers
```bash
docker compose up -d --build
```

### Hakbang 3: Patakbuhin ang Migrations at Cache Optimization sa Loob ng Container
```bash
# Patakbuhin ang database migrations
docker compose exec app php trip migrate

# I-compile ang production route at config caches
docker compose exec app php trip optimize
```

---

## Option B: Nginx Web Server sa Linux VPS (Bare Metal)

Kung ayaw mong gumamit ng Docker at direkta mong gustong patakbuhin ang PHP-FPM at Nginx sa Ubuntu/Debian server.

### Hakbang 1: I-install ang PHP 8.4-FPM at Nginx
```bash
sudo apt update
sudo apt install -y nginx php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-bcmath php8.4-intl php8.4-zip php8.4-opcache
```

### Hakbang 2: Kopyahin ang Nginx Server Block
```bash
sudo cp deployment/nginx/trip.conf /etc/nginx/sites-available/trip.conf
sudo ln -s /etc/nginx/sites-available/trip.conf /etc/nginx/sites-enabled/
sudo nano /etc/nginx/sites-available/trip.conf # I-edit ang server_name at root path
```

### Hakbang 3: I-set ang Directory Permissions
```bash
sudo chown -R www-data:www-data /var/www/trip/storage /var/www/trip/app/views
sudo chmod -R 775 /var/www/trip/storage
```

### Hakbang 4: I-restart ang Nginx at PHP-FPM
```bash
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.4-fpm
```

---

## Option C: Apache / Shared Hosting / cPanel (Halimbawa: Hostinger)

Gamitin ito kapag naka-host sa shared hosting tulad ng **Hostinger**, **Namecheap**, **GoDaddy**, o anumang cPanel/hPanel na Apache server.

---

### 📂 Dalawang Paraan ng Pag-deploy sa Hostinger

#### Paraan A: I-point ang Domain sa `public/` subfolder (Inirerekomenda)

Sa **hPanel ng Hostinger**, pwede mong baguhin ang document root ng domain mo:

1. Pumunta sa **hPanel → Domains → Manage → yourdomain.com**
2. Sa **"Point to"** o **"Document Root"**, palitan ang value mula:
   ```
   public_html
   ```
   papunta sa:
   ```
   public_html/public
   ```
3. I-upload ang **buong project** sa `public_html/`:

```
/home/username/public_html/          ← Buong project dito
├── .env                              ← Credentials (protected by .htaccess)
├── .htaccess                         ← Root redirect (backup protection)
├── app/
├── config/
├── composer.json
├── database/
├── public/                           ← ITO ang DocumentRoot ng domain
│   ├── .htaccess                     ← Front controller + security headers
│   ├── index.php                     ← Entry point
│   ├── css/                          ← Static assets (kung meron)
│   └── js/
├── src/
├── storage/
└── vendor/
```

> ✅ **Ito ang pinaka-secure** — ang `.env`, `config/`, at `storage/` ay hindi kailanman accessible sa browser kasi nasa labas sila ng DocumentRoot.

---

#### Paraan B: I-upload Lahat sa `public_html/` (Kung hindi pwedeng baguhin ang Document Root)

Kung hindi pwedeng baguhin ang DocumentRoot (mas lumang shared hosting plan), i-upload ang buong project sa `public_html/`. Ang root `.htaccess` ang bahala mag-redirect ng lahat papuntang `public/`:

```
/home/username/public_html/          ← DocumentRoot ng domain (hindi mababago)
├── .env                              ← Protected by .htaccess
├── .htaccess                         ← ⚡ NAGRE-REDIRECT ng traffic → public/
├── app/                              ← Protected by .htaccess
├── config/                           ← Protected by .htaccess
├── database/                         ← Protected by .htaccess
├── public/
│   ├── .htaccess                     ← Front controller
│   └── index.php
├── src/                              ← Protected by .htaccess
├── storage/                          ← Protected by .htaccess
└── vendor/                           ← Protected by .htaccess
```

> ⚠️ **Paliwanag:** Ang root `.htaccess` ang gumagawa ng dalawang bagay:
> 1. **Redirect** — Lahat ng request ay napupunta sa `public/index.php`.
> 2. **Block** — Kahit i-type sa browser ang `yourdomain.com/config/database.php`, babalik na **403 Forbidden**.

---

### 🔧 Hakbang 1: I-set Up ang Database sa Hostinger hPanel

1. Pumunta sa **hPanel → Databases → MySQL Databases**
2. Gumawa ng bagong database:
   - **Database name:** `u123456789_trip_db`
   - **Username:** `u123456789_trip_user`
   - **Password:** *(gumawa ng malakas na password)*
3. I-update ang `.env` file:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=base64:your-generated-key-here

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_trip_db
DB_USERNAME=u123456789_trip_user
DB_PASSWORD=your-strong-db-password

SESSION_SECURE=true
LOG_LEVEL=error
```

> 💡 **Sa Hostinger**, ang `DB_HOST` ay palaging `localhost` (hindi IP address).

---

### 📤 Hakbang 2: I-upload ang Project Files

**Option A: Via SSH** (Hostinger Business plan at pataas)
```bash
# SSH login
ssh u123456789@yourdomain.com -p 65002

# Pumunta sa public_html
cd ~/public_html

# I-clone ang project (kung nasa Git)
git clone https://github.com/your-repo/trip.git .

# I-install ang dependencies
composer install --no-dev --optimize-autoloader
```

**Option B: Via File Manager o FTP**
1. Sa **hPanel → Files → File Manager**, i-upload ang ZIP ng buong project.
2. I-extract sa `public_html/`.
3. Siguraduhing kasama ang `vendor/` folder (o mag-SSH para mag-`composer install`).

---

### 🛡️ Hakbang 3: Kumpirmahin ang `.htaccess` Files

Dalawang `.htaccess` ang kailangan — **parehong kasama na sa project**:

| File | Tungkulin |
|---|---|
| **`.htaccess`** (root) | I-redirect lahat ng traffic sa `public/`, i-block ang `app/`, `config/`, `storage/`, `vendor/`, `.env` |
| **`public/.htaccess`** | Front controller routing → `index.php`, Authorization header passthrough (JWT), security headers, GZIP, caching |

**Kung may SSL na (Let's Encrypt via Hostinger)**, i-uncomment sa `public/.htaccess`:
```apache
# Hanapin ito sa public/.htaccess at tanggalin ang # sa harap:
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

At i-uncomment din ang HSTS header:
```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

---

### 📁 Hakbang 4: I-set ang Directory Permissions

```bash
# Via SSH:
chmod -R 755 storage/
chmod -R 755 app/views/

# Siguraduhing writable ang log at cache directories
chmod -R 775 storage/log/
chmod -R 775 storage/cache/
chmod -R 775 storage/framework/
```

> Sa **Hostinger File Manager**, right-click sa `storage/` folder → **Permissions** → set to `755` recursively.

---

### 🚀 Hakbang 5: Patakbuhin ang Migrations at Optimization

```bash
# Via SSH:
php trip migrate
php trip optimize

# I-generate ang encryption key (kung wala pa)
php trip key:generate
php trip jwt:secret
```

> Kung walang SSH, maaaring gumawa ng temporary PHP script:
> Gumawa ng `public/setup.php`:
> ```php
> <?php
> // PANSAMANTALANG setup script — TANGGALIN PAGKATAPOS GAMITIN!
> require_once __DIR__ . '/../vendor/autoload.php';
> echo "<pre>";
> echo shell_exec('php ' . __DIR__ . '/../trip migrate 2>&1');
> echo shell_exec('php ' . __DIR__ . '/../trip optimize 2>&1');
> echo "</pre>";
> ```
> Buksan sa browser: `https://yourdomain.com/setup.php`
> **⚠️ TANGGALIN KAAGAD ang `setup.php` pagkatapos gamitin!**

---

### 🔍 Troubleshooting sa Shared Hosting

| Problema | Solusyon |
|---|---|
| **500 Internal Server Error** | Tingnan ang `storage/log/app.YYYY-MM-DD.log`. Kung walang laman, tingnan ang Apache error log sa hPanel → **Advanced → Error Logs**. |
| **404 Not Found sa lahat ng route** | Siguraduhing naka-enable ang `mod_rewrite`. Sa hPanel, pumunta sa **Advanced → PHP Configuration → Apache Modules** at i-check ang `rewrite_module`. |
| **JWT/Authorization header nawala** | Nasa `public/.htaccess` na ang 3 fallback methods. Kung hindi pa rin gumana, idagdag sa `.env`: `JWT_HEADER=X-Auth-Token` at ipasa ang token doon. |
| **"Class not found" errors** | I-run `composer dump-autoload --optimize` via SSH. |
| **PHP version mismatch** | Sa hPanel → **Advanced → PHP Configuration**, palitan ang PHP version sa **8.1** o mas mataas. |
| **Hindi nagre-redirect sa HTTPS** | I-check kung naka-install ang SSL sa hPanel → **Security → SSL**. Pagkatapos, i-uncomment ang Force HTTPS rules sa `public/.htaccess`. |
| **`storage/` not writable** | `chmod -R 775 storage/` via SSH o sa File Manager permissions. |

---

## 5. 🧹 File Cleanup Guide (Kung Docker ang Napili)

Kung **Docker (Option A)** ang opisyal mong napiling paraan para sa deployment, maaari mong ligtas na tanggalin ang mga sumusunod na files upang maging malinis ang codebase:

### ❌ Ligtas nang Tanggalin:
* `.htaccess` *(Root directory)*
* `public/.htaccess` *(Apache directory)*
* `deployment/nginx/trip.conf` *(Native Nginx config)*

### ✅ DAPAT MANATILI para sa Docker:
* `Dockerfile`
* `docker-compose.yml`
* `deployment/nginx/docker.conf`
* `deployment/php/php.ini`
* `deployment/php/opcache.ini`
* `deploy.sh`

---

## 6. Automated Deployment (`deploy.sh`)

Para sa one-command zero-downtime updates sa live production server gamit ang Git:

```bash
chmod +x deploy.sh
./deploy.sh
```

### Daloy ng `deploy.sh`:
1. **Maintenance Mode**: Isasara ang site gamit ang `php trip down --retry=30`.
2. **Git Pull**: I-pu-pull ang pinakabagong updates mula sa `main` branch.
3. **Composer Install**: I-i-install ang production dependencies (`composer install --no-dev`).
4. **Database Migrations**: Awtomatikong magpapatakbo ng pending migrations (`php trip migrate`).
5. **Cache Compilations**: I-re-recompile ang sariwang route at config caches (`php trip optimize`).
6. **Live Mode**: Ibabalik sa online status ang app (`php trip up`).

---

## 7. Pre-Flight Production Checklist

| Pagsusuri | Setting / Command | Layunin |
|---|---|---|
| **Environment Mode** | `APP_ENV=production` | Pinipigilan ang pag-leak ng sensitive debug info |
| **Debug Mode** | `APP_DEBUG=false` | Pinapagana ang custom generic error views |
| **App Secret Key** | `php trip key:generate` | Lumilikha ng 32-byte AES-GCM encryption key |
| **JWT Secret** | `php trip jwt:secret` | Nagsusulat ng 256-bit token signing secret |
| **Route & Config Cache** | `php trip optimize` | Nagko-compile sa OPcache para sa sub-millisecond boot |
| **Automated Unit Tests** | `composer test` | Tinitiyak na 100% passing ang 27 PHPUnit tests |
