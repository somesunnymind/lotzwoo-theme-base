/**
 * Die Editor-Seite von `lotzwoo/header-slot`.
 *
 * Ohne diese Datei kennt der Website-Editor den Block nicht und zeigt an
 * seiner Stelle „Dieser Block wird von deiner Website nicht unterstützt" —
 * eine Fehlerkachel mitten im Kopfbereich, für einen Block, der vollkommen in
 * Ordnung ist. Die Registrierung in PHP allein genügt dem Editor nicht.
 *
 * Bewusst ohne Bauschritt geschrieben: kein JSX, kein Bundler, keine
 * `index.asset.php`. Das Theme-Repo hat keine Werkzeugkette für Frontend-Code,
 * und diese fünfzehn Zeilen wären ein schlechter Grund, eine einzuführen.
 * `wp.element.createElement` ist das, was ein Bundler aus JSX ohnehin macht.
 *
 * Die Angaben doppeln bewusst `block.json`: der Editor bekommt sie aus dieser
 * Registrierung, der Server aus der Datei. Wer den Titel ändert, ändert beide.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element) {
		return;
	}

	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType('lotzwoo/header-slot', {
		apiVersion: 3,
		title: 'Kopfbereich — Plugin-Fläche',
		category: 'theme',
		icon: 'screenoptions',
		description:
			'Leere Fläche im Kopfbereich, die das Plugin lotzapp-for-woocommerce füllt: ' +
			'Schnellsuche, Favoriten-Zähler, Kundenchip. Ohne Plugin rendert sie nichts.',
		supports: {
			html: false,
			reusable: false,
			multiple: false,
			interactivity: false,
		},

		// Im Editor eine benannte Kachel, damit die Stelle auffindbar ist und
		// niemand sie beim Umbau des Kopfbereichs versehentlich entfernt. Im
		// Frontend rendert PHP eine leere Zeichenkette.
		edit: function () {
			return el(
				'div',
				useBlockProps({ className: 'lotzwoo-header-slot-placeholder' }),
				'Plugin-Fläche'
			);
		},

		// Dynamischer Block: der Inhalt entsteht beim Rendern, nicht beim
		// Speichern. `null` hält das Blockmarkup auf einem einzigen Kommentar.
		save: function () {
			return null;
		},
	});
})(window.wp);
