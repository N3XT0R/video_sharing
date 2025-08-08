# Video Sharing Plattform

Dieses Projekt ist eine Laravel‑Anwendung zum Verteilen von Videomaterial an verschiedene Kanäle. Neue Videos werden aus einem Upload‑Verzeichnis oder aus Dropbox eingelesen, auf einem konfigurierten Storage gespeichert und anschließend fair auf Kanäle mit Quoten und Gewichtung verteilt. Die Kanäle erhalten per E‑Mail signierte Links zu einer Angebotsseite, auf der sie einzelne Videos oder eine ZIP‑Datei mit begleitender `info.csv` herunterladen können. Nicht benötigte Videos lassen sich zurückgeben, und alle Downloads werden protokolliert.

## Funktionen

- **Ingest**: rekursives Scannen eines Upload‑Ordners (lokal oder Dropbox) mit Deduplizierung per SHA‑256.
- **Verteilung**: Zuweisung neuer bzw. abgelaufener Videos an Kanäle (gewichtetes Round‑Robin, Wochenquota).
- **Benachrichtigung**: Versand von E‑Mails mit temporären Download‑Links und Angebotsseiten.
- **Angebot & Download**: Weboberfläche zur Auswahl und zum ZIP‑Download ausgewählter Videos inkl. `info.csv` und Tracking der Abholungen.
- **Vorschauen**: Generierung kurzer MP4‑Clips mit `ffmpeg`.
- **Dropbox‑Integration**: OAuth‑Anbindung und automatisches Auffrischen von Tokens.

## Voraussetzungen

- PHP >= 8.2
- Composer
- Node.js & npm (für Build‑Assets)
- ffmpeg
- Eine von Laravel unterstützte Datenbank (z. B. SQLite)
- Optional: Dropbox‑App mit Client‑ID und ‑Secret

## Installation

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
```

## Nützliche Befehle

| Befehl | Beschreibung |
|--------|--------------|
| `php artisan ingest:scan` | Durchsucht den Upload‑Ordner und speichert neue Videos. |
| `php artisan info:import` | Importiert Clip‑Infos aus einer `info.csv`. |
| `php artisan assign:distribute` | Verteilt Videos auf Kanäle. |
| `php artisan notify:offers` | Versendet Angebotslinks per E‑Mail. |
| `php artisan assign:expire` | Markiert abgelaufene Zuweisungen und blockiert Kanäle temporär. |
| `php artisan previews:generate` | Erzeugt Vorschau‑Clips. |
| `php artisan weekly:run` | Führt Expire → Distribute → Notify hintereinander aus. |

## Tests

```bash
composer test
```

## Lizenz

MIT
