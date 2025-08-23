# Filesystem

## Dropbox einrichten (optional)

Neue App einrichten auf https://www.dropbox.com/de/developers
![Dropbox](./assets/images/dropbox_app.png)

Erste Einstellungen konfigurieren (wie im Bild):
![Dropbox](./assets/images/dropbox_first_settings.png)

Der Name der Anwendung muss unique sein.

Anschließend sind der App Key und das App secret zu sichern,
wie aber auch die erlaubten Redirect Urls und Domains anzugeben wie hier im Bild:

![Dropbox](./assets/images/configuration.png)

App-Key und App-Secret werden in der .env als Keys angegeben:

```dotenv
DROPBOX_CLIENT_ID=
DROPBOX_CLIENT_SECRET=
```