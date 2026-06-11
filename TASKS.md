# Seebuchung — Aufgabenplan

Status: ⬜ offen · 🔄 in Arbeit · ✅ fertig — bitte direkt hier pflegen.

## Phase 0 · Setup

- ✅ Repo initialisieren: Plugin-Skelett (`seebuchung.php`, Verzeichnisstruktur lt. CLAUDE.md), `.gitignore`, GPLv2+-Lizenz, readme.txt (WP-Format) *(2026-06-10)*
- ✅ Composer: Autoload (PSR-4), phpcs + WPCS, PHPUnit — lokal validiert: phpcs grün, 4 Tests grün *(2026-06-10)*
- ✅ wp-env-Setup (PHP 8.1, aktuelles WP), CI-Grundlage (phpcs + Tests bei Push) — wp-env läuft via OrbStack (Plugin aktiv in WP 7.0), CI grün auf github.com/Tiemo-LL/seebuchung *(2026-06-11)*
- ✅ `docs/`: feature-spec.md + analyse-altsystem.md aus dem Vault hineinkopiert *(2026-06-10)*

## Phase 1 · Kern (Parität Altsystem)

- ✅ Datenmodell + Migrationen: `seebuchung_seen`, `seebuchung_kontingente` (See × Wochentag × Stunde als Zeilen), `seebuchung_buchungen`, `seebuchung_blockaden`, `seebuchung_vereine`, `seebuchung_brevets`, `seebuchung_nachttermine`, Settings via Options-API — dbDelta-Migration mit Versionsflag, in wp-env verifiziert (7 Tabellen, idempotent) *(2026-06-11)*
- ✅ Import-Skript Stammdaten aus Alt-Dump (`alt/.../backup/*.sql.gz`): Seen, Kontingente, Vereine, Brevets — WP-CLI `wp seebuchung import-alt <datei>`, nur in leere Tabellen; mit echtem Dump verifiziert (3 Seen, 161 Kontingente, 47 Vereine, 14 Brevets; Engine-Werte = Altsystem). „Kein Verein"-Platzhalter wird übersprungen (→ verein_id NULL). **Kontakte folgen mit dem Rollen-Task** (werden WP-User) *(2026-06-11)*
- ✅ Admin: Seen-CRUD + Kontingent-Matrix + Saison-/Fenster-Einstellungen — Menü „Seebuchung" mit Seen-Editor (alle Felder, Matrix Wochentag × Stunde bzw. Tagesspalte) + Einstellungsseite (Verband, Fristen, Buchungsseite, PayPal-Felder) *(2026-06-11)*
- ✅ Verfügbarkeits-Engine: Restkontingent je See/Datum/Stunde (berücksichtigt Buchungen, Blockaden, Nachttermine, Saison, Buchungsfenster) — **mit Unit-Tests** — pure Domänenklasse + Repository, 19 Engine-Tests, End-to-End in wp-env verifiziert *(2026-06-11)*
- ✅ Buchungs-Frontend (Shortcode `[seebuchung]`): Seeauswahl → Kalender → Stundenwahl → Formular → Token-Statusseite; mobile-first, serverseitig gerendert, PRG über admin-post; Templates per Filter überschreibbar (F7). Gutenberg-Block folgt in der Politur *(2026-06-11)*
- ✅ Buchungs-Statemachine + Doppel-Opt-in-Mail (gehashter Token), Storno-Link, Verfall per Cron — **mit Tests** — E2E in wp-env: anfragen → Mail-Token → bestätigen (kostenlos→gültig, zahlungspflichtig→wartet auf PayPal/Phase 2) → stornieren (Kontingent frei) → Verfall-Cron. **Wichtige Korrektur:** Alt-Semantik `isKostenpflichtig` = „ALLE Taucher zahlen", Zahler zahlen immer 4,50 € (unabhängig vom Flag) *(2026-06-11)*
- ✅ Admin: Buchungsübersicht (filterbar je See/Datum/Status), manuelle Stornierung mit Mail an die buchende Person — in wp-env verifiziert *(2026-06-11)*
- ✅ Rollen/Capabilities (`seebuchung_admin`, `seebuchung_seeverantwortlicher`, `seebuchung_kontrolleur`) + Caps, WP-Admins erhalten alle; uninstall.php *(2026-06-11)*

## Phase 2 · Zahlung + Bestätigung

- ✅ PayPal Orders v2: Checkout im Flow (Zahlen-Button auf der Token-Statusseite → Order → Approve → Capture), Preis = Zahler × Preis/Person, Sandbox-Modus in Settings; eigener REST-Client über WP-HTTP-API (kein SDK) *(2026-06-11)*
- ✅ Webhook-Endpoint `/wp-json/seebuchung/v1/paypal-webhook` mit Signaturprüfung (verify-webhook-signature); `gültig` erst nach Capture, idempotent (Return-Flow + Webhook doppelt sicher); Capture-ID an der Buchung *(2026-06-11)*
- ✅ Keine-Erstattung-Logik: Storno setzt nur Status (Kontingent frei, Geld bleibt), Hinweistexte in Formular, Storno-Dialog und Mail *(2026-06-11)*
- ✅ CSV-Export Zahlungen (Schatzmeister) — Button in der Buchungsübersicht *(2026-06-11)*
- 🔄 QR-Bestätigung: signierter Payload (HMAC, offline prüfbar), QR-SVG auf der Statusseite (ohne gd-Abhängigkeit); Mail **verlinkt** auf die QR-Seite statt einzubetten (Text-Mails). Offen: optionaler PDF-Download (→ Phase 4 Politur) *(2026-06-11)*
- ⬜ E2E-Test Sandbox: buchen → zahlen → bestätigen → stornieren — **braucht PayPal-Sandbox-Zugangsdaten von Tiemo** (Client-ID/Secret/Webhook-ID in Seebuchung → Einstellungen eintragen); Code-Flow ohne PayPal-Gegenstelle in wp-env verifiziert

## Phase 3 · Blockaden-Self-Service + Kontrolle

- ✅ Vereins-Token: Generierung/Widerruf im Admin (Seite „Vereine", Klartext-Link einmalig sichtbar, nur Hash gespeichert), Token-Link-Seite `?sb_verein=…` *(2026-06-11)*
- ✅ Antragsformular (nur im Fenster, konfigurierbar; Zieljahr-Logik über Jahreswechsel): Regeln max. 2/Jahr/See/Verein, max. 3 Std., Tagessee = ganzer Tag — **mit Tests** (11 Validierungs-Tests) *(2026-06-11)*
- ✅ Genehmigungs-Workflow: Admin-Seite „Blockaden" mit Genehmigen/Ablehnen + Mail an Verein; genehmigte Blockade wirkt in der Engine (E2E verifiziert: 12 reservierte Taucher sperren Slot) *(2026-06-11)*
- ✅ Kontrolleurs-Ansicht (Admin-Seite „Kontrolle", Cap kontrollieren): See → Tagesliste, QR-Payload-Prüfung (Eingabefeld für Scanner-Apps) → GÜLTIG/UNGÜLTIG, „kontrolliert"-Haken (nur aus gültig). Kamera-Scan direkt im Browser + Offline-Cache → Politur Phase 4 *(2026-06-11)*
- ✅ Wochenbericht: täglicher Cron, Versand am konfigurierten Wochentag (Default Freitag) je See an zugeordnete Seeverantwortliche (Zuordnung im WP-Benutzerprofil); HTML-Tabellen-Mail der kommenden 7 Tage. **PDF-Anhang → Phase 4 Politur** *(2026-06-11)*

## Phase 4 · Jahresarchiv, Politur, Go-Live

- ✅ Saisonwechsel-Assistent: neue Saisondaten je See (Vorschlag +1 Jahr), Anonymisierung vergangener Buchungen; täglicher Cron anonymisiert nach Frist (28 Tage) — **mit Tests**, E2E verifiziert (PII weg, Statistikfelder bleiben) *(2026-06-11)*
- ✅ Mehrjahres-Statistik: Jahr × See (Buchungen, Taucher, Einnahmen, Stornos, Verfall) + Top-Stunden — übersteht Anonymisierung *(2026-06-11)*
- ✅ F7-Härtung: alle Verbands-Spezifika in Settings, Mailtexte/Templates filterbar, README + docs/installation.md für fremde Admins. Hinweis Verteilung: `composer install --no-dev` nötig (vendor/ nicht im Repo) *(2026-06-11)*
- 🔄 Barrierefreiheit + Lasttest: Grundlagen drin (Labels, role-Attribute, mobile-first, Touch-Ziele); systematischer A11y-Durchgang + Lasttest auf Zielumgebung offen
- ⬜ Security-Review (Nonces, Escaping, SQL, Rate-Limits) — als eigener Review-Durchgang. Stand: WPCS-Security-Sniffs 0 Befunde, Nonces/Caps/prepare/Token-Hashing/Rate-Limit (10/10 min je IP) implementiert; unabhängiger Durchgang (z. B. /security-review) vor Go-Live empfohlen
- ⬜ Migration auf lvst.de: Plugin installieren, Stammdaten-Import, Testlauf mit einem See (Schlicht), see.lvst.de-Redirect, Info-Seiten als WP-Seiten
- ⬜ Go-Live zum Saisonstart 2027, Altsystem read-only abschalten

## Offene Fragen

- ✅ GitHub-Remote: github.com/Tiemo-LL/seebuchung (public), CI grün *(2026-06-11)*
- ✅ Docker via OrbStack installiert, wp-env läuft *(2026-06-11)*
- ✅ Zugangsregeln (alt: `befugnisse`-Tabelle, Jägerweiher nur VDST / Marxweiher vormittags nur LVST): **entfällt endgültig — Leo (2026-06-11): „Wird nicht gebraucht!"** Nicht ins Neusystem übernommen. Konsequenz: an allen Seen können künftig auch Gäste ohne Verein buchen (gegen Gebühr). Falls je wieder nötig: Zugangsregel-Spalte an `seebuchung_kontingente` + Engine-Filter nachrüstbar

- ✅ Plugin-Name/Slug final: **Seebuchung** / `seebuchung` (entschieden 2026-06-10; generisch, kein Verbandsbezug im Namen)
- ⬜ PayPal-Webhook auf IONOS Shared Hosting verifizieren (Erreichbarkeit/SSL)
- ⬜ Verteilung an andere Verbände: nur GitHub oder auch wordpress.org-Review anstreben?
