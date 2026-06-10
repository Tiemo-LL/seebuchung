---
created: 2026-06-10
status: Entwurf — zur Freigabe durch Tiemo / IT-Team
basis: analyse-altsystem.md + neue Anforderungen (Tiemo, 2026-06-10)
ziel: produktiv vor Saison 2027
---

# Feature-Spec: WordPress-Plugin "LVST Seeanmeldung"

## 1. Zielbild

Ein WordPress-Plugin auf lvst.de (IONOS), das das Altsystem vollständig ablöst: gleiche Fachlogik, modern umgesetzt — responsive, mit PayPal-Zahlung, Vereins-Self-Service für Blockaden, Jahresarchiv, digitaler Bestätigung und Kontrolleurs-Ansicht am See.

## 2. Übernommene Features (Parität zum Altsystem)

- **Seen-Verwaltung:** aktiv/inaktiv, Saison, Buchungsfenster (Wochen im Voraus), Tag- oder Stundenkontingent, Uhrzeiten Woche/Wochenende, max. Taucher pro Buchung, min. Anmelder (1), Preis pro Person, kostenpflichtig ja/nein, Info-Text
- **Kontingente:** Max-Werte je See × Wochentag × Stunde; Live-Restanzeige im Buchungskalender; manuelle Korrektur durch Admin
- **Nachttermine** als freigegebene Ausnahmen (21–24 Uhr)
- **Buchung ohne Account:** Name, Vorname, E-Mail, Telefon, Vereins-Nr. oder "kein LVST-Verein", Brevet Gruppenleiter, Anzahl Taucher/Zahler
- **Doppel-Opt-in per Mail** + Self-Service-Storno über denselben Link, Kontingent-Rückgabe
- **Blockaden** mit Wiederholung, ganzer Tag/Stunden, Vereinszuordnung; LVST-Veranstaltungen mit Vorrang
- **Wochenbericht an Seeverantwortliche** (PDF/Liste je See, automatischer Versand)
- **Admin:** Buchungsübersicht/Archiv, Statistik, Vereine/Verbände, Brevets, Benutzerverwaltung
- **DSGVO:** automatische Löschung personenbezogener Daten (28 Tage nach Tauchtag bzw. zum Saisonende), Widerspruchs-Hinweis, Datenschutzinfos

## 3. Neue Features

### F1 · PayPal-Zahlung (Kontoabgleich entfällt)
- Zahlungspflichtige Buchungen (Nicht-LVST-Taucher, 4,50 €/Tag/Person) werden direkt im Buchungsflow per **PayPal Checkout** bezahlt (Gastzahlung mit Karte möglich, kein PayPal-Konto nötig)
- Buchung wird erst nach Zahlungseingang (Webhook/Capture) gültig → kein Dienstag/Freitag-Rhythmus, keine manuelle Freigabe, kein Kontoabgleich
- Storno-Regel: **keine Erstattung** (entschieden 2026-06-10)
- Transaktions-ID an der Buchung, Export für Schatzmeister (CSV)
- LVST-PayPal-Konto vorhanden ✅

### F2 · Vereins-Blockaden per öffentlichem Link
- Antragsseite per **Vereins-Token-Link** (je Verein ein Link, von Leonel Wieser kommuniziert), **zeitlich begrenzt gültig**: Antragsfenster **1.10.–1.2.** fürs Folgejahr (konfigurierbar; außerhalb: Hinweisseite)
- Formular: Verein (Auswahl), Veranstaltungsart, See, Wunschtermin(e) + Uhrzeit (max. 3 Std.), Anzahl Taucher, Verantwortlicher, E-Mail/Telefon
- Regeln aus dem Altsystem automatisch geprüft: max. 2 Blockaden/Jahr/See/Verein, Tagessee = ganzer Tag
- Workflow: Antrag → Status "beantragt" → Admin genehmigt/lehnt ab (mit Mail an Verein) → genehmigte Blockade erscheint im Kalender
- Token pro Verein generierbar/widerrufbar im Admin; Token-Verteilung über Leonel Wieser

### F3 · Jahresarchiv statt Überschreiben
- Saisonwechsel als geführter Admin-Prozess statt "Restart": neues Jahr anlegen, altes Jahr bleibt **read-only archiviert**
- Personenbezogene Daten werden zum Jahreswechsel anonymisiert (DSGVO wie bisher), aber **Statistikdaten bleiben dauerhaft**: Buchungen/Auslastung je See, Tag, Stunde, Vereinszuordnung aggregiert
- Mehrjahres-Statistik im Admin (Auslastungsvergleich, Einnahmen)

### F4 · Digitale Tauchbestätigung (kein Ausdruck im Auto)
- Bestätigung als **QR-Code** (am Handy vorzeigbar, in Bestätigungsmail + abrufbar über Buchungslink); PDF bleibt als Option
- Kontrolle erfolgt digital durch Kontrolleure (F6) — Auslegen im Fahrzeug entfällt
- Hinweis: Verfahrensänderung ggf. mit Kreisverwaltung/Seenverantwortlichen abstimmen (Rechtsverordnungs-Auflagen)

### F5 · WordPress-Plugin, responsive
- Eigenes Plugin `lvst-seeanmeldung`, Buchungs-Frontend per Shortcode/Block auf beliebiger Seite (ersetzt see.lvst.de; Subdomain leitet um)
- Eigene DB-Tabellen (`wp_lvst_*`), normalisiert (Stunden als Zeilen, Foreign Keys); WP-Cron für Fristen/Löschung/Wochenmail; WP-Mail (SMTP via IONOS)
- **Mobile-first** Buchungsstrecke (Kalender → Uhrzeit → Formular → Zahlung), ein Template-Satz für alle Geräte
- Rollen über WP-Capabilities: `lvst_admin` (alles), `lvst_seeverantwortlicher` (eigener See: Buchungen einsehen, Berichte), `lvst_kontrolleur` (nur Kontroll-Ansicht F6)
- Sicherheit: Nonces/CSRF, prepared statements, gehashte Tokens, Rate-Limiting fürs öffentliche Formular

### F6 · Kontrolleurszugang für die See-Kontrolle
- Mobile Ansicht "Kontrolle": Login → See wählen → **heutige Buchungen** (Name Gruppenleiter, Verein, Anzahl, Uhrzeit, bezahlt-Status)
- **QR-Scan** der digitalen Bestätigung → gültig/ungültig + Buchungsdetails
- Read-only; einzige Aktion: "kontrolliert"-Haken (ersetzt `istAbgeholt`-Logik sinnvoll)
- Funktioniert auch bei schlechtem Netz: Tagesliste wird beim Öffnen gecacht

### F7 · Wiederverwendbar für andere Landesverbände
- Kein hartcodiertes LVST-Branding: Verbandsname, Logo, Kontaktadressen, Gebühren, Mailtexte, PayPal-Zugangsdaten und Seen vollständig über Einstellungen konfigurierbar
- Generischer Plugin-Name/Textdomain (z. B. `seeanmeldung`), LVST ist nur die erste Installation
- i18n-fähig (alle Strings über WP-Übersetzungsfunktionen), Open-Source-Lizenz GPLv2+ (WordPress-Standard), Verteilung über Git-Repo (später ggf. wordpress.org)
- Dokumentation: Installations-/Konfigurationsanleitung für fremde Admins

## 4. Explizit NICHT im Scope (v1)

- Vereinsliste / Tauchlehrerliste (eigene Projekte, Prio 2/3)
- Bezahlung per Überweisung als Fallback (nur falls Vorstand PayPal-only ablehnt — dann bleibt Aufwand!)
- Mehrsprachigkeit

## 5. Entscheidungen (Stand 2026-06-10, Tiemo)

1. ✅ **PayPal:** LVST-PayPal-Konto existiert. **Storno = keine Erstattung** (wie bisher).
2. ✅ **Digitale Bestätigung reicht** (Handy/QR) — kein Auslegen im Auto, keine Abstimmung nötig.
3. ✅ **Blockaden-Antrag mit Vereins-Token** (kein komplett öffentlicher Link).
4. ✅ **Antragsfenster: 1.10.–1.2.** (fürs Folgejahr). Kommunikation an die Vereine: Leonel Wieser.
5. ✅ **Hosting: Installation ins bestehende WordPress auf lvst.de** (kein separates WP).
6. ✅ **Wiederverwendbarkeit:** Plugin soll **anderen Landesverbänden** zur Verfügung gestellt werden → siehe F7.

## 6. Umsetzungsphasen

```
Phase 1: Datenmodell + Seen-/Kontingent-Verwaltung + Buchungsflow inkl.
         Doppel-Opt-in, Storno, Mails (Parität Kern)
Phase 2: PayPal (F1) + digitale Bestätigung mit QR (F4)
Phase 3: Blockaden-Self-Service (F2) + Kontrolleurs-Ansicht (F6)
Phase 4: Jahresarchiv/Statistik (F3), Wochenberichte, Migration + Parallelbetrieb,
         Saisonstart-Test mit einem See (z. B. Schlicht), dann Umstellung
```

## 7. Migration

- Stammdaten (Seen, Kontingente, Befugnisse, Vereine, Brevets, Kontakte) per Import-Skript aus MySQL-Dump
- Laufende Buchungen: Umstellung zum Jahreswechsel (leeres System), kein Buchungs-Import nötig
- see.lvst.de → Redirect auf neue WP-Seite; Info-Seiten (Tauchregelungen) als WP-Seiten neu
