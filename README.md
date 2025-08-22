[![CI](https://github.com/N3XT0R/dashclip-delivery/actions/workflows/ci.yml/badge.svg)](https://github.com/N3XT0R/dashclip-delivery/actions/workflows/ci.yml)
[![Maintainability](https://qlty.sh/gh/N3XT0R/projects/dashclip-delivery/maintainability.svg)](https://qlty.sh/gh/N3XT0R/projects/dashclip-delivery)
[![Code Coverage](https://qlty.sh/gh/N3XT0R/projects/dashclip-delivery/coverage.svg)](https://qlty.sh/gh/N3XT0R/projects/dashclip-delivery)

# Dashclip-Delivery

## Projektbeschreibung

Dashclip-Delivery ist eine LaravelâAnwendung zum Verteilen von Videomaterial an verschiedene KanÃ¤le. Neue Videos werden
aus
einem UploadâVerzeichnis oder aus Dropbox eingelesen, auf einem konfigurierten Storage gespeichert und anschlieÃend fair
auf KanÃ¤le mit Quoten und Gewichtung verteilt. Die KanÃ¤le erhalten per EâMail signierte Links zu einer Angebotsseite,
auf der sie einzelne Videos oder eine ZIPâDatei mit begleitender `info.csv` herunterladen kÃ¶nnen. Nicht benÃ¶tigte Videos
lassen sich zurÃ¼ckgeben, und alle Downloads werden protokolliert.

## Funktionen

- **Ingest**: rekursives Scannen eines UploadâOrdners (lokal oder Dropbox) mit Deduplizierung per SHAâ256.
- **Verteilung**: Zuweisung neuer bzw. abgelaufener Videos an KanÃ¤le (gewichtetes RoundâRobin, Wochenquota).
- **Benachrichtigung**: Versand von EâMails mit temporÃ¤ren DownloadâLinks und Angebotsseiten.
- **Angebot & Download**: WeboberflÃ¤che zur Auswahl und zum ZIPâDownload ausgewÃ¤hlter Videos inkl. `info.csv` und
  Tracking der Abholungen.
- **Vorschauen**: Generierung kurzer MP4âClips mit `ffmpeg`.
- **DropboxâIntegration**: OAuthâAnbindung und automatisches Auffrischen von Tokens.

## Voraussetzungen

- PHP 8.4
- Composer
- Node.js & npm (fÃ¼r BuildâAssets)
- ffmpeg
- Eine von Laravel unterstÃ¼tzte Datenbank (z. B. SQLite)
- Optional: DropboxâApp mit ClientâID und âSecret

## Installation

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
```

## NÃ¼tzliche Befehle

| Befehl                              | Beschreibung                                                                                    |
|-------------------------------------|-------------------------------------------------------------------------------------------------|
| `php artisan ingest:unzip`          | Entpackt ZIP-Dateien aus einem Verzeichnis.                                                     |
| `php artisan ingest:scan`           | Durchsucht den UploadâOrdner und speichert neue Videos.                                         |
| `php artisan info:import`           | Importiert ClipâInfos aus einer `info.csv`.                                                     |
| `php artisan assign:distribute`     | Verteilt Videos auf KanÃ¤le.                                                                     |
| `php artisan notify:offers`         | Versendet Angebotslinks per E‑Mail.                |
        |
| `php artisan notify:reminders`      | Benachrichtigt Kanäle vor Ablauf über offene Angebote.                |
        |
| `php artisan assign:expire`         | Markiert abgelaufene Zuweisungen und blockiert KanÃ¤le temporÃ¤r.                                 |
| `php artisan dropbox:refresh-token` | Aktualisiert den Dropbox Token.                                                                 |
| `php artisan weekly:run`            | FÃ¼hrt Expire â Distribute â Notify hintereinander aus.                                          |
| `php artisan video:cleanup`         | LÃ¶scht heruntergeladene Videos, deren Ablauf seit der angegebenen Wochenzahl Ã¼berschritten ist. |

## Dokumentation

AusfÃ¼hrliche ErlÃ¤uterungen zu Aufbau und Nutzung finden sich im Verzeichnis [`docs`](docs):

- [Ãbersicht](docs/README.md)
- [Setup](docs/setup.md)
- [Werkzeuge](docs/tool.md)
- [Workflow](docs/workflow.md)
- [Deployment](docs/deployment.md)

## Tests

```bash
composer test
```

## Lizenz

MIT
