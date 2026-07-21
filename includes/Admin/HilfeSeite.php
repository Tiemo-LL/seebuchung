<?php
/**
 * Admin: Hilfe und Anleitung.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Rollen;
use Seebuchung\Settings;

/**
 * Ausführliche Bedienungsanleitung direkt im Backend — für Admins,
 * Seeverantwortliche und Kontrolleur:innen.
 */
final class HilfeSeite {

	/**
	 * Seite rendern.
	 */
	public function render(): void {
		$kuerzel = Settings::verband_kuerzel();
		$admin   = current_user_can( Rollen::CAP_VERWALTEN );
		?>
		<div class="wrap" style="max-width:56em">
			<h1><?php esc_html_e( 'Seebuchung — Anleitung', 'seebuchung' ); ?></h1>

			<h2 style="margin-top:1.5em"><?php esc_html_e( 'Inhalt', 'seebuchung' ); ?></h2>
			<ul style="list-style:disc;margin-left:1.5em">
				<li><a href="#sb-ablauf"><?php esc_html_e( 'So läuft eine Buchung ab', 'seebuchung' ); ?></a></li>
				<?php if ( $admin ) : ?>
					<li><a href="#sb-einrichtung"><?php esc_html_e( 'Erste Einrichtung (Checkliste)', 'seebuchung' ); ?></a></li>
					<li><a href="#sb-seen"><?php esc_html_e( 'Seen und Kontingente pflegen', 'seebuchung' ); ?></a></li>
				<?php endif; ?>
				<li><a href="#sb-buchungen"><?php esc_html_e( 'Buchungen verwalten', 'seebuchung' ); ?></a></li>
				<?php if ( $admin ) : ?>
					<li><a href="#sb-paypal"><?php esc_html_e( 'PayPal einrichten (Sandbox und Live)', 'seebuchung' ); ?></a></li>
					<li><a href="#sb-blockaden"><?php esc_html_e( 'Blockaden und Vereins-Links', 'seebuchung' ); ?></a></li>
				<?php endif; ?>
				<li><a href="#sb-kontrolle"><?php esc_html_e( 'Kontrolle am See', 'seebuchung' ); ?></a></li>
				<li><a href="#sb-wochenbericht"><?php esc_html_e( 'Wochenbericht und Seeverantwortliche', 'seebuchung' ); ?></a></li>
				<?php if ( $admin ) : ?>
					<li><a href="#sb-saison"><?php esc_html_e( 'Saisonwechsel und Datenschutz', 'seebuchung' ); ?></a></li>
					<li><a href="#sb-rollen"><?php esc_html_e( 'Rollen und Rechte', 'seebuchung' ); ?></a></li>
					<li><a href="#sb-anpassen"><?php esc_html_e( 'Mails und Texte anpassen', 'seebuchung' ); ?></a></li>
				<?php endif; ?>
				<li><a href="#sb-faq"><?php esc_html_e( 'Häufige Fragen und Probleme', 'seebuchung' ); ?></a></li>
			</ul>

			<hr>

			<h2 id="sb-ablauf"><?php esc_html_e( 'So läuft eine Buchung ab', 'seebuchung' ); ?></h2>
			<ol style="list-style:decimal;margin-left:1.5em">
				<li><?php esc_html_e( 'Taucher:in wählt auf der Buchungsseite See, Datum und (bei Stundenseen) die Uhrzeit und füllt das Formular aus. Ein Benutzerkonto ist nicht nötig.', 'seebuchung' ); ?></li>
				<li><?php esc_html_e( 'Die Buchung ist zunächst „angefragt" und reserviert das Kontingent sofort. Eine E-Mail mit Bestätigungslink geht raus (Doppel-Opt-in).', 'seebuchung' ); ?></li>
				<li><?php esc_html_e( 'Klick auf den Link → „bestätigt". Kostenlose Buchungen (Verbandsvereine) werden sofort „gültig". Zahlungspflichtige zeigen einen PayPal-Button und werden erst mit Zahlungseingang „gültig".', 'seebuchung' ); ?></li>
				<li><?php esc_html_e( 'Gültige Buchungen zeigen auf ihrer Statusseite einen QR-Code — die Tauchbestätigung fürs Handy.', 'seebuchung' ); ?></li>
				<li><?php esc_html_e( 'Storno ist jederzeit über denselben Link möglich; das Kontingent wird wieder frei, gezahlte Gebühren werden nicht erstattet. Unbestätigte Anfragen verfallen automatisch nach der eingestellten Frist.', 'seebuchung' ); ?></li>
			</ol>
			<p><em><?php esc_html_e( 'Status-Kette: angefragt → bestätigt → gültig → kontrolliert; daneben storniert und verfallen.', 'seebuchung' ); ?></em></p>

			<?php if ( $admin ) : ?>
				<h2 id="sb-einrichtung"><?php esc_html_e( 'Erste Einrichtung (Checkliste)', 'seebuchung' ); ?></h2>
				<ol style="list-style:decimal;margin-left:1.5em">
					<li><?php esc_html_e( 'Seite mit dem Shortcode [seebuchung] anlegen (das ist die Buchungsseite).', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'Einstellungen: Verbandsname, Kürzel, Kontakt-E-Mail, Absenderadresse und die Buchungsseite auswählen.', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'Seen anlegen bzw. per Import übernehmen (Menüpunkt „Import", nur in leere Tabellen) und auf „aktiv" schalten.', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'PayPal einrichten (siehe unten) — ohne PayPal-Daten erscheint kein Zahlen-Button und zahlungspflichtige Buchungen bleiben „bestätigt".', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'Benutzer anlegen: Seeverantwortliche und Kontrolleur:innen mit den passenden Rollen, Seen im Benutzerprofil zuordnen.', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'Eine Testbuchung durchspielen: buchen → Mail-Link → bestätigen → ggf. zahlen → stornieren.', 'seebuchung' ); ?></li>
				</ol>

				<h2 id="sb-seen"><?php esc_html_e( 'Seen und Kontingente pflegen', 'seebuchung' ); ?></h2>
				<p><?php esc_html_e( 'Unter „Seen" bearbeitest du je See:', 'seebuchung' ); ?></p>
				<ul style="list-style:disc;margin-left:1.5em">
					<li><strong><?php esc_html_e( 'Modus:', 'seebuchung' ); ?></strong> <?php esc_html_e( 'Tageskontingent (ein Wert pro Tag, z. B. Jägerweiher) oder Stundenkontingent (Werte je Stunde, z. B. Marxweiher).', 'seebuchung' ); ?></li>
					<li><strong><?php esc_html_e( 'Saison:', 'seebuchung' ); ?></strong> <?php esc_html_e( 'konkrete Datumsgrenzen — beim jährlichen Saisonwechsel aktualisieren (Assistent).', 'seebuchung' ); ?></li>
					<li><strong><?php esc_html_e( 'Buchungsfenster:', 'seebuchung' ); ?></strong> <?php esc_html_e( 'wie viele Wochen im Voraus gebucht werden darf.', 'seebuchung' ); ?></li>
					<li><strong><?php esc_html_e( 'Uhrzeiten:', 'seebuchung' ); ?></strong> <?php esc_html_e( 'Öffnungszeiten getrennt für Mo–Fr und Sa/So; „bis" ist exklusiv (9–20 heißt: letzter Slot 19 Uhr).', 'seebuchung' ); ?></li>
					<li><strong><?php esc_html_e( 'Kontingent-Matrix:', 'seebuchung' ); ?></strong> <?php esc_html_e( 'maximale Taucher je Wochentag und Stunde (bzw. Spalte „Tag" beim Tagessee). Leer oder 0 = nicht buchbar.', 'seebuchung' ); ?></li>
					<li><strong><?php esc_html_e( 'Preis und „Alle zahlungspflichtig":', 'seebuchung' ); ?></strong> <?php esc_html_e( 'Gäste ohne Verbands-Verein zahlen immer den Personenpreis. Ist der Haken gesetzt, zahlen ALLE Taucher — auch Vereinsmitglieder.', 'seebuchung' ); ?></li>
				</ul>
			<?php endif; ?>

			<h2 id="sb-buchungen"><?php esc_html_e( 'Buchungen verwalten', 'seebuchung' ); ?></h2>
			<p><?php esc_html_e( 'Der Menüpunkt „Buchungen" zeigt alle Buchungen, filterbar nach See, Datum und Status. Admins können Buchungen manuell stornieren (die buchende Person bekommt automatisch eine Mail) und alle Zahlungen als CSV für die Kasse exportieren.', 'seebuchung' ); ?></p>
			<p><?php esc_html_e( 'Wichtig: Es gibt kein „Löschen" — stornierte und verfallene Buchungen bleiben als Historie erhalten und geben ihr Kontingent automatisch frei. Personenbezogene Daten werden nach Fristablauf automatisch anonymisiert.', 'seebuchung' ); ?></p>

			<?php if ( $admin ) : ?>
				<h2 id="sb-paypal"><?php esc_html_e( 'PayPal einrichten (Sandbox und Live)', 'seebuchung' ); ?></h2>
				<ol style="list-style:decimal;margin-left:1.5em">
					<li><?php esc_html_e( 'Auf developer.paypal.com mit dem PayPal-Business-Konto des Verbands anmelden und unter „Apps & Credentials" eine App anlegen.', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'Client-ID und Secret in den Einstellungen eintragen. Zum Testen den Haken „Sandbox" gesetzt lassen und die Sandbox-Zugangsdaten verwenden.', 'seebuchung' ); ?></li>
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: Webhook-URL */
								__( 'Webhook anlegen (Empfehlung): URL %s, Event PAYMENT.CAPTURE.COMPLETED — die Webhook-ID in den Einstellungen eintragen. Der Webhook fängt Zahlungen auf, bei denen die Rückleitung abbricht.', 'seebuchung' ),
								rest_url( 'seebuchung/v1/paypal-webhook' )
							)
						);
						?>
					</li>
					<li><?php esc_html_e( 'Für den Echtbetrieb: Live-Zugangsdaten der App eintragen und den Sandbox-Haken entfernen.', 'seebuchung' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'Preislogik: Gebühr = Anzahl zahlungspflichtiger Taucher × Personenpreis des Sees. Bei Storno erfolgt keine Erstattung — das steht auch in Formular und Mails.', 'seebuchung' ); ?></p>

				<h2 id="sb-blockaden"><?php esc_html_e( 'Blockaden und Vereins-Links', 'seebuchung' ); ?></h2>
				<ol style="list-style:decimal;margin-left:1.5em">
					<li><?php esc_html_e( 'Unter „Vereine" für einen Verein „Token generieren" — der angezeigte Link ist nur einmal sichtbar; kopieren und dem Verein geben. „Widerrufen" macht den Link ungültig.', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'Der Verein beantragt über seinen Link Termine fürs Folgejahr — nur im Antragsfenster (Standard 1.10.–1.2., einstellbar). Regeln werden automatisch geprüft: max. 2 Blockaden pro Jahr und See, Stundenblockaden max. 3 Stunden, am Tagessee zählt der ganze Tag.', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'Unter „Blockaden" genehmigst oder lehnst du Anträge ab — der Verein bekommt automatisch eine Mail. Genehmigte Blockaden sperren bzw. reduzieren das Kontingent im Buchungskalender.', 'seebuchung' ); ?></li>
				</ol>
			<?php endif; ?>

			<h2 id="sb-kontrolle"><?php esc_html_e( 'Kontrolle am See', 'seebuchung' ); ?></h2>
			<ol style="list-style:decimal;margin-left:1.5em">
				<li><?php esc_html_e( 'Am Handy im WordPress-Admin anmelden und „Seebuchung → Kontrolle" öffnen, dann den See wählen.', 'seebuchung' ); ?></li>
				<li><?php esc_html_e( 'Die Tagesliste zeigt alle aktiven Buchungen von heute mit Uhrzeit, Gruppenleitung, Verein, Anzahl und Status.', 'seebuchung' ); ?></li>
				<li><?php esc_html_e( 'QR-Code prüfen: Den Code der Tauchbestätigung mit einer beliebigen Scanner-App scannen, den Inhalt (beginnt mit SB1:) ins Prüffeld einfügen → GÜLTIG oder UNGÜLTIG mit Buchungsdetails.', 'seebuchung' ); ?></li>
				<li><?php esc_html_e( 'Nach der Kontrolle den Haken „kontrolliert" setzen — die Buchung bleibt gültig und ist als geprüft markiert.', 'seebuchung' ); ?></li>
			</ol>

			<h2 id="sb-wochenbericht"><?php esc_html_e( 'Wochenbericht und Seeverantwortliche', 'seebuchung' ); ?></h2>
			<p><?php esc_html_e( 'Seeverantwortliche erhalten automatisch am eingestellten Wochentag (Standard Freitag) je zugeordnetem See eine E-Mail mit allen Buchungen der kommenden 7 Tage. Die Zuordnung pflegen Admins im Benutzerprofil der Person unter „Seebuchung: Seeverantwortung".', 'seebuchung' ); ?></p>

			<?php if ( $admin ) : ?>
				<h2 id="sb-saison"><?php esc_html_e( 'Saisonwechsel und Datenschutz', 'seebuchung' ); ?></h2>
				<p><?php esc_html_e( 'Einmal jährlich (z. B. im Winter) unter „Saisonwechsel" die neuen Saisondaten je See eintragen (Vorschlag: Vorjahr + 1) und ausführen. Dabei werden alle Buchungen vergangener Tauchtage anonymisiert: Name, E-Mail und Telefon werden entfernt, die Statistik (Auslastung, Einnahmen) bleibt dauerhaft erhalten.', 'seebuchung' ); ?></p>
				<p><?php esc_html_e( 'Unabhängig davon anonymisiert ein täglicher Automatismus alle Buchungen, deren Tauchtag länger als die eingestellte Frist (Standard 28 Tage) zurückliegt. Es muss also niemand manuell löschen.', 'seebuchung' ); ?></p>

				<h2 id="sb-rollen"><?php esc_html_e( 'Rollen und Rechte', 'seebuchung' ); ?></h2>
				<table class="widefat striped" style="max-width:44em">
					<thead><tr><th><?php esc_html_e( 'Rolle', 'seebuchung' ); ?></th><th><?php esc_html_e( 'Darf', 'seebuchung' ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Seebuchung-Admin', 'seebuchung' ); ?></td><td><?php esc_html_e( 'alles: Seen, Buchungen, Blockaden, Vereine, Einstellungen, Import, Saisonwechsel', 'seebuchung' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Seeverantwortliche:r', 'seebuchung' ); ?></td><td><?php esc_html_e( 'Buchungen und Statistik einsehen, erhält Wochenberichte', 'seebuchung' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Seebuchung-Kontrolleur:in', 'seebuchung' ); ?></td><td><?php esc_html_e( 'nur die Kontroll-Ansicht (Tagesliste, QR-Prüfung, Haken)', 'seebuchung' ); ?></td></tr>
					</tbody>
				</table>
				<p><?php esc_html_e( 'WordPress-Administratoren haben automatisch alle Rechte.', 'seebuchung' ); ?></p>

				<h2 id="sb-anpassen"><?php esc_html_e( 'Mails und Texte anpassen', 'seebuchung' ); ?></h2>
				<ul style="list-style:disc;margin-left:1.5em">
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: Absendername */
								__( 'Absender: Mails kommen als „%s"; die Absenderadresse ist in den Einstellungen konfigurierbar und sollte zur Website-Domain gehören.', 'seebuchung' ),
								$kuerzel . ' Seebuchung'
							)
						);
						?>
					</li>
					<li><?php esc_html_e( 'Mailtexte und Betreffzeilen lassen sich ohne Code-Änderung per Filter anpassen (seebuchung_mail_betreff, seebuchung_mail_text, seebuchung_mail_absendername) — etwas für die IT.', 'seebuchung' ); ?></li>
					<li><?php esc_html_e( 'Frontend-Templates können per Filter seebuchung_template durch eigene Dateien ersetzt werden.', 'seebuchung' ); ?></li>
				</ul>
			<?php endif; ?>

			<h2 id="sb-faq"><?php esc_html_e( 'Häufige Fragen und Probleme', 'seebuchung' ); ?></h2>
			<dl>
				<dt><strong><?php esc_html_e( 'Die Buchungsseite zeigt veraltete Restplätze.', 'seebuchung' ); ?></strong></dt>
				<dd><?php esc_html_e( 'Die Buchungsstrecke nimmt sich selbst vom Seiten-Cache aus (ab Version 0.9.1). Zeigt ein Browser trotzdem alte Zahlen: Seite neu laden; bei hartnäckigen Fällen den Cache des Caching-Plugins einmal leeren.', 'seebuchung' ); ?></dd>

				<dt style="margin-top:0.75em"><strong><?php esc_html_e( 'Buchungs-Mails kommen nicht an.', 'seebuchung' ); ?></strong></dt>
				<dd><?php esc_html_e( 'Spam-Ordner prüfen. Dauerhaft hilft eine Absenderadresse der eigenen Domain (Einstellungen) und ein SMTP-Plugin, falls der Hoster wp_mail unzuverlässig zustellt.', 'seebuchung' ); ?></dd>

				<dt style="margin-top:0.75em"><strong><?php esc_html_e( 'Eine Zahlung hängt auf „bestätigt".', 'seebuchung' ); ?></strong></dt>
				<dd><?php esc_html_e( 'Die Person hat die PayPal-Zahlung abgebrochen oder nicht abgeschlossen. Sie kann sie jederzeit über ihren Buchungslink nachholen. Ohne Zahlung verfällt nichts automatisch — bei Bedarf manuell stornieren.', 'seebuchung' ); ?></dd>

				<dt style="margin-top:0.75em"><strong><?php esc_html_e( '„Ungültiger Buchungslink" beim Klick auf einen Mail-Link.', 'seebuchung' ); ?></strong></dt>
				<dd><?php esc_html_e( 'Der Link wurde unvollständig kopiert oder die Buchung wurde bereits anonymisiert (Datenschutz-Frist abgelaufen). Die Buchung selbst ist in der Buchungsübersicht weiterhin sichtbar.', 'seebuchung' ); ?></dd>

				<dt style="margin-top:0.75em"><strong><?php esc_html_e( 'Ein QR-Code wird als UNGÜLTIG angezeigt, obwohl die Person eine Mail vorzeigt.', 'seebuchung' ); ?></strong></dt>
				<dd><?php esc_html_e( 'Der QR ist nur bei Status „gültig" oder „kontrolliert" korrekt — stornierte, verfallene oder unbezahlte Buchungen haben keinen gültigen Code. Im Zweifel die Tagesliste prüfen.', 'seebuchung' ); ?></dd>

				<dt style="margin-top:0.75em"><strong><?php esc_html_e( 'Ein Verein kann keine Blockade beantragen.', 'seebuchung' ); ?></strong></dt>
				<dd><?php esc_html_e( 'Häufigste Gründe: Antragsfenster geschlossen, Token widerrufen, oder das Limit von 2 Blockaden pro Jahr und See ist erreicht. Die Fehlermeldung auf der Antragsseite nennt den Grund.', 'seebuchung' ); ?></dd>
			</dl>
		</div>
		<?php
	}
}
