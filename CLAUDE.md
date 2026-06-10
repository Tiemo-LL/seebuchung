# Seebuchung — WordPress-Plugin für Tauchsee-Buchungen

WP-Plugin, das das Alt-System "Seeanmeldung" (PHP-Eigenentwicklung 2004, zuletzt v8.4 auf PHP 8.4) des Landesverbands Sporttauchen Rheinland-Pfalz e.V. (LVST) ablöst. Taucher buchen ohne Account Tageskontingente an verwalteten Tauchseen; Nicht-Verbandsmitglieder zahlen per PayPal.

**Wichtig: Das Plugin ist generisch.** LVST ist nur die erste Installation — es wird anderen Landesverbänden zur Verfügung gestellt. Kein hartcodiertes LVST-Branding, keine hartcodierten Seen/Gebühren/Mailtexte. Alles über Settings. Lizenz: GPLv2+.

## Referenzdokumente (im LVST-Obsidian-Vault, ggf. Kopien in `docs/`)

- `~/Obsidian/LVST/workspace/seeanmeldung/feature-spec.md` — verbindliche Spec (F1–F7, Phasen)
- `~/Obsidian/LVST/workspace/seeanmeldung/analyse-altsystem.md` — Ist-Analyse Altsystem
- `~/Obsidian/LVST/workspace/seeanmeldung/alt/seeanmeldung_8_4/` — Alt-Code als Feature-Referenz (READ-ONLY, nie Code daraus übernehmen — nur Logik nachschlagen)

## Fachlogik-Kurzfassung

- **Seen** konfigurierbar: Saison, Buchungsfenster (Wochen im Voraus), Tag- ODER Stundenkontingent, Uhrzeiten Woche/Wochenende, max. pro Buchung, Preis/Person, kostenpflichtig ja/nein
- **Kontingente:** Max-Werte je See × Wochentag × Stunde; Live-Restzähler je Datum
- **Buchung ohne Account:** Kontaktdaten + Vereins-Nr. (oder "kein Verein" → zahlungspflichtig) + Brevet Gruppenleiter + Anzahl Taucher/Zahler. Doppel-Opt-in per Mail-Link (Token), Self-Service-Storno über denselben Link, Kontingent-Rückgabe
- **Zahlung (F1):** PayPal Checkout im Buchungsflow, Buchung erst nach Capture gültig. Keine Erstattung bei Storno. Transaktions-ID speichern, CSV-Export
- **Bestätigung (F4):** QR-Code (Handy reicht), PDF optional. Kontrolleur scannt QR → gültig/ungültig
- **Blockaden (F2):** Vereine beantragen Folgejahr-Blockaden über Token-Link (je Verein), Antragsfenster 1.10.–1.2. (konfigurierbar). Regeln: max. 2/Jahr/See/Verein, max. 3 Std. (Tagessee = ganzer Tag). Admin genehmigt → Kalender
- **Rollen:** `seebuchung_admin` (alles) · `seebuchung_seeverantwortlicher` (eigener See: einsehen, Wochenbericht) · `seebuchung_kontrolleur` (mobile Kontroll-Ansicht, read-only + "kontrolliert"-Haken)
- **Jahreswechsel (F3):** geführter Saisonwechsel, altes Jahr read-only archiviert; personenbezogene Daten anonymisieren (28 Tage nach Tauchtag bzw. Saisonende), aggregierte Statistik bleibt dauerhaft
- **Wochenbericht:** Cron-Mail mit Buchungsliste (PDF) je See an Seeverantwortliche

## Tech-Stack & Architektur

- PHP ≥ 8.1, WordPress ≥ 6.4, MySQL/MariaDB (IONOS Shared Hosting als Referenzumgebung — keine Exoten-Extensions!)
- Eigene Tabellen `{$wpdb->prefix}seebuchung_*`, normalisiert (Stunden als ZEILEN, nicht Spalten wie im Altsystem!), Migrationen über dbDelta + Versionsflag
- Struktur: `seebuchung.php` (Bootstrap) · `includes/` (Domain-Klassen, PSR-4 via Composer-Autoload) · `admin/` · `public/` · `templates/` · `languages/`
- Frontend: Shortcode `[seebuchung]` + Gutenberg-Block; Buchungsstrecke mobile-first, Vanilla JS oder Alpine.js (kein React-Build-Zwang)
- PayPal: offizielles PHP-SDK bzw. REST (Orders v2 + Webhooks), Sandbox-Creds in Settings, Webhook-Signaturprüfung
- QR: serverseitig generiert (endroid/qr-code o. ä.), signierter Payload (Buchungs-ID + HMAC), Prüf-Endpoint für Kontrolleure
- Mails: `wp_mail()` (SMTP konfiguriert der Host), Templates filterbar
- Cron: WP-Cron für Bestätigungsfristen, Anonymisierung, Wochenberichte

## Konventionen

- **Sicherheit zuerst:** Nonces für alle Formulare, `$wpdb->prepare()` überall, `esc_html/esc_attr/esc_url` beim Output, Capability-Checks pro Admin-Action, Tokens nur gehasht speichern, Rate-Limit auf öffentliche Endpoints
- **i18n:** alle Strings über `__()/_e()` mit Textdomain `seebuchung`. Quellsprache Deutsch (Zielgruppe sind deutsche Landesverbände)
- **Code-Style:** WordPress Coding Standards (phpcs mit WPCS), Prefix `seebuchung_` für Funktionen/Hooks/Optionen
- **Tests:** PHPUnit + wp-env (Docker) für Integrationstests; mindestens Kontingent-Logik, Buchungs-Statemachine und Blockaden-Regeln müssen getestet sein
- **Git:** Feature-Branches, kleine Commits mit klaren Messages; `main` ist immer installierbar
- **Deutsch** für Doku/Kommentare/Commits ok — Team ist deutschsprachig

## Buchungs-Statemachine

`angefragt` → (Mail-Link) → `bestätigt` → ggf. (PayPal Capture) → `bezahlt/gültig` → `kontrolliert` | `storniert` (jederzeit via Link, keine Erstattung) | `verfallen` (Frist abgelaufen, Cron)

Kostenlose Buchungen (Verbandsvereine) springen von `bestätigt` direkt auf `gültig`.

## Arbeitsweise

- Aufgaben und Phasen in `TASKS.md` — dort Status pflegen
- Bei fachlichen Unklarheiten: NICHT raten, Frage in TASKS.md unter "Offene Fragen" notieren (Tiemo entscheidet, ggf. Vorstand)
- Niemals Produktiv-Zugangsdaten (PayPal live, DB) ins Repo — `.env`/wp-config, `.gitignore` von Anfang an
- Referenzumgebung lokal: `wp-env` mit PHP 8.1 + aktuellem WP
