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
- ⬜ Admin: Seen-CRUD + Kontingent-Matrix + Saison-/Fenster-Einstellungen
- ✅ Verfügbarkeits-Engine: Restkontingent je See/Datum/Stunde (berücksichtigt Buchungen, Blockaden, Nachttermine, Saison, Buchungsfenster) — **mit Unit-Tests** — pure Domänenklasse + Repository, 19 Engine-Tests, End-to-End in wp-env verifiziert *(2026-06-11)*
- ⬜ Buchungs-Frontend (Shortcode/Block): Kalender → ggf. Stundenwahl → Formular → Zusammenfassung; mobile-first
- ⬜ Buchungs-Statemachine + Doppel-Opt-in-Mail (gehashter Token), Storno-Link, Verfall per Cron — **mit Tests**
- ⬜ Admin: Buchungsübersicht (filterbar je See/Datum), manuelle Stornierung
- ⬜ Rollen/Capabilities anlegen (`seebuchung_admin`, `seebuchung_seeverantwortlicher`, `seebuchung_kontrolleur`)

## Phase 2 · Zahlung + Bestätigung

- ⬜ PayPal Orders v2: Checkout im Flow für zahlungspflichtige Buchungen (Preisberechnung: Zahler × Preis/Person), Sandbox-Modus in Settings
- ⬜ Webhook-Endpoint mit Signaturprüfung; Buchung → `gültig` erst nach Capture; Transaktions-ID speichern
- ⬜ Keine-Erstattung-Logik bei Storno bezahlter Buchungen (Kontingent frei, Geld bleibt) + Hinweistexte
- ⬜ CSV-Export Zahlungen (Schatzmeister)
- ⬜ QR-Bestätigung: signierter Payload (HMAC), Anzeige auf Bestätigungsseite + in Mail; PDF-Download als Option
- ⬜ E2E-Test Sandbox: buchen → zahlen → bestätigen → stornieren

## Phase 3 · Blockaden-Self-Service + Kontrolle

- ⬜ Vereins-Token: Generierung/Widerruf im Admin, Token-Link-Seite
- ⬜ Antragsformular (nur im Fenster 1.10.–1.2., konfigurierbar): Regeln max. 2/Jahr/See/Verein, max. 3 Std. automatisch prüfen — **mit Tests**
- ⬜ Genehmigungs-Workflow: beantragt → genehmigt/abgelehnt + Mail an Verein; genehmigte Blockade in Verfügbarkeits-Engine
- ⬜ Kontrolleurs-Ansicht (mobile): Login → See → Tagesliste (gecacht), QR-Scan → gültig/ungültig, "kontrolliert"-Haken
- ⬜ Wochenbericht: Cron-Mail mit PDF-Buchungsliste je See an Seeverantwortliche

## Phase 4 · Jahresarchiv, Politur, Go-Live

- ⬜ Saisonwechsel-Assistent: neues Jahr anlegen, altes read-only; Anonymisierung (28 Tage nach Tauchtag / Saisonende) per Cron — **mit Tests**
- ⬜ Mehrjahres-Statistik (Auslastung je See/Tag/Stunde, Einnahmen, aggregiert)
- ⬜ F7-Härtung: alle LVST-Spezifika in Settings (Verbandsname, Logo, Texte, Gebühren), Installations-/Konfig-Doku für fremde Admins
- ⬜ Barrierefreiheit + Lasttest Buchungs-Frontend (Saisonstart-Peak)
- ⬜ Security-Review (Nonces, Escaping, SQL, Rate-Limits) — als eigener Review-Durchgang
- ⬜ Migration auf lvst.de: Plugin installieren, Stammdaten-Import, Testlauf mit einem See (Schlicht), see.lvst.de-Redirect, Info-Seiten als WP-Seiten
- ⬜ Go-Live zum Saisonstart 2027, Altsystem read-only abschalten

## Offene Fragen

- ✅ GitHub-Remote: github.com/Tiemo-LL/seebuchung (public), CI grün *(2026-06-11)*
- ✅ Docker via OrbStack installiert, wp-env läuft *(2026-06-11)*
- 🔄 Zugangsregeln (alt: `befugnisse`-Tabelle): regelt je See × Wochentag × Stunde, WER buchen darf (00=offen, 99=nur VDST, 09=nur LVST …). Live-Daten: Jägerweiher durchgehend nur VDST; Marxweiher bis 9 Uhr nur LVST, danach offen; Schlicht offen. Aktive Fachlogik (canDive() in der Buchungsvalidierung). **Vorläufige Entscheidung Tiemo (2026-06-11): wird im Neusystem erstmal NICHT abgebildet** — Mail an Leo (vize@lvst.de) raus, finale Entscheidung nach seiner Antwort. Falls doch nötig: als Zugangsregel-Spalte an `seebuchung_kontingente` + Filter in der Verfügbarkeits-Engine nachrüstbar

- ✅ Plugin-Name/Slug final: **Seebuchung** / `seebuchung` (entschieden 2026-06-10; generisch, kein Verbandsbezug im Namen)
- ⬜ PayPal-Webhook auf IONOS Shared Hosting verifizieren (Erreichbarkeit/SSL)
- ⬜ Verteilung an andere Verbände: nur GitHub oder auch wordpress.org-Review anstreben?
