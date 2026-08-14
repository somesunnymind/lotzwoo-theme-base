/**
 * Die Editor-Seite von `lotzwoo/context-slot`.
 *
 * Wortgleich gebaut wie `blocks/header-slot/index.js` und aus demselben Grund:
 * ohne eine `edit`-Komponente zeigt der Website-Editor an dieser Stelle
 * „Dieser Block wird von deiner Website nicht unterstützt" — eine Fehlerkachel
 * unter dem Kopfbereich, für einen Block, der vollkommen in Ordnung ist.
 *
 * Kein Bauschritt, kein JSX, keine `index.asset.php`: dasselbe Argument wie
 * beim ersten Slot, und ein dritter Block ist kein Grund, es umzustoßen.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element) {
		return;
	}

	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType('lotzwoo/context-slot', {
		apiVersion: 3,
		title: 'Kontextleiste — Plugin-Fläche',
		category: 'theme',
		icon: 'location',
		description:
			'Leere Fläche unter dem Kopfbereich, die das Plugin lotzapp-for-woocommerce füllt: ' +
			'Lieferort, Liefertermin und der Wechsler dazu. Ohne Plugin rendert sie nichts.',
		supports: {
			html: false,
			reusable: false,
			multiple: false,
			interactivity: false,
		},

		edit: function () {
			return el(
				'div',
				useBlockProps({ className: 'lotzwoo-context-slot-placeholder' }),
				'Plugin-Fläche: Lieferort und Liefertermin'
			);
		},

		save: function () {
			return null;
		},
	});
})(window.wp);
