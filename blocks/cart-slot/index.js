/**
 * Die Editor-Seite von `lotzwoo/cart-slot`.
 *
 * Aus demselben Grund handgeschrieben wie bei `header-slot`: eine reine
 * PHP-Registrierung genügt dem Website-Editor nicht, er zeigt sonst „Dieser
 * Block wird von deiner Website nicht unterstützt" — hier mitten in einem
 * Warenkorb, der vollkommen in Ordnung ist. Kein JSX, kein Bundler, keine
 * `index.asset.php`; das Theme-Repo hat keine Werkzeugkette für Frontend-Code.
 *
 * Der Unterschied zum Kopf-Slot steht nicht hier, sondern in PHP: dort rendert
 * der `render_callback` den Anker `<div id="lotzwoo-cart-slot"></div>` statt der
 * leeren Zeichenkette, weil die Daten clientseitig in der Store-API-Cart liegen
 * und ein `render_block`-Filter beim Templaterendering nichts zu füllen hätte
 * (Ticket 15, Frage 1).
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

	wp.blocks.registerBlockType('lotzwoo/cart-slot', {
		apiVersion: 3,
		title: 'Warenkorb — Plugin-Fläche',
		category: 'theme',
		icon: 'screenoptions',
		description:
			'Anker im Warenkorb, den das Plugin lotzapp-for-woocommerce clientseitig ' +
			'füllt: zurückgehaltene Positionen und der Versand-Hinweis. Ohne Plugin ' +
			'bleibt das Div leer und unsichtbar.',
		supports: {
			html: false,
			reusable: false,
			multiple: false,
			interactivity: false,
		},

		// Im Editor eine benannte Kachel zwischen Positionen und Summen, damit
		// die Stelle auffindbar ist und niemand sie beim Umbau des Warenkorbs
		// versehentlich entfernt. Im Frontend rendert PHP den leeren Anker.
		edit: function () {
			return el(
				'div',
				useBlockProps({ className: 'lotzwoo-cart-slot-placeholder' }),
				'Plugin-Fläche: zurückgehaltene Positionen'
			);
		},

		// Dynamischer Block: der Anker entsteht beim Rendern, nicht beim
		// Speichern. `null` hält das Blockmarkup auf einem einzigen Kommentar.
		save: function () {
			return null;
		},
	});
})(window.wp);
