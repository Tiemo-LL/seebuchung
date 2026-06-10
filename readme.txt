=== Seebuchung ===
Contributors: lvstrlp
Tags: buchung, tauchen, kontingent, verein, paypal
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Buchung von Tageskontingenten an verwalteten Tauchseen — für Landesverbände und Vereine.

== Description ==

Seebuchung ist ein generisches Buchungssystem für Tauchseen mit Tageskontingenten, entwickelt vom Landesverband Sporttauchen Rheinland-Pfalz e.V. (LVST) und nutzbar für beliebige Landesverbände.

Funktionsumfang (im Aufbau):

* Buchung ohne Benutzerkonto mit Doppel-Opt-in per E-Mail und Self-Service-Storno
* Konfigurierbare Seen: Saison, Buchungsfenster, Tages- oder Stundenkontingente, Preise
* PayPal-Zahlung für Nicht-Verbandsmitglieder (Orders v2, Buchung gültig erst nach Capture)
* QR-Code-Bestätigung mit mobiler Kontrolleurs-Ansicht
* Blockaden-Self-Service für Vereine mit Genehmigungs-Workflow
* Saisonwechsel mit Archivierung und DSGVO-konformer Anonymisierung
* Wochenberichte per E-Mail an Seeverantwortliche

Alle verbandsspezifischen Inhalte (Name, Logo, Seen, Gebühren, Mailtexte) werden über Einstellungen gepflegt — nichts ist hartcodiert.

== Installation ==

1. Plugin-Verzeichnis nach `wp-content/plugins/seebuchung` hochladen oder als ZIP installieren.
2. Plugin im WordPress-Admin aktivieren.
3. Unter "Seebuchung" Seen, Kontingente und Verbandsdaten konfigurieren.

== Changelog ==

= 0.1.0 =
* Projekt-Setup (Phase 0): Plugin-Skelett, Composer, phpcs/WPCS, PHPUnit, wp-env, CI.
