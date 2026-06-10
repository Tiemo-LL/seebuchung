---
created: 2026-06-10
quelle: workspace/seeanmeldung/alt/seeanmeldung_8_4/ (Code) + backup/20260610.sql.gz (Schema) + see.lvst.de/infos
status: abgeschlossen
---

# Ist-Analyse Altsystem "Seeanmeldung" (v8.4)

## Überblick

Eigenentwicklung von Kay Koch (2004/2005, TSS-Bitburg), portiert auf PHP 7 (Peter Brunner) und PHP 8.4 (Thomas Bantz). Lizenzfrei ("frei verfügbar, keine Lizenzen") — Neubau rechtlich unproblematisch. Kein Framework: eigene Template-Engine (`class.TEMPLATE`), eigenes DB-Layer, FPDF für PDFs, htmlMimeMail5/SMTP für Mail, MySQL. Zwei Einstiegspunkte: `index.php` (User) und `admin.php7` (Verwaltung). Separate Mobile-Templates (`mobile_*.htm`) — rudimentär, kein Responsive Design.

## Datenmodell (14 Tabellen)

| Tabelle | Zweck | Auffälligkeiten |
|---|---|---|
| `see` | Seen-Konfiguration: aktiv, Saison, Buchungsfenster (`wochenImVorraus`), min. Anmelder, Uhrzeiten Woche/Wochenende, Tag- vs. Stundenkontingent (`buchbarProTag`), max. pro Buchung, Preis/Person, kostenpflichtig ja/nein, **Bankverbindung als Text** | Konfig + Zahldaten vermischt |
| `maxfrei` | Max. Kontingent je See × Wochentag × Stunde | **24 Stunden-Spalten** (01:00–24:00) statt Zeilen |
| `nochfrei` | Live-Restkontingent je See × Datum × Stunde | gleiche Spalten-Struktur; wird beim Jahres-Restart neu erzeugt |
| `befugnisse` | Berechtigungscode je See × Wochentag × Stunde | 24 Spalten, char(2)-Codes |
| `nachttermine` | Freigegebene Nachttauch-Termine je See/Datum (21:00–24:00) | |
| `blockaden` | Vereins-/LVST-Blockaden: See, Datum, Stunde, ganzer Tag, Wiederholung, Anzahl, Verein, E-Mail, Info | werden NUR vom Admin eingetragen (Vereine beantragen formlos per Mail bis 1.4.) |
| `buchungen` | Buchung: Name, Vorname, E-Mail, Telefon, Vereins-Nr., Brevet, Datum, Stunde, Anzahl, Anzahl Zahler, `istAbgeholt`, `istBezahlt`, Buchungsdatum, `pwd` (10-Zeichen-Token), block_id | personenbezogen; Token im Klartext |
| `archiv` | Kopie abgeschlossener Buchungen | Auto-Löschung nach 28 Tagen (`DELETE ... datum <= CURDATE - 28 DAY`) |
| `kontakte` | Admins + Seeverantwortliche + Kontrolleure: Login, MD5-Passwort, `isAdmin`-Flag | **nur 2 Rollenstufen** (Admin / Nicht-Admin) |
| `brevets` | Brevet-Liste fürs Formular | |
| `vereine` / `verbaende` | VDST-Vereins-/Verbandsnummern | manuell gepflegt |
| `flags` | Saison Start/Ende, Tageswechsel, **Wochenmail-Zeitpunkt** (dayOfChange/timeOfChange), Info-Texte, Fehlerflag | globale Steuerung |
| `primarykeys` | Meta-Tabelle Tabellen→PK | Eigenbau-ORM-Hilfe |

## User-Flow (Buchung)

1. **Intro** → See wählen (Dropdown: Jägerweiher, Marxweiher, Schlicht)
2. **Tagauswahl** (Kalender, Restkontingent grün/rot) → bei Stundenseen **Stundenauswahl**
3. **Eingabe:** Name, Vorname, E-Mail, Telefon, Vereins-Nr. (oder „kein LVST-Verein" → zahlungspflichtig), Brevet des Gruppenleiters, Anzahl Taucher (min. 1), davon Zahler
4. **Buchung** reserviert Kontingent, Mail mit Bestätigungslink (`buch_id` + `pwd`-Token)
5. **Doppel-Opt-in:** Link bestätigt Buchung; ohne Bestätigung bis Frist → automatische Löschung
6. **PDF-Tauchbestätigung** über denselben Link abrufbar; muss ausgedruckt **im Auto ausgelegt** werden
7. **Storno** über denselben Link → Kontingent wird wieder frei

## Zahl-Workflow (Nicht-LVST-Taucher, 4,50 €/Tag)

Vollständig manuell: Mail nennt Bankverbindung + Buchungs-ID → Überweisung bis Freitag → **Admin gleicht Konto manuell ab**, setzt `istBezahlt` → Freitagabend Freigabe, sonst Löschung. Keine Erstattung bei Storno. → Hauptschmerzpunkt, den PayPal ablösen soll.

## Wochen-Workflow

Zum konfigurierten Zeitpunkt (flags) erhält jeder Seeverantwortliche per Mail ein **PDF mit allen Anmeldungen seines Sees** (email_week) — Grundlage für Kontrollen am See. Kontrolleure haben zusätzlich eingeschränkten Admin-Zugang (Archiv-Ansicht, `seekontroll_header`-Template), alles Schreibende ist per `onlyAdmin()` gesperrt.

## Admin-Funktionen

Seen-CRUD · Kontingente (maxfrei) · Restplätze manuell korrigieren (nochfrei) · Befugnisse · Blockaden-CRUD (mit Kopier-/Wiederholfunktion) · Nachttermine · Brevets · Vereine/Verbände · Kontakte/Benutzer · Archiv mit PDF-Export je See/Tag · Buchungsstatistik · Passwort ändern · **Restart**.

## Jahreswechsel ("Restart") — der Datenverlust-Mechanismus

`admin.php?action=restart`:
- **totalrestart:** löscht ALLES inkl. Nachttermine + Blockaden, legt `nochfrei` fürs aktuelle Jahr neu an
- **partrestart:** löscht Buchungsdaten, erhält Nachttermine/Blockaden

→ Das alte Jahr wird **überschrieben, nicht archiviert**. Zusammen mit der 28-Tage-Löschung im Archiv gibt es keine Historie/Statistik über Jahre. (DSGVO-Löschung personenbezogener Daten ist davon zu trennen — Statistik ginge anonymisiert.)

## Technische Altlasten

- Stunden als DB-Spalten (3× dupliziert in maxfrei/nochfrei/befugnisse) — Abfragen und Änderungen umständlich
- Keine Foreign Keys, Eigenbau-ORM über `primarykeys`-Tabelle
- MD5-Passwörter, Buchungs-Token (10 Zeichen) im Klartext
- GET-basierte Admin-Aktionen ohne CSRF-Schutz; SQL teils per String-Konkatenation (Injection-Risiko)
- `mysql_close()`-Relikt in admin.php7, doppelte Template-Sätze (php5/php7)
- Mobile-Variante als separates Template-Set, nicht responsive
- Mail-Versand über eingebettete SMTP-Klasse von 2005

## Was das Altsystem gut macht (übernehmen)

- Klares Kontingentmodell: max je See/Wochentag/Stunde + Live-Restzähler
- Doppel-Opt-in + Self-Service-Storno über einen Link (kein Account nötig!)
- Tag- vs. Stundenbuchung pro See konfigurierbar; Nachttermine als Ausnahmen
- Wochen-PDF an Seeverantwortliche
- Blockaden mit Wiederholungslogik
- Automatische Löschfristen (28 Tage / Saisonende)
