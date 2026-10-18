# PostVoyage – Free Social Media Post Distributor

Kostenloses WordPress-Plugin, das neue Beiträge automatisch auf Social-Media-Kanälen verteilt.
Du konfigurierst es über Regeln je Kategorie, Inhaltstyp und Sprache und über Textvorlagen mit
Variablen. Es gibt keine Pro-Version und keinen externen Dienst dazwischen.

| Netzwerk | Anbindung | Bild |
|---|---|---|
| Mastodon | Zugriffstoken | Upload oder Link-Vorschau |
| Bluesky | App-Passwort | Link-Karte mit Vorschaubild, klickbare Links und Hashtags |
| Telegram | Bot-Token | Foto mit Bildunterschrift |
| Discord | Webhook | Embed mit Bild |
| Facebook-Seite | Seiten-Token (Graph API) | Link-Vorschau |
| Threads | Long-lived Token (wird wöchentlich erneuert) | Bild bei öffentlicher Website |
| LinkedIn | Token (Posts API) | Artikel-Karte |
| X | OAuth 1.0a (API v2) | – |
| Webhook | URL + optionale HMAC-Signatur | Bild-URL im JSON |

## Schnellstart (lokal mit Laragon)

```bash
cd C:\laragon\www\productive-books.org\wp-content\plugins
git clone <dein-repo> postvoyage-socialmedia-poster
cd postvoyage-socialmedia-poster
composer install        # nur für Entwicklung (Lint, Tests)
```

Danach das Plugin im Backend aktivieren und unter **PostVoyage → Konten** das erste Konto verbinden.

## Entwicklung

```bash
composer check   # Coding Standards (WPCS, PHP 7.4+) und Unit-Tests
composer build   # dist/postvoyage-socialmedia-poster.zip
```

Architektur, Konventionen und Arbeitsweise stehen in [CLAUDE.md](CLAUDE.md).

## Lizenz

GPL-2.0-or-later
