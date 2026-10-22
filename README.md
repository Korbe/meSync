# meSync

**meSync** ist die Verwaltungs-App von [korbitsch.at](https://korbitsch.at), einem Ein-Personen-Unternehmen aus Villach für Websites, Webanwendungen, PC-Hilfe und PC-Builds. Sie bündelt alles, was im Alltag anfällt, an einem Ort: Kunden, Rechnungen, Wartungsverträge mit Website-Überwachung, Inventar und ein Haushaltsbuch für privat und Firma.

Die Oberfläche ist komplett auf Deutsch, im dunklen Glas-Look von korbitsch.at (Teal `#1fadae`, Amber `#f5a524`) und lässt sich als App (PWA) auf PC und Handy installieren.

## Funktionen

| Bereich | Was meSync kann |
|---|---|
| **Kunden** | Privat- und Firmenkunden mit Titeln, Adresse, UID, Notizen und Interessen (PC-Hilfe, Website, Webanwendung, PC-Build). |
| **Rechnungen** | Fortlaufende Nummern (`2026-001`), Leistungskatalog mit Fix- und Zeittarif, Aufzählungen in Positionen, Zahlungen und Teilzahlungen, Barzahlung mit Quittungsstempel, Stornorechnungen. Das PDF wird bei jedem Download neu erzeugt (mit Logo, Wunschfarbe und SEPA-QR-Code) und nie gespeichert. |
| **Wartungsverträge** | Website- und Webapp-Betreuung mit jährlicher Verrechnung, Fälligkeits-Erinnerung per Mail, Vertragsinformation als PDF für den Kunden. |
| **Monitoring Center** | Prüft die Websites alle 5 Minuten: Status, Verfügbarkeit über 24 Std., 7 und 30 Tage, Antwortzeiten, SSL-Ablauf, Ausfall-Liste. Mails bei Ausfall, Entwarnung und ablaufendem Zertifikat. |
| **Inventar** | Gekaufte Geräte mit Beleg (privat gespeichert), GWG-Erkennung bis 1.000 € netto, lineare AfA mit Halbjahresregel, Anlagenverzeichnis als CSV. |
| **Finanzen** | Haushaltsbuch privat und Firma: Verträge und Fixkosten (auch geteilt und jährlich), schnelle Buchungen mit Summen wie in Excel (`5,92+6,50`), monatlicher Kontoabgleich zum Abhaken, Jahresübersicht mit verrechneten Beträgen und Planwerten. Zahlungseingänge aus Rechnungen und Inventarkäufe fließen automatisch ein. |
| **Export** | Für den Steuerberater: Rechnungen, Zahlungen und USt.-Übersicht als CSV (Excel-tauglich) oder als ZIP mit allen Rechnungs-PDFs. |
| **Backup** | Alle eigenen Daten als eine JSON-Datei herunterladen und wieder einspielen, wahlweise **anhängen** oder **alles ersetzen**. |
| **Demo** | Knopf „Mit Demo-Benutzer starten“ auf der Login-Seite: ein eigenes Konto mit Beispieldaten. Das Profil ist dort schreibgeschützt, es gehen keine Mails raus und die Website-Prüfungen werden nur simuliert. |

## Technologien

- **Backend:** [Laravel 13](https://laravel.com) (PHP 8.5) mit Jetstream und Fortify für Login, E-Mail-Bestätigung und Zwei-Faktor-Anmeldung. Die Registrierung ist deaktiviert.
- **Frontend:** [Vue 3](https://vuejs.org) über [Inertia 3](https://inertiajs.com) (ohne SSR), gebaut mit [Vite 8](https://vite.dev).
- **Styling:** [Tailwind CSS 4](https://tailwindcss.com) (CSS-first-Konfiguration in `resources/css/style.css`), Heroicons, Schrift Bricolage Grotesque.
- **Datenbank:** MySQL 8 bzw. MariaDB (Tests laufen auf SQLite im Speicher).
- **PDF:** [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf), QR-Codes mit [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode).
- **Dateien:** [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary) für das Logo (öffentlich) und Inventar-Belege (privat).
- **Routen im Frontend:** [Ziggy](https://github.com/tighten/ziggy).
- **PWA:** eigenes Manifest und eigener Service Worker (`public/manifest.json`, `public/sw.js`). Gecacht werden nur Programmdateien, nie Kundendaten.

---

## Server einrichten

Diese Anleitung beschreibt einen Linux-Server (z. B. Ubuntu 24.04) mit nginx, PHP-FPM und MySQL. Mit Apache oder Plesk funktioniert es genauso, nur die Webserver-Konfiguration sieht anders aus.

### 1. Voraussetzungen

| Was | Version / Hinweis |
|---|---|
| PHP | **8.5** (Ubuntu 26.04), mit FPM (Erweiterungen: mysql, mbstring, xml, curl, zip, gd, intl) |
| PHP-Erweiterungen | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip` |
| Datenbank | MySQL 8 oder MariaDB 10.6+ |
| Composer | 2.x |
| Node.js | **20.19+** oder **22.12+** (nur zum Bauen des Frontends, kann auch lokal passieren) |
| HTTPS | Pflicht: ohne HTTPS lässt sich die App nicht installieren (PWA) und Login-Cookies sind unsicher. Zertifikat z. B. mit Let's Encrypt (`certbot`). |

`gd` und `dom` braucht der PDF-Export, `zip` das ZIP für den Steuerberater, `openssl` die SSL-Prüfung im Monitoring und `fileinfo` das Erkennen von Logo- und Belegdateien.

### 2. Code holen und installieren

```bash
cd /var/www
git clone <repository-url> mesync
cd mesync

composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 3. `.env` anlegen

```bash
cp .env.example .env
php artisan key:generate
```

Die wichtigsten Einträge:

```dotenv
APP_NAME=meSync
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mesync.example.at
APP_LOCALE=de
APP_FALLBACK_LOCALE=de
APP_FAKER_LOCALE=de_AT

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mesync
DB_USERNAME=mesync
DB_PASSWORD=geheim

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database   # alle Mails gehen über die Queue, siehe Schritt 9

# Mails: Erinnerungen, Monitoring-Alarme, E-Mail-Bestätigung, Passwort vergessen
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.example.at
MAIL_PORT=465
MAIL_USERNAME=noreply@example.at
MAIL_PASSWORD=geheim
MAIL_FROM_ADDRESS=noreply@example.at
MAIL_FROM_NAME="meSync"
```

`APP_URL` muss genau die Adresse sein, unter der meSync erreichbar ist. Daraus entstehen die Links in Mails und die Logo-URLs. Die Zeitzone ist fest auf `Europe/Vienna` eingestellt (`config/app.php`).

### 4. Datenbank, Speicher und Rechte

```bash
php artisan migrate --force
php artisan storage:link          # öffentliches Logo unter /storage
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Der Webserver-Benutzer muss in storage/ und bootstrap/cache/ schreiben dürfen
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

Inventar-Belege liegen privat unter `storage/app/private` und werden nur über die App ausgeliefert.

### 5. Ersten Benutzer anlegen

Die Registrierung ist absichtlich deaktiviert. Den eigenen Zugang legst du einmalig auf dem Server an:

```bash
php artisan tinker --execute="App\Models\User::forceCreate(['name' => 'Vorname Nachname', 'email' => 'du@example.at', 'password' => Illuminate\Support\Facades\Hash::make('ein-sicheres-passwort'), 'email_verified_at' => now()]);"
```

Danach im Profil die Firmendaten (Adresse, IBAN, UID, Rechnungsfarbe) und das Logo eintragen, sonst fehlen sie auf den Rechnungen.

### 6. Webserver (nginx)

Das Document Root zeigt auf **`public/`**, nie auf das Projektverzeichnis selbst.

```nginx
server {
    listen 443 ssl http2;
    server_name mesync.example.at;
    root /var/www/mesync/current/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/mesync.example.at/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mesync.example.at/privkey.pem;

    # Backups (bis 100 MB) und Belege (bis 10 MB) hochladen
    client_max_body_size 110M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Service Worker und Manifest nie cachen, damit Updates sofort ankommen
    location = /sw.js         { add_header Cache-Control "no-cache"; }
    location = /manifest.json { add_header Cache-Control "no-cache"; }

    # Vite-Dateien haben einen Hash im Namen und ändern sich nie
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known) { deny all; }
}

server {
    listen 80;
    server_name mesync.example.at;
    return 301 https://$host$request_uri;
}
```

### 7. PHP-Einstellungen

In der `php.ini` von PHP-FPM (z. B. `/etc/php/8.5/fpm/php.ini`):

```ini
upload_max_filesize = 100M   ; Backup-Datei einspielen
post_max_size = 110M
memory_limit = 256M          ; PDFs und ZIP-Export
max_execution_time = 120     ; ZIP mit vielen Rechnungs-PDFs
```

Danach `sudo systemctl reload php8.5-fpm`.

### 8. Cronjob (wichtig!)

Ohne Cronjob gibt es **keine Website-Überwachung, keine Erinnerungsmails und kein Aufräumen**. Laravel braucht genau einen Eintrag, der jede Minute läuft. Den Rest regelt der Scheduler in `routes/console.php`.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/mesync/current && php artisan schedule:run >> /dev/null 2>&1
```

Was dadurch läuft:

| Befehl | Wann | Wozu |
|---|---|---|
| `sites:check` | alle 5 Minuten | Websites der Wartungsverträge prüfen, Verlauf speichern, Mails bei Ausfall, Entwarnung und SSL-Ablauf |
| `contracts:remind` | täglich 08:00 | Mail, wenn eine jährliche Wartungsrechnung in 7 Tagen fällig ist |
| `model:prune` (SiteCheck) | täglich 00:00 | Prüf-Verlauf älter als 30 Tage löschen (so weit schaut das Monitoring Center zurück) |

Kontrollieren lässt sich das mit:

```bash
php artisan schedule:list
php artisan sites:check        # einmal von Hand prüfen
```

Der Cronjob muss als derselbe Benutzer laufen wie PHP-FPM (`www-data`), sonst gehören neue Dateien in `storage/` dem falschen Benutzer.

### 9. Queue-Worker (wichtig!)

**Alle Mails** laufen über die Queue: Ausfall, Entwarnung, SSL-Warnung, Vertragserinnerung, E-Mail-Bestätigung und Passwort zurücksetzen. Sie landen zuerst in der Tabelle `jobs`, ein dauerhaft laufender Worker verschickt sie. **Ohne Worker geht keine einzige Mail raus** – es gibt dann auch keine Fehlermeldung, die Mails warten einfach.

Den Worker als systemd-Dienst einrichten, damit er nach Abstürzen und Neustarts von selbst wieder läuft:

```bash
sudo nano /etc/systemd/system/mesync-queue.service
```

```ini
[Unit]
Description=meSync Queue Worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/mesync/current
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --backoff=60 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now mesync-queue
sudo systemctl status mesync-queue      # muss "active (running)" zeigen
```

- `--tries=3 --backoff=60`: Ist der Mailserver kurz nicht erreichbar, wird jede Mail bis zu dreimal im Minutenabstand versucht.
- `--max-time=3600`: Der Worker startet sich stündlich neu (systemd holt ihn sofort zurück) – das hält den Speicherverbrauch klein.
- `current` ist der Symlink auf das aktuelle Release. Der Worker löst ihn beim Start auf und bleibt danach im alten Release-Ordner – deshalb nach jedem Deploy `php artisan queue:restart`, dann startet systemd ihn im neuen Release.

Kontrollieren:

```bash
php artisan queue:monitor database:default   # wartende Mails
php artisan queue:failed                     # endgültig fehlgeschlagene Mails (mit Fehlermeldung)
php artisan queue:retry all                  # fehlgeschlagene nochmal versuchen
```

Auf Shared Hosting ohne systemd geht ersatzweise ein zweiter Cronjob, der jede Minute die wartenden Mails abarbeitet:

```cron
* * * * * cd /var/www/mesync/current && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

### 10. Updates einspielen

**Mit Deployer (empfohlen):** Vom eigenen Rechner aus genügt

```bash
dep deploy
```

`deploy.php` holt den Code aus GitHub in ein neues Release, installiert die Composer-Pakete, **baut die Assets lokal** und lädt nur `public/build` hoch (kein Node.js am Server), führt Caches und Migrationen aus, schaltet den Symlink `current` um, lädt PHP-FPM neu (leert den OPcache) und startet den Queue-Worker mit `queue:restart` neu. `.env` und `storage/` liegen unter `shared/` und bleiben über alle Releases erhalten.

Damit der Benutzer `deployer` PHP-FPM neu laden darf, einmalig mit `sudo visudo -f /etc/sudoers.d/deployer`:

```
deployer ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.5-fpm
```

Die PHP-Version steht in `deploy.php` unter `php_fpm_service` – muss zu der am Server passen.

**Von Hand (ohne Deployer):**

```bash
cd /var/www/mesync/current
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize          # config, routes, views, events neu cachen
php artisan queue:restart     # Worker mit dem neuen Code neu starten
php artisan up
```

Wer auf dem Server kein Node.js haben will, baut lokal mit `npm run build` und lädt den Ordner `public/build` mit hoch.

`queue:restart` ist wichtig: Der Worker hält den Code im Speicher und würde sonst mit der alten Version weiterlaufen.

### 11. Demo-Benutzer (optional)

Der Knopf „Mit Demo-Benutzer starten“ legt beim ersten Klick den Benutzer `demo@mesync.test` an und füllt ihn mit Beispieldaten. Zurücksetzen auf frische Beispieldaten:

```bash
php artisan db:seed --force
```

Der Seeder fasst ausschließlich den Demo-Benutzer an. Für den Demo-Benutzer gilt:

- Das Profil ist schreibgeschützt.
- Es gehen keine Mails an ihn.
- Seine Websites werden nie wirklich angefragt, die Prüfungen sind simuliert.

### 12. Datensicherung

- **In der App:** unter *Backup* alle eigenen Daten als JSON (inklusive Logo und Belegen).
- **Am Server** zusätzlich regelmäßig die Datenbank (`mysqldump`) und den Ordner `storage/app` sichern.

---

## Lokale Entwicklung

Entwickelt wird unter Windows mit [Laragon](https://laragon.org) (Apache, MySQL, PHP 8.3). Die App läuft dort unter `http://mesync.test`.

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan storage:link

composer dev         # Vite mit Hot Reload + Queue-Worker (+ artisan serve, unter Laragon nicht nötig)
```

Wer nur `npm run dev` startet, braucht für Mails zusätzlich einen Worker in einem zweiten Terminal: `php artisan queue:work`.

- **Demo-Daten:** `php artisan db:seed`, oder einfach auf der Login-Seite „Mit Demo-Benutzer starten“ klicken.
- **Mails testen:** `MAIL_MAILER=log` schreibt sie nach `storage/logs/laravel.log` – aber erst, wenn ein Queue-Worker läuft.
- **Scheduler lokal:** `php artisan schedule:work` statt Cronjob.
- **PWA lokal testen:** In Laragon SSL aktivieren (Menü → Apache → SSL) und `https://mesync.test` öffnen. Über `http` bieten Browser keine Installation an.

### Tests

```bash
php artisan test
```

Die Feature-Tests laufen auf SQLite im Speicher und decken unter anderem diese Bereiche ab:

- Rechnungen, Storno und Zahlungen
- Export und Backup (Hin- und Rückweg)
- Inventar-Abschreibung
- Finanz-Jahresrechnung und Kontoabgleich
- Monitoring
- Demo-Schutz

## Projektstruktur (Auszug)

```
app/
  Console/Commands/      sites:check, contracts:remind
  Enums/                 Kategorien, Vertragsarten, Zahlungsarten
  Http/Controllers/      eine Datei pro Bereich
  Http/Middleware/       ProtectDemoProfile (Demo-Profil schreibgeschützt)
  Models/                Customer, Invoice, MaintenanceContract, InventoryItem, RecurringExpense, …
  Notifications/         Mails (Ausfall, Entwarnung, SSL, fällige Verträge)
  Services/              InvoicePdf, ContractPdf, SiteMonitor, MonitoringReport, FinanceOverview, Backup, AccountingExport
database/seeders/        DatabaseSeeder steuert die Demo-Daten
resources/js/
  Pages/                 Inertia-Seiten pro Bereich
  components/            V*-Komponenten (VButton, VInput, VModal, VConfirmDialog, …)
resources/views/pdf/     Rechnungs- und Vertrags-PDF
public/                  manifest.json, sw.js, offline.html, Icons
```
