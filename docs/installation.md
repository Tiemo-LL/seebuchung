# Seebuchung — Installation und Konfiguration

Anleitung für Verbands-Admins (F7: das Plugin ist generisch — diese Schritte gelten für jeden Verband, nicht nur den LVST).

## Voraussetzungen

- WordPress ≥ 6.4, PHP ≥ 8.1, MySQL/MariaDB (Shared Hosting reicht; keine besonderen PHP-Extensions nötig)
- Funktionierender Mail-Versand (`wp_mail`, beim Hoster meist SMTP — ggf. SMTP-Plugin einsetzen)
- Für Zahlungen: PayPal-Business-Konto des Verbands

## Installation

1. Plugin-Verzeichnis nach `wp-content/plugins/seebuchung` hochladen (bei Installation aus dem Git-Repo vorher `composer install --no-dev` ausführen, damit `vendor/` vorhanden ist) und im WP-Admin aktivieren. Beim Aktivieren werden Tabellen, Rollen und Cron-Jobs angelegt.
2. Eine Seite anlegen (z. B. „Tauchsee buchen") und den Shortcode `[seebuchung]` einfügen.
3. **Seebuchung → Einstellungen:** Verbandsname, Kontakt-E-Mail, die eben angelegte Buchungsseite, Bestätigungsfrist und Anonymisierungsfrist setzen.
4. **Seebuchung → Seen:** Seen anlegen — Modus (Tag/Stunde), Saison, Buchungsfenster, Öffnungszeiten, Preise und die Kontingent-Matrix füllen. Erst „aktiv" geschaltete Seen sind buchbar.
5. Brevets und Vereine pflegen (beim LVST: Import aus dem Altsystem per `wp seebuchung import-alt <dump>`).

## PayPal einrichten

1. Im [PayPal-Developer-Portal](https://developer.paypal.com/) eine App anlegen → Client-ID und Secret in **Einstellungen → PayPal** eintragen (erst mit Sandbox testen, dann Live-Daten + Haken „Sandbox" entfernen).
2. Dort einen **Webhook** auf `https://DEINE-DOMAIN/wp-json/seebuchung/v1/paypal-webhook` mit dem Event `PAYMENT.CAPTURE.COMPLETED` anlegen und die Webhook-ID in den Einstellungen hinterlegen.
3. Testbuchung mit Sandbox-Käuferkonto durchspielen: buchen → bestätigen → zahlen → Status „gültig" + QR.

## Benutzer und Rollen

| Rolle | Kann |
|---|---|
| Seebuchung-Admin | alles (Seen, Buchungen, Blockaden, Einstellungen) |
| Seeverantwortliche:r | Buchungen einsehen, erhält Wochenberichte |
| Seebuchung-Kontrolleur:in | nur die mobile Kontroll-Ansicht |

WordPress-Administratoren haben automatisch alle Rechte. **Seeverantwortliche** ihren Seen zuordnen: Benutzerprofil öffnen → „Seebuchung: Seeverantwortung" → Seen ankreuzen. Der Wochenbericht geht am konfigurierten Wochentag automatisch raus.

## Blockaden-Self-Service für Vereine

1. **Seebuchung → Vereine:** je Verein „Token generieren" — der angezeigte Link ist nur einmal sichtbar; an den Verein weitergeben.
2. Vereine beantragen darüber im Antragsfenster (Standard 1.10.–1.2., konfigurierbar) Blockaden fürs Folgejahr; Regeln werden automatisch geprüft.
3. **Seebuchung → Blockaden:** Anträge genehmigen/ablehnen — der Verein bekommt automatisch eine Mail, genehmigte Termine sperren das Kontingent.

## Kontrolle am See

Kontrolleur:innen melden sich am Handy im WP-Admin an → **Seebuchung → Kontrolle** → See wählen. Dort: heutige Buchungen, QR-Code-Prüfung (Inhalt aus einer beliebigen Scanner-App einfügen) und der „kontrolliert"-Haken.

## Saisonwechsel (jährlich)

**Seebuchung → Saisonwechsel:** neue Saisondaten je See eintragen (Vorschlag: Vorjahr + 1) und ausführen. Vergangene Buchungen werden DSGVO-konform anonymisiert; die **Statistik** (Seebuchung → Statistik) bleibt über alle Jahre erhalten. Unabhängig davon anonymisiert ein täglicher Cron alle Buchungen, deren Tauchtag länger als die konfigurierte Frist (Standard 28 Tage) zurückliegt.

## Anpassung ohne Code-Änderung

- Mailtexte: Filter `seebuchung_mail_betreff` / `seebuchung_mail_text` (Parameter: Text, Typ, Buchung)
- Frontend-Templates: Filter `seebuchung_template` — eigene Template-Dateien je Schritt
- Alle Strings sind übersetzbar (Textdomain `seebuchung`)
