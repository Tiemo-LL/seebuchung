# Seebuchung

WordPress-Plugin für die Buchung von Tageskontingenten an verwalteten Tauchseen — entwickelt vom Landesverband Sporttauchen Rheinland-Pfalz e.V. (LVST), nutzbar für beliebige (Landes-)Verbände. Lizenz: GPLv2+.

## Funktionsumfang

- **Buchung ohne Benutzerkonto:** Kalender → ggf. Stundenwahl → Formular, Doppel-Opt-in per E-Mail, Self-Service-Storno über denselben Link (keine Erstattung)
- **Seen-Verwaltung:** Saison, Buchungsfenster, Tages- oder Stundenkontingente (Matrix Wochentag × Stunde), Öffnungszeiten Woche/Wochenende, Gruppengrößen, Preise, Nachttermine
- **PayPal-Zahlung** (Orders v2) für zahlungspflichtige Taucher — Buchung wird erst mit Capture gültig; Webhook als Fallback; CSV-Export für die Kasse
- **QR-Tauchbestätigung** (HMAC-signiert, am Handy vorzeigbar) mit mobiler **Kontrolleurs-Ansicht** (Tagesliste, QR-Prüfung, „kontrolliert"-Haken)
- **Blockaden-Self-Service:** Vereine beantragen Termine über einen Token-Link im Antragsfenster; Regeln (max. 2/Jahr/See, max. 3 Std.) werden automatisch geprüft; Genehmigung im Admin
- **Wochenbericht** je See an die zugeordneten Seeverantwortlichen
- **Saisonwechsel-Assistent** statt Datenbank-Reset: neue Saisondaten, DSGVO-Anonymisierung, **Mehrjahres-Statistik** bleibt dauerhaft
- **Rollen:** Seebuchung-Admin, Seeverantwortliche:r, Kontrolleur:in (eigene Capabilities)

Alle verbandsspezifischen Inhalte (Name, Texte, Gebühren, PayPal-Zugang, Seen) werden über die Einstellungen gepflegt — nichts ist hartcodiert. Mailtexte und Templates sind über Filter (`seebuchung_mail_*`, `seebuchung_template`) anpassbar.

Details zur Einrichtung: [docs/installation.md](docs/installation.md)

## Entwicklung

```bash
composer install          # Abhängigkeiten + Dev-Tools
composer phpcs            # WordPress Coding Standards
composer test             # PHPUnit (Unit-Tests, ohne WP)
npx @wordpress/env start  # lokale WP-Umgebung (Docker), Plugin vorinstalliert
```

Stammdaten aus dem LVST-Altsystem lassen sich per WP-CLI importieren:

```bash
wp seebuchung import-alt pfad/zum/dump.sql.gz
```

## Architektur in Kürze

- `includes/Domain/` — pure Fachlogik (Verfügbarkeits-Engine, Statemachine, Validierungen), vollständig unit-getestet
- `includes/Database/` — Repositories auf eigene Tabellen (`{prefix}seebuchung_*`, dbDelta-Migrationen mit Versionsflag)
- `includes/Service/` — Workflows (Buchung, Blockaden, PayPal, QR, Wochenbericht)
- `includes/Frontend/` + `templates/` — Shortcode `[seebuchung]`, serverseitig gerendert, mobile-first
- `includes/Admin/` — Verwaltungsseiten
