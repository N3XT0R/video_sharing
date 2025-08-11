# Workflow

Der typische Ablauf zur Verteilung neuer Videos umfasst mehrere Schritte:

1. **Ingest** – Neue Dateien werden aus dem Upload-Verzeichnis oder von Dropbox eingelesen. Dabei werden Duplikate per SHA-256 erkannt und ignoriert.
2. **Verteilung** – Nicht zugeordnete oder abgelaufene Videos werden anhand von Gewichtung und Wochenquota den Kanälen zugewiesen.
3. **Benachrichtigung** – Jeder Kanal erhält eine E-Mail mit einem signierten Link zur Angebotsseite.
4. **Angebot und Download** – Auf der Angebotsseite können Kanäle einzelne Videos oder ein ZIP-Paket herunterladen. Alle Aktionen werden protokolliert.
5. **Rückgabe** – Nicht benötigte Videos lassen sich über die Weboberfläche zurückgeben und stehen anschließend anderen Kanälen zur Verfügung.
6. **Wöchentlicher Lauf** – Mit `php artisan weekly:run` lassen sich die Schritte Expire, Distribute und Notify in einem Durchlauf ausführen.

Dieser Workflow sorgt für eine faire und nachvollziehbare Verteilung der Inhalte.

