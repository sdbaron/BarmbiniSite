# Barmbini – Server-Neuinstallation (leeres Linux-System)

Dieses Dokument beschreibt Schritt für Schritt, wie die Website
**Sozialkaufhaus Barmbini** (`https://barmbini.de`) mit allen erforderlichen
Diensten auf einem **leeren Linux-Server** neu aufgesetzt wird.

Es bildet den **verifizierten Live-Stand vom 2026-08-20** ab (Ubuntu 24.04,
nginx 1.24, PHP 8.3, MariaDB 10.11, WordPress 7.0.4, barmbini-core 0.9.1).

> **Geheimnisse:** Datenbank- und SMTP-Passwörter liegen auf dem Server in
> `/root/barmbini-db.txt` bzw. `/root/barmbini-mail.txt` (chmod 600) und werden
> hier **nicht** wiedergegeben. An den entsprechenden Stellen stehen Platzhalter
> `<DB-PASSWORT>` bzw. `<SMTP-PASSWORT>`.

---

## Inhaltsverzeichnis

1. [Überblick](#1-überblick)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Basis-Konfiguration des Servers](#3-basis-konfiguration-des-servers)
4. [Pakete installieren](#4-pakete-installieren)
5. [PHP-FPM konfigurieren](#5-php-fpm-konfigurieren)
6. [MariaDB einrichten](#6-mariadb-einrichten)
7. [WordPress installieren](#7-wordpress-installieren)
8. [nginx-Site konfigurieren](#8-nginx-site-konfigurieren)
9. [HTTPS mit Let’s Encrypt](#9-https-mit-lets-encrypt)
10. [Website-Inhalte einspielen (Code, Uploads, Datenbank)](#10-website-inhalte-einspielen-code-uploads-datenbank)
11. [Plugins & Themes aktivieren und konfigurieren](#11-plugins--themes-aktivieren-und-konfigurieren)
12. [E-Mail-Versand (Solid Mail + IONOS SMTP)](#12-e-mail-versand-solid-mail--ionos-smtp)
13. [Besucherstatistik installieren](#13-besucherstatistik-installieren)
14. [Sicherheits-Härtung](#14-sicherheits-härtung)
15. [Backups, Cron & Wartung](#15-backups-cron--wartung)
16. [Abschluss-Verifikation (Checkliste)](#16-abschluss-verifikation-checkliste)
17. [Rollback & Risiken](#17-rollback--risiken)

---

## 1. Überblick

| Komponente | Wert (Live-Stand) |
|---|---|
| Betriebssystem | Ubuntu 24.04.4 LTS (noble) |
| Webserver | nginx 1.24 |
| PHP | 8.3 (php8.3-fpm), FPM via Unix-Socket |
| Datenbank | MariaDB 10.11 |
| WordPress | 7.0.4 (de_DE_formal) |
| WP-CLI | 2.12.0 |
| Webroot | `/var/www/barmbini` |
| DB-Name / -User | `barmbini_wp` / `barmbini_user` |
| Tabellen-Präfix | `wp_` |
| Domain | `barmbini.de` (www → 301 auf apex) |
| SSL | Let’s Encrypt (Certbot) |
| RAM | 826 MB (1-Core-VPS, Swap 2 GB) |
| Besonderheiten | WooCommerce nur als Katalog (kein Checkout) |

**Architektur-Hinweis:** Sämtliche projektspezifische Geschäftslogik liegt im
Plugin `wp-content/plugins/barmbini-core/`. Theme ist **Kadence** (Kind-Theme
nur für Template-/CSS-Overrides). Keine Geschäftslogik in
`themes/kadence/functions.php`.

---

## 2. Voraussetzungen

- **VPS mit root-Zugriff** (IONOS VPS Linux S+, 1 vCPU, 826 MB RAM, min. 10 GB
  Disk – der Live-Server zeigt auf `/` ca. 8,7 GB, daher knapp kalkulieren).
- **2 GB Swap** (bei 826 MB RAM dringend empfohlen, siehe Schritt 3.4).
- **Domain `barmbini.de`** mit DNS-A-Record auf die Server-IP (für Let’s Encrypt
  müssen `barmbini.de` und `www.barmbini.de` erreichbar sein).
- **SSH-Schlüssel** für den Root-Login (Passwort-Login wird in Schritt 14
  deaktiviert – Schlüssel **vorher** hinterlegen!).
- **Zugangsdaten:** DB-Passwort, SMTP-Passwort (IONOS-Postfach `info@barmbini.de`).
- **Website-Quellstand** für Schritt 10 (lokaler Stand oder Backup):
  - `wp-content` (plugins/themes/languages/uploads)
  - Datenbank-Dump `local.sql`

> **Wichtig:** Der Server gilt als **sicherheitskritisch** (frühere
> Kompromittierungen sind dokumentiert). Alle Konfigurationsänderungen mit
> Backup durchführen; die Härtungsschritte in Kapitel 14 sind **Pflicht**, nicht optional.

---

## 3. Basis-Konfiguration des Servers

### 3.1 System aktualisieren und Grundwerkzeuge

```bash
apt-get update && apt-get upgrade -y
apt-get install -y curl wget unzip jq git rsync ca-certificates \
  software-properties-common lsb-release gnupg logrotate
```

### 3.2 Zeitzone, Locale, Hostname

```bash
timedatectl set-timezone Europe/Berlin
hostnamectl set-hostname barmbini
locale-gen de_DE.UTF-8
update-locale LANG=de_DE.UTF-8
```

### 3.3 SSH-Schlüssel-Login vorbereiten (vor dem Abschalten von Passwort-Login)

Lokalen Public Key auf dem Server hinterlegen:

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "<PUBLIC-KEY>" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

**Test:** Ein zweites Terminal öffnen und prüfen, dass `ssh root@<IP>` mit dem
Schlüssel funktioniert – erst danach in Schritt 14 den Passwort-Login deaktivieren.

### 3.4 Swap anlegen (2 GB)

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
swapon --show          # Kontrolle: /swapfile 2G
```

---

## 4. Pakete installieren

Ubuntu 24.04 liefert PHP 8.3 direkt aus den Standard-Quellen.

```bash
apt-get update
apt-get install -y \
  nginx \
  mariadb-server \
  php8.3-fpm php8.3-cli \
  php8.3-mysql php8.3-curl php8.3-xml php8.3-xsl php8.3-mbstring \
  php8.3-zip php8.3-gd php8.3-intl php8.3-imagick php8.3-opcache \
  certbot python3-certbot-nginx
```

**Modul-Check:** `php8.3 -m` muss mindestens `mysqli pdo_mysql curl xml mbstring
zip gd intl imagick opcache` enthalten (das sind die WordPress-relevanten Module
des Live-Systems).

### 4.1 WP-CLI installieren

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp
wp --allow-root --info     # Version 2.x erwartet
```

### 4.2 Dienste aktivieren

```bash
systemctl enable --now nginx
systemctl enable --now mariadb
systemctl enable --now php8.3-fpm
systemctl status nginx mariadb php8.3-fpm --no-pager
```

---

## 5. PHP-FPM konfigurieren

### 5.1 Upload-/Speicher-Limits (`/etc/php/8.3/fpm/conf.d/99-barmbini.ini`)

Für große Medien- und Plugin-Uploads (nginx erlaubt 256M, siehe Schritt 8):

```ini
upload_max_filesize = 256M
post_max_size = 256M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
```

Anlegen:

```bash
cat > /etc/php/8.3/fpm/conf.d/99-barmbini.ini <<'EOF'
upload_max_filesize = 256M
post_max_size = 256M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
EOF
```

### 5.2 Worker-Pool auf den knappen RAM abstimmen (`/etc/php/8.3/fpm/pool.d/www.conf`)

Der Live-Server hat nur **826 MB RAM**. Jeder PHP-Worker belegt ~110–120 MB.
**Empfohlene Werte** (3 Worker ≈ 360 MB):

```ini
pm = dynamic
pm.max_children = 3
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 2
```

> Der aktuelle Live-Server läuft mit `pm.max_children = 5` /
> `pm.max_spare_servers = 3`. Bei 826 MB RAM ist **3 der sichere Wert** (Detail:
> `Tasks/Barmbini_Aufgabe_Server_PHP_FPM_Tuning.md`). Bei mehr RAM kann erhöht werden.

Konfiguration testen und übernehmen:

```bash
php-fpm8.3 -t                  # muss "syntax is ok" liefern
systemctl reload php8.3-fpm
```

---

## 6. MariaDB einrichten

```bash
# Root-Socket-Auth bleibt bestehen; separaten App-User anlegen:
mariadb <<'SQL'
CREATE DATABASE barmbini_wp DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'barmbini_user'@'localhost' IDENTIFIED BY '<DB-PASSWORT>';
GRANT ALL PRIVILEGES ON barmbini_wp.* TO 'barmbini_user'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Passwort auf dem Server ablegen (analog zum Live-Stand):

```bash
printf 'barmbini_wp\nbarmbini_user\n<DB-PASSWORT>\nlocalhost\n' > /root/barmbini-db.txt
chmod 600 /root/barmbini-db.txt
```

---

## 7. WordPress installieren

```bash
mkdir -p /var/www/barmbini
cd /var/www/barmbini

wp core download --locale=de_DE_formal --allow-root

wp config create \
  --dbname=barmbini_wp \
  --dbuser=barmbini_user \
  --dbpass='<DB-PASSWORT>' \
  --dbhost=localhost \
  --dbprefix=wp_ \
  --allow-root

# (Zusätzlich, wie im Live-Stand, in wp-config.php setzen:)
#   define( 'WP_HOME',    'https://barmbini.de' );
#   define( 'WP_SITEURL', 'https://barmbini.de' );
#   define( 'WP_DEBUG', false );
#   define( 'WP_DEBUG_LOG', true );
#   define( 'WP_DEBUG_DISPLAY', false );

wp core install \
  --url='https://barmbini.de' \
  --title='Sozialkaufhaus Barmbini' \
  --admin_user='<ADMIN-BENUTZER>' \
  --admin_password='<STARKES-PASSWORT>' \
  --admin_email='<ADMIN-E-MAIL>' \
  --allow-root

# Permalink-Struktur = Beitragsname (/%postname%/)
wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --allow-root

chown -R www-data:www-data /var/www/barmbini
```

> Wenn ein **Backup/ein lokaler Stand** vorhanden ist, statt einer leeren
> Installation direkt Kapitel 10 nutzen (DB-Import + `wp-content` übertragen).

---

## 8. nginx-Site konfigurieren

Die vollständige Konfiguration liegt im Workspace unter
`server-config/barmbini-nginx.conf` und enthält bereits alle Härtungen
(Security-Header, `server_tokens off`, XML-RPC-Block, 256M Upload-Limit).

```bash
# Config aus dem Workspace auf den Server kopieren (z. B. per scp) und aktivieren:
cp /pfad/zu/server-config/barmbini-nginx.conf /etc/nginx/sites-available/barmbini
ln -sf /etc/nginx/sites-available/barmbini /etc/nginx/sites-enabled/barmbini
rm -f /etc/nginx/sites-enabled/default   # Standard-Site entfernen

nginx -t                 # muss "syntax is ok" / "test is successful" liefern
systemctl reload nginx
```

**Kernpunkte der Config:**

```nginx
server {
    server_name barmbini.de;

    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    server_tokens off;

    root /var/www/barmbini;
    index index.php index.html;
    client_max_body_size 256M;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location = /xmlrpc.php {          # Brute-Force-Vektor blockieren
        deny all;
        return 403;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht { deny all; }
}
```

Der Rest der Datei (www-Redirect auf `https://barmbini.de` und die
HTTP→HTTPS-Blöcke) wird in Schritt 9 durch **Certbot** ergänzt.

---

## 9. HTTPS mit Let’s Encrypt

Voraussetzung: DNS für `barmbini.de` **und** `www.barmbini.de` zeigt bereits auf
den Server.

```bash
certbot --nginx -d barmbini.de -d www.barmbini.de --redirect --agree-tos \
  -m '<ADMIN-E-MAIL>' --non-interactive
```

Certbot ergänzt automatisch die `listen 443 ssl`-Blöcke, die Zertifikatspfade und
die HTTP→HTTPS-Umleitungen. Danach:

```bash
nginx -t && systemctl reload nginx
curl -sI https://barmbini.de/ | head -5   # erwartet: HTTP/2 200
```

> **Hinweis HSTS:** Der `Strict-Transport-Security`-Header ist manuell im
> Haupt-Server-Block gesetzt (siehe Schritt 8) und darf nach der
> HTTPS-Einrichtung **nicht** entfernt werden.

---

## 10. Website-Inhalte einspielen (Code, Uploads, Datenbank)

Es gibt zwei Wege:

### Weg A – Wiederherstellung aus lokalem Stand / Backup (empfohlen)

Der Workspace enthält die Deployment-Skripte (`deploy.sh` / `deploy.ps1`).
Für die **Erstinstallation auf leerem Server** ist ein **Modus-A-Lauf**
(Vollabgleich inkl. Datenbank) der schnellste Weg:

```bash
# Lokal (Git Bash / Linux), lokaler Stand unter $HOME/Local Sites/barmbini:
./deploy.sh --full --force --nobrowser
```

Das Skript baut ein Archiv aus `wp-content` (languages/plugins/themes/uploads),
erstellt einen frischen SQL-Dump (`local.sql`), lädt beides hoch und spielt es
ein – inkl. URL-Umschreibung auf `https://barmbini.de`.

> **Achtung:** Modus A ersetzt die Live-Datenbank. Auf einem **leeren** Server
> ist das der gewünschte Weg. Auf einem **laufenden** System mit echten
> Kunden-/Benutzerdaten ist **Modus B** Pflicht (`./deploy.sh` ohne `--full`).

### Weg B – Manueller Import (ohne Skripte)

1. `wp-content` (languages, plugins, themes, uploads) nach
   `/var/www/barmbini/wp-content/` kopieren und `www-data:www-data` setzen.
2. Datenbank importieren und URLs umschreiben:

```bash
mysql -u root barmbini_wp < /pfad/zu/local.sql
wp search-replace 'http://barmbini.local' 'https://barmbini.de' --all-tables --allow-root
wp search-replace 'http://217.160.74.128' 'https://barmbini.de' --all-tables --allow-root
```

3. Anschließend `WP_HOME`/`WP_SITEURL` auf `https://barmbini.de` prüfen (wp-config.php).

---

## 11. Plugins & Themes aktivieren und konfigurieren

### 11.1 Aktive Plugins (Live-Stand)

| Plugin | Version | Zweck / Hinweis |
|---|---|---|
| `barmbini-core` | 0.9.1 | **Projekt-Plugin**: alle Features, Sicherheits-Module, Shortcodes |
| `woocommerce` | 10.8.1 | **Nur Produktkatalog**, kein Checkout/Zahlung |
| `hide-cart-functions` | 1.2.16 | Blendet Warenkorb/Kasse aus (Katalog-Modus) |
| `kadence-blocks` | 3.7.9 | Gutenberg-Blöcke |
| `kadence-starter-templates` | 2.3.3 | Kadence-Starter (kann nach Setup deaktiviert werden) |
| `contact-form-7` | 6.1.6 | Kontaktformular |
| `wp-smtp` (Solid Mail) | 3.0.0 | E-Mail-Versand über IONOS SMTP (Kapitel 12) |
| `simple-local-avatars` | 2.8.6 | Lokale Avatare |
| `wp-fastest-cache` | 1.4.9 | Seiten-Cache |
| `wordpress-seo` (Yoast) | 27.1.1 | SEO |

**Inaktiv (nicht aktivieren):** `all-in-one-wp-migration` (7.109) – nur für
manuelle Migrationen; nach der Installation deaktiviert lassen.

### 11.2 Theme

- **Aktiv:** `kadence` (1.5.1)
- Inaktive Standard-Themes können entfernt werden, um Speicher zu sparen
  (storefront, twentytwentyfive, … – siehe Wartung).

### 11.3 WooCommerce als Katalog

- Kein Checkout, keine Zahlung, keine Warenkorb-Nutzung.
- `hide-cart-functions` blendet die Kauf-Elemente aus.
- Produkte = Sortiment-Artikel (Preis, Kategorie, Lagerstatus „ausverkauft“).
- Beispiel-Produkte tragen das Produkt-Schlagwort `Beispiel`, damit das
  „Beispiel“-Badge erscheint (Logik in `barmbini-core`).

### 11.4 Wichtige barmbini-core-Funktionen (laufen automatisch)

- **Rollen:** Nutzer der früheren Rolle `barmbini_verkaeufer` werden per
  `admin_init` zur WooCommerce-Standardrolle `shop_manager` migriert; die
  Standardrolle `editor` („Redakteur“) bleibt unangetastet.
- **Interne Anleitung:** `/anleitung-redakteur/` (Capability
  `barmbini_view_guide_redakteur`, Admin + Redakteur). Die frühere
  Shop-Manager-Anleitung `/anleitung-verkaeufer/` wird bei `admin_init`
  automatisch in den Papierkorb verschoben.
- **Sicherheit:** REST-API-Benutzer-Endpoints für Unberechtigte gesperrt;
  Login-Brute-Force-Schutz (5 Fehlversuche → 15 Min. Sperre).
- **Cache:** WP-Cron leert WP Fastest Cache alle 6 Stunden (damit abgelaufene
  Aktionen zuverlässig von der Startseite verschwinden).
- **Shortcodes:** `[barmbini_address]`, `[barmbini_latest_news]`,
  `[barmbini_promotion]`, `[barmbini_top_product_categories]`,
  `[barmbini_visitor_stats]` (Details: `Docs/Barmbini_Shortcodes.md`).

---

## 12. E-Mail-Versand (Solid Mail + IONOS SMTP)

Die Domain-Mail läuft bei **IONOS** (MX `mx00.ionos.de`/`mx01.ionos.de`), nicht
auf dem Server. WordPress versendet daher über das Plugin **Solid Mail**
(Slug `wp-smtp`) mit dem IONOS-SMTP-Relay.

```bash
# SMTP-Passwort auf dem Server ablegen (analog barmbini-db.txt)
printf 'info@barmbini.de\n<SMTP-PASSWORT>\n' > /root/barmbini-mail.txt
chmod 600 /root/barmbini-mail.txt
```

Solid Mail aktivieren und als Standard-Verbindung konfigurieren (Connection-ID
`ionos-smtp`, Host `smtp.ionos.de`, Port 587, STARTTLS):

```bash
wp plugin activate wp-smtp --allow-root

wp eval 'update_option("solid_smtp_providers", array(
  "ionos-smtp" => array(
    "name"         => "other",
    "smtp_host"    => "smtp.ionos.de",
    "smtp_port"    => 587,
    "smtp_secure"  => "tls",
    "smtp_auth"    => "yes",
    "smtp_username" => "info@barmbini.de",
    "smtp_password" => "<SMTP-PASSWORT>",
    "is_active"    => true,
    "is_default"   => true,
  )
));' --allow-root
```

**Test:**

```bash
wp eval 'var_dump( wp_mail( "info@barmbini.de", "Test", "Test" ) );' --allow-root
```

Der Absender ist `Barmbini Sozialkaufhaus <info@barmbini.de>` (im Plugin gesetzt).
Erwartung: `true` und ein Eintrag in `wp_wpsmtp_logs` ohne Fehler.

> **Achtung:** `wp_wpmailsmtp_debug_events`/`_tasks_meta`-Tabellen sind Altlasten
> einer früheren WP-Mail-SMTP-Installation und können ignoriert werden.

---

## 13. Besucherstatistik installieren

Anonymisierte Besucherstatistik über das nginx-Zugriffslog (keine Cookies, keine
IP-Speicherung). Die Dateien liegen im Workspace unter `server-config/barmbini-stats/`.

```bash
# Dateien auf den Server kopieren (z. B. per scp) und installieren:
scp -r server-config/barmbini-stats root@<IP>:/root/barmbini-stats
ssh root@<IP> 'cd /root/barmbini-stats && ./install.sh --test'
```

`install.sh` ist idempotent und richtet ein:

1. Verzeichnisse `/root/barmbini-stats` und `/var/lib/barmbini-stats/stats`
2. Skripte `process.php`/`process.sh`
3. logrotate für `barmbini_access.log` (daily, `rotate 7`, `delaycompress`)
4. Cron `/etc/cron.d/barmbini-stats`: `15 7 * * * root /root/barmbini-stats/process.sh`

**Datenfluss:**

```text
nginx → /var/log/nginx/barmbini_access.log
      → logrotate (täglich) → barmbini_access.log.1
      → process.sh (Cron 07:15) → process.php
      → /var/lib/barmbini-stats/stats/stats-YYYY-MM-DD.json
      → Plugin barmbini-core (Admin-Seite + [barmbini_visitor_stats])
```

**Aufbewahrung (DSGVO):** Roh-Log 7 Tage, Aggregate 90 Tage. Vor der
Live-Schaltung die **Datenschutzerklärung** aktualisieren (berechtigtes
Interesse, Art. 6 Abs. 1 lit. f DSGVO). Details:
`Tasks/Barmbini_Aufgabe_Besucherstatistik_nginx.md`.

---

## 14. Sicherheits-Härtung

> Der Server war in der Vergangenheit mehrfach kompromittiert. Alle Punkte sind Pflicht.

### 14.1 SSH auf Schlüssel-only

```bash
cat > /etc/ssh/sshd_config.d/00-barmbini-hardening.conf <<'EOF'
PasswordAuthentication no
PermitRootLogin prohibit-password
EOF
sshd -t && systemctl reload ssh
```

**Vorher** den Schlüssel-Login testen (Schritt 3.3), sonst sperrt man sich aus.

### 14.2 nginx-Härtung (bereits in Schritt 8 enthalten)

- Security-Header (HSTS, X-Frame-Options, X-Content-Type-Options,
  Referrer-Policy, Permissions-Policy)
- `server_tokens off` (nginx-Version verbergen)
- XML-RPC blockieren: `location = /xmlrpc.php { deny all; return 403; }`

### 14.3 `readme.html` entfernen (Versions-Leak)

```bash
cp /var/www/barmbini/readme.html /root/barmbini-readme-backup-$(date +%F-%H%M%S).html
rm -f /var/www/barmbini/readme.html
```

### 14.4 Firewall (empfohlen)

Der Live-Server hat derzeit `ufw` **nicht aktiv**; für eine Neuinstallation wird
die Aktivierung empfohlen:

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'   # 80 + 443
ufw --force enable
ufw status
```

### 14.5 WordPress-Ebene (läuft über `barmbini-core`, kein Server-Schritt)

- REST-API-Benutzer-Endpoints für Unberechtigte gesperrt
  (`Barmbini_Core_Rest_Api_Hardening`)
- Login-Brute-Force-Schutz (`Barmbini_Core_Login_Limiter`)

### 14.6 Zugangsdaten-Verwaltung

- DB-Credentials: `/root/barmbini-db.txt` (chmod 600)
- SMTP-Credentials: `/root/barmbini-mail.txt` (chmod 600)
- Keine Geheimnisse in Git/Doku ablegen.

---

## 15. Backups, Cron & Wartung

### 15.1 Backups

- `deploy.sh` (Modus A/B) erstellt vor jedem Deployment ein Server-Backup unter
  `/root/barmbini-backup-<zeitstempel>/` (DB-Dump `live-before-deploy.sql`).
- `fetch-backups.sh` holt diese Backups nach lokal und bereinigt den Server
  (Offsite-Kopie; lokal unter `server-backups/`, **nicht committen**).

### 15.2 Cron-Übersicht

| Cron | Zeit | Zweck |
|---|---|---|
| `/etc/cron.d/barmbini-stats` | 07:15 | Besucherstatistik-Aggregat |
| WP-Cron (barmbini-core) | alle 6 h | WP Fastest Cache leeren |
| WP-Cron (barmbini-core) | 08:00 | Aktionen-Start-Benachrichtigung |

### 15.3 Speicher-Wartung (Server ist knapp bemessen)

```bash
apt-get clean
rm -rf /var/lib/apt/lists/*
journalctl --vacuum-size=200M
# inaktive Standard-Themes entfernen, um Speicher zu sparen:
rm -rf /var/www/barmbini/wp-content/themes/{storefront,twentytwentyfive,twentytwentyfour,twentytwentythree,twentytwentytwo}
```

---

## 16. Abschluss-Verifikation (Checkliste)

- [ ] `nginx -t` ok, `php-fpm8.3 -t` ok
- [ ] `systemctl status nginx mariadb php8.3-fpm` → active (running)
- [ ] `curl -sI https://barmbini.de/` → `HTTP/2 200`
- [ ] `curl -sI http://barmbini.de/` → `301` auf https
- [ ] `curl -sI https://barmbini.de/readme.html` → `404`
- [ ] `curl -sI https://barmbini.de/xmlrpc.php` → `403`
- [ ] `curl -sI https://barmbini.de/ | grep -iE 'strict-transport|x-frame|x-content-type|referrer-policy|permissions-policy'` → alle Header vorhanden
- [ ] `/wp-admin/` erreichbar, Login funktioniert
- [ ] `wp plugin list --allow-root` → barmbini-core, woocommerce, wp-smtp aktiv
- [ ] `wp core version --allow-root` → 7.x
- [ ] Test-Mail (`wp_mail`) → `true`, in `wp_wpsmtp_logs` ohne Fehler
- [ ] `/var/lib/barmbini-stats/stats/stats-*.json` vorhanden (nach erstem Stats-Lauf)
- [ ] `/etc/cron.d/barmbini-stats` vorhanden
- [ ] Frontend: Startseite, Sortiment, Kontakt, Aktionen laden ohne PHP-Fehler
- [ ] SSH-Login nur noch mit Schlüssel möglich (Passwort-Login getestet abgelehnt)
- [ ] `ufw status` → active (falls aktiviert)

---

## 17. Rollback & Risiken

- **nginx-Config:** Backup vor jeder Änderung
  (`cp /etc/nginx/sites-available/barmbini /root/barmbini-nginx-backup-$(date +%F-%H%M%S)`).
- **PHP-FPM:** Backup `www.conf` unter `/root/`; Rollback =
  Konfiguration zurückkopieren + `php-fpm8.3 -t && systemctl reload php8.3-fpm`.
- **Datenbank:** `deploy.sh`-Backups unter `/root/barmbini-backup-*` ermöglichen
  die Wiederherstellung (`wp db import ...`).
- **SSH-Härtung:** Wenn der Schlüssel-Login nicht vorher getestet wurde, droht
  Aussperrung → Schritt 3.3 unbedingt zuerst ausführen.
- **Risiko RAM:** Bei zu vielen PHP-Workern drohen OOM-Kills (826 MB RAM) →
  `pm.max_children` konservativ lassen (3).
- **Risiko Modus A:** Ein `--full`-Deployment ersetzt die Live-DB → nur auf
  leerem/nicht produktivem System oder nach expliziter Freigabe verwenden.
