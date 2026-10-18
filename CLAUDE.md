# CLAUDE.md – PostVoyage

Arbeitsanweisungen für Claude (und Menschen) in diesem Repository. Diese Datei ist die
**verbindliche Quelle** für Aufbau, Konventionen und Arbeitsweise. Wenn Code und diese
Datei auseinanderlaufen, wird im selben Arbeitsschritt beides angeglichen.

## 1. Projekt in einem Satz

**PostVoyage – Free Social Media Post Distributor** ist ein kostenloses WordPress-Plugin, das
veröffentlichte Beiträge automatisch auf Social-Media-Kanäle verteilt. Gesteuert wird das über
Regeln je Kategorie, Inhaltstyp und Sprache und über Textvorlagen mit Variablen.

- Slug / Ordner / Text Domain: `postvoyage-socialmedia-poster`
- PHP-Namespace: `PostVoyage\` (PSR-4 in `src/`)
- Präfix für Optionen, Hooks, Meta, Cron: `postvoyage_` (Meta: `_postvoyage_`)
- Mindestversionen: WordPress 6.5, PHP 7.4 · getestet bis WordPress 7.1
- Lizenz: GPL-2.0-or-later
- Lokale Entwicklung: Laragon, `C:\laragon\www\productive-books.org\wp-content\plugins\postvoyage-socialmedia-poster`

## 2. Befehle

```bash
composer install          # Dev-Tools (PHPCS/WPCS, PHPCompatibility, PHPUnit)
composer lint             # Coding Standards prüfen (WordPress + PHP 7.4-Kompatibilität)
composer fix              # automatisch korrigierbare Verstöße beheben
composer test             # Unit-Tests (ohne WordPress)
composer check            # lint + test – muss vor jedem Commit grün sein
composer build            # dist/postvoyage-socialmedia-poster.zip (nur Laufzeitdateien)

# Übersetzungen (WP-CLI, im Plugin-Ordner)
wp i18n make-pot . languages/postvoyage-socialmedia-poster.pot --exclude=vendor,tests,dist
wp i18n update-po languages/postvoyage-socialmedia-poster.pot languages/
wp i18n make-mo languages/ && wp i18n make-php languages/
```

Laufzeit braucht **kein** Composer: Der Autoloader steckt in `postvoyage-socialmedia-poster.php`.
`vendor/` ist reines Dev-Werkzeug und landet nie im ZIP.

## 3. Architektur

```
postvoyage-socialmedia-poster.php   Plugin-Header, Konstanten, Autoloader, Hooks für Aktivierung
uninstall.php                       Löscht Daten nur, wenn in den Einstellungen gewünscht
src/
  Plugin.php                        Bootstrap: verdrahtet alle Komponenten mit Hooks
  Installer.php                     Log-Tabelle (dbDelta), Defaults, Schema-Version, Upgrades
  Storage/                          Persistenz: Settings, Account(+Repository), RuleRepository, PostMeta
  Support/Crypto.php                Verschlüsselung von Zugangsdaten (libsodium, Schlüssel aus WP-Salts)
  Content/                          Message, TemplateEngine, TextFitter, Hashtags, Language, Media
  Distribution/                     Scheduler (Cron/Action Scheduler), RuleMatcher, Distributor
  Networks/                         NetworkInterface, AbstractNetwork, Registry + je Netzwerk eine Klasse
  Http/                             Multipart, OAuth1 (reines PHP, unit-getestet)
  Log/Logger.php                    Tabelle {prefix}postvoyage_log
  Admin/                            Menü, Seiten (Konten, Regeln, Protokoll, Einstellungen), MetaBox
assets/                             admin.css, admin.js (Vanilla JS, kein Build-Schritt)
languages/                          .pot, de_DE (.po/.mo/.l10n.php)
tests/Unit/                         PHPUnit-Tests für reine PHP-Klassen
tests/manual/                       HTTP-Mock + Smoke-Test für lokales WordPress (siehe 6.)
```

### Ablauf beim Veröffentlichen

1. `transition_post_status` → `Scheduler::on_transition()` reagiert nur auf den **ersten** Wechsel
   nach `publish` (auch geplante Beiträge). Revisionen, Autosaves und Importe werden ignoriert.
2. Der Job `postvoyage_distribute` wird mit Verzögerung (Einstellung, Standard 60 s) eingeplant.
   Das läuft über WP-Cron oder über Action Scheduler, falls vorhanden. Die Verzögerung ist nötig:
   Der Block-Editor speichert Meta-Boxen erst **nach** dem Statuswechsel.
3. `Distributor::distribute()` sucht passende Regeln (`RuleMatcher`) und bildet die Paare
   Konto → Regel. Erste Regel gewinnt.
4. Je Konto: Sperre setzen (`PostMeta::lock`), Text rendern (`TemplateEngine` → `TextFitter`),
   `Network::publish()` aufrufen, Ergebnis protokollieren (`Logger`) und am Beitrag speichern
   (`_postvoyage_shares`).
5. Fehler mit `retryable = true` (Timeout, HTTP 5xx/429) werden bis zu 3-mal neu versucht
   (`postvoyage_retry`, nach 5 und 10 Minuten).
6. Bereits erfolgreich geteilte Konten werden nie automatisch doppelt bedient. Erneut teilen geht
   nur bewusst über „Jetzt teilen“.

### Datenhaltung

| Was | Wo |
|---|---|
| Einstellungen | Option `postvoyage_settings` (Settings API) |
| Konten | Option `postvoyage_accounts` (autoload aus, Secrets verschlüsselt mit Präfix `pv1:`) |
| Regeln | Option `postvoyage_rules` (Reihenfolge = Priorität) |
| Pro Beitrag | Meta `_postvoyage_enabled`, `_postvoyage_message` (REST-fähig), `_postvoyage_shares` |
| Protokoll | Tabelle `{prefix}postvoyage_log`, Aufräumen per täglichem Cron |

## 4. Konventionen (verbindlich)

**Code-Stil**
- WordPress Coding Standards (WPCS 3) mit PSR-4-Dateinamen. Tabs, Yoda-Bedingungen, `array()`.
- **PHP-7.4-kompatibel bleiben:** kein `match`, keine Named Arguments, kein Nullsafe `?->`,
  keine Union Types, keine Constructor Promotion, keine `enum`/`readonly`. `composer lint` prüft das.
- Klassen sind `final`, außer sie sind zum Erweitern gedacht (`AbstractNetwork`, `NetworkException`).
- Jede Datei beginnt mit `defined( 'ABSPATH' ) || exit;`.
- Reine Logik (Textkürzung, Signaturen, Facets, Multipart) bleibt frei von WordPress-Funktionen und
  bekommt Unit-Tests.

**Sicherheit**
- Jede Eingabe wird bereinigt (`sanitize_*`, `absint`, `esc_url_raw`), jede Ausgabe escaped
  (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Jede schreibende Aktion prüft eine **Nonce** (`check_admin_referer` oder `check_ajax_referer`
  direkt im Handler) und die **Berechtigung**: `Plugin::capability()`, filterbar über
  `postvoyage_capability`, oder `edit_post`.
- Secrets werden nur verschlüsselt gespeichert (`Crypto`), nie im Formular zurückgegeben
  (Platzhalter „gespeichert“) und nie geloggt. Tokens in URLs werden über `redact()` entfernt.
- SQL nur mit `$wpdb->prepare()`.

**Übersetzung und Mehrsprachigkeit**
- Quelltexte auf **Englisch**, Text Domain immer als String-Literal `'postvoyage-socialmedia-poster'`.
- Platzhalter bekommen einen `/* translators: */`-Kommentar.
- Nach jeder Textänderung `.pot` neu erzeugen und `de_DE` nachziehen (Befehle in Abschnitt 2).
- Mehrsprachige Inhalte: Polylang und WPML über `Content\Language`. Kategorien werden zum Vergleich
  auf die Standardsprache abgebildet (`canonical_term_ids`).

**Erweiterbarkeit (öffentliche Hooks, nicht ohne Not umbenennen)**
- Filter: `postvoyage_networks`, `postvoyage_post_types`, `postvoyage_template_variables`,
  `postvoyage_variables_help`, `postvoyage_message`, `postvoyage_matching_rules`,
  `postvoyage_should_share`, `postvoyage_post_language`, `postvoyage_languages`,
  `postvoyage_webhook_payload`, `postvoyage_capability`, `postvoyage_facebook_api_version`,
  `postvoyage_linkedin_api_version`
- Action: `postvoyage_shared` (nach jedem Teilversuch)

## 5. Ein neues Netzwerk hinzufügen

1. `src/Networks/<Name>.php` anlegen: `final class <Name> extends AbstractNetwork`.
2. Implementieren: `id()`, `label()`, `help()`, `fields()`, `char_limit()`, ggf. `url_length()`,
   `send()` und `check()`. Fehler als `NetworkException` werfen, mit `retryable = true` bei
   vorübergehenden Problemen. HTTP immer über `$this->http()`.
3. Secret-Felder mit `'secret' => true` markieren. Sie werden dann automatisch verschlüsselt.
4. In `Networks\Registry::all()` eintragen.
5. Den HTTP-Mock in `tests/manual/http-mock.php` um die API ergänzen und den Smoke-Test laufen lassen.
6. README, `readme.txt` und CHANGELOG ergänzen, `.pot` aktualisieren.

## 6. Testen

- **Unit-Tests:** `composer test`. Neue reine Logik bekommt immer einen Test.
- **Smoke-Test im lokalen WordPress, ohne echte Posts:**
  1. `tests/manual/http-mock.php` nach `wp-content/mu-plugins/` kopieren. Der Mock fängt alle
     API-Aufrufe ab und protokolliert sie in `wp-content/http-mock.jsonl`.
  2. `wp eval-file wp-content/plugins/postvoyage-socialmedia-poster/tests/manual/smoke.php`
  3. Danach den Mock **wieder entfernen**. Er blockiert sonst echtes Teilen.
- **Manuell mit echten Konten:** pro Netzwerk ein Testkonto oder einen privaten Kanal verwenden.
  „Verbindung testen“ postet nichts.
- `WP_DEBUG` und `WP_DEBUG_LOG` sind lokal aktiv. `wp-content/debug.log` muss nach Tests frei von
  PostVoyage-Meldungen sein.
- Lokal (Laragon) läuft WP-Cron nur bei Seitenaufrufen. Zum sofortigen Ausführen:
  `wp cron event run --due-now`.

## 7. Arbeitsweise für Claude

1. **Erst lesen, dann ändern:** relevante Klassen und diese Datei lesen. Bestehende Muster
   übernehmen (Repository-Klassen, `AbstractNetwork`, Admin-Seiten mit `admin-post.php` und
   Post/Redirect/Get über `Notices`).
2. **Klein und vollständig:** Eine Änderung umfasst Code, Tests, Übersetzungen und Doku.
   Halbe Sachen werden nicht committet.
3. **Vor dem Commit:** `composer check` muss grün sein. Bei UI-Änderungen kommt der Smoke-Test bzw.
   ein kurzer Klick-Test dazu.
4. **Doku im Code:** jede Klasse und Methode mit DocBlock (WPCS verlangt das). Kommentare erklären
   das *Warum*, nicht das *Was*. Hooks bekommen DocBlocks im WordPress-Stil (`@param`).
5. **Version anheben** an drei Stellen gleichzeitig: Plugin-Header `Version`, `POSTVOYAGE_VERSION`
   und `Stable tag` in `readme.txt`. Dazu einen Eintrag in `CHANGELOG.md` und in den
   Changelog-Abschnitt von `readme.txt`.
6. **Datenbank:** Schemaänderungen nur über `Installer::DB_VERSION` hochzählen. `dbDelta` erledigt
   den Rest.
7. **Commits:** Conventional Commits (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`),
   Betreff im Imperativ, Text auf Deutsch oder Englisch, aber einheitlich pro Commit.
8. **Nichts erfinden:** API-Details (Endpunkte, Limits, Versionen) gegen die offizielle Doku prüfen
   und Quelle bzw. Annahme im Code-Kommentar nennen. API-Versionen (Graph, LinkedIn) sind
   filterbar und pro Konto einstellbar.
9. **Keine Secrets** in Code, Tests, Logs, Commits oder Screenshots.

## 8. Bekannte Grenzen und Roadmap

- Instagram, Pinterest und TikTok verlangen öffentlich erreichbare Bild-URLs bzw. eine App-Prüfung.
  Bis dahin bleibt der Webhook-Kanal (n8n, Make, Zapier) der Weg dorthin.
- OAuth-Login-Flows (statt Token einfügen) für LinkedIn, Threads und Facebook.
- Block-Editor-Seitenleiste (`PluginDocumentSettingPanel`) als Ergänzung zur Meta-Box.
- Vorschau des gerenderten Texts je Netzwerk in der Regel-Bearbeitung.
- Planung „erneut teilen“ (Evergreen-Posts) und Zeitfenster.
- Bei X hängt das Posting-Kontingent vom API-Tarif ab. In Hilfe und Doku ist das vermerkt.
