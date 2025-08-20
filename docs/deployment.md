# Deployment mit Deployer

Diese Anleitung beschreibt den Einsatz von [Deployer](https://deployer.org) zum automatischen Ausrollen der Anwendung.

## Vorbereitung

1. Beispieldatei kopieren und anpassen:
   ```bash
   cp deploy.php.dist deploy.php
   ```
2. In `deploy.php` den GitHub Personal Access Token (`GITHUB_PAT`) und die Ziel-Hosts eintragen.

## Deployment ausführen

Die Datei `deployer.phar` liegt im Projektverzeichnis. Ein Deployment wird mit folgendem Befehl gestartet:

```bash
php deployer.phar deploy prd
```

Dabei bezeichnet `prd` den in `deploy.php` definierten Host. Weitere Details bietet die [Dokumentation von Deployer](https://deployer.org/docs).

