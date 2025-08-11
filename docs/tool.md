# Werkzeuge

Die Anwendung stellt verschiedene Artisan-Befehle zur Verfügung, um Videos zu verwalten und zu verteilen. Nachfolgend sind die wichtigsten Befehle mit Kurzbeschreibung aufgeführt:

| Befehl | Aufgabe |
|--------|--------|
| `php artisan ingest:scan` | Durchsucht den Upload-Ordner und legt neue Videos an. |
| `php artisan info:import` | Importiert Metadaten aus einer `info.csv`. |
| `php artisan assign:distribute` | Verteilt Videos anhand von Quoten und Gewichtung auf Kanäle. |
| `php artisan notify:offers` | Versendet E-Mails mit Downloadlinks an die Kanäle. |
| `php artisan assign:expire` | Kennzeichnet abgelaufene Zuweisungen und blockiert Kanäle temporär. |
| `php artisan previews:generate` | Erzeugt kurze Vorschauclips mit `ffmpeg`. |
| `php artisan weekly:run` | Führt das wöchentliche Ablaufpaket Expire → Distribute → Notify aus. |

Weitere Details zu den einzelnen Befehlen können über `php artisan help <befehl>` abgerufen werden.

