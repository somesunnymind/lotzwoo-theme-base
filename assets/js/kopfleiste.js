/**
 * Die Kopfzeile verteilt ihre Punkte, statt welche fallen zu lassen (AP-53).
 *
 * Bis zum 2026-08-17 standen im Stylesheet zwei Stufen: bei 1040 px
 * verschwanden „Über uns" und „Aktuelles" per `display:none`, bei 880 px wich
 * die ganze Leiste einer Menütaste. Die erste war das, was dieser Auftrag
 * abstellt — **zwei Punkte flogen raus**, und niemand kam mehr an sie heran.
 *
 * Was hier passiert, ist die Umkehrung: was nicht passt, wandert in ein
 * Untermenü hinter dem Punkt „mehr". Die Vereinigung aus Leiste und „mehr" ist
 * auf **jeder** Breite vollständig — das ist die Zusicherung, um die es geht.
 *
 * ## Breitenmessend, nicht schwellenbasiert
 *
 * Die Menüpunkte stehen im Kindtheme und sind vom Kunden änderbar. Eine
 * Schwelle, die für sechs Punkte stimmt, stimmt für sieben nicht mehr.
 * Gemessen wird deshalb, was tatsächlich passt.
 *
 * ## Die drei Fallen, in der Reihenfolge, in der sie zuschlagen
 *
 * **1 · Eine eingeklappte Breite ist 0.** Wer die Punkte misst, *nachdem* er
 * sie ins Untermenü gehängt hat, misst nichts und klappt beim Vergrößern nie
 * wieder aus. Die Antwort hier ist nicht ein Zwischenspeicher, sondern die
 * Reihenfolge: **jeder** Durchgang stellt zuerst den vollen Zustand her, misst
 * ihn, und lagert dann neu aus. Ein Wert, den niemand aufhebt, kann nicht
 * veralten.
 *
 * **2 · Die Schriften kommen später.** Eine Messung vor `document.fonts.ready`
 * misst Ersatzschrift und liegt um genug daneben, dass ein Punkt zu viel oder
 * zu wenig wandert. Der erste Durchgang wartet darauf.
 *
 * **3 · Der Flex-Kasten gibt nach, statt überzulaufen.** Und das ist die
 * Falle, die dieser Datei eigen ist: die Zeile steht auf `nowrap`, ihre
 * Inhalte haben `min-width: 0` und Auslassungspunkte — genau damit auf keiner
 * Breite waagrecht übergelaufen wird. Ein Kasten, der nachgibt, meldet aber
 * **nie** einen Überlauf: `scrollWidth` bleibt gleich `clientWidth`, und die
 * Messung sagt „passt" bei jeder Breite.
 *
 * Deshalb misst dieser Code in einem eigenen Zustand: `.lotzwoo-misst` nimmt
 * den Kindern der Zeile für die Dauer der Messung Wachsen und Schrumpfen ab.
 * Dann — und nur dann — ist `scrollWidth` die Summe der natürlichen Breiten
 * und die Frage „passt das?" überhaupt beantwortbar. Der ganze Durchgang läuft
 * synchron in **einem** Animationsbild; gemalt wird zwischendurch nicht, also
 * flackert auch nichts.
 *
 * ## Die Rangordnung
 *
 * `.lotzwoo-navi--sekundaer` ist seit diesem Auftrag keine Ausblendung mehr,
 * sondern eine **Rangordnung**: was sie trägt, wandert zuerst. Die
 * redaktionelle Entscheidung dahinter — „Über uns" und „Aktuelles" sind
 * nachrangig — bleibt damit erhalten und bekommt eine bessere Wirkung als
 * vorher. Danach wird von hinten abgeräumt.
 *
 * Im Untermenü stehen die Punkte trotzdem in **Dokumentreihenfolge**: gewandert
 * wird nach Rang, gelesen wird nach Reihenfolge.
 *
 * ## Die Nummer
 *
 * Die Bestellannahme (`data-lotzwoo-headerphone`, gesetzt vom Plugin) ist der
 * **letzte**, der geht — sie ist der Grund, warum es die mittlere Bahn gibt.
 * Findet dieses Skript den Haken nicht, hängt es eben nur Menüpunkte um: die
 * Sonde darf das Plugin kennen, das Plugin nie die Sonde.
 */
(function () {
	'use strict';

	var zeile = document.querySelector('.lotzwoo-header-row');
	var eintrag = zeile && zeile.querySelector('[data-lotzwoo-mehr]');

	if (!zeile || !eintrag) {
		return;
	}

	var knopf = eintrag.querySelector('.lotzwoo-mehr__knopf');
	var menue = eintrag.querySelector('.lotzwoo-mehr__menue');
	var liste = eintrag.parentNode;

	if (!knopf || !menue || !liste) {
		return;
	}

	// Die Punkte, so wie sie im Dokument stehen — einmal gelesen, weil sich
	// ihre **Reihenfolge** nicht ändert. Ihre Breiten werden nie aufgehoben.
	var punkte = Array.prototype.filter.call(liste.children, function (kind) {
		return kind !== eintrag;
	});

	var telefon = document.querySelector('[data-lotzwoo-headerphone]');
	var telefonHeimat = telefon ? telefon.parentNode : null;

	// Sekundäre zuerst, danach von hinten nach vorn.
	var rang = punkte
		.map(function (li, i) {
			return { li: li, i: i, sek: li.classList.contains('lotzwoo-navi--sekundaer') };
		})
		.sort(function (a, b) {
			if (a.sek !== b.sek) {
				return a.sek ? -1 : 1;
			}

			return b.i - a.i;
		});

	// **Nicht `scrollWidth`.** Die Ist-Messung vom 2026-08-16 hat notiert, dass
	// `scrollWidth > clientWidth` am Kopf auf *jeder* Breite um 12 px erfüllt
	// war, auch dort, wo nichts überlief — eine Sonde, die darauf baute,
	// meldete „161 von 161 Breiten mit Überlauf". Gefragt wird deshalb direkt:
	// steht die rechte Kante des letzten Kindes rechts von der rechten Kante
	// der Zeile? Bei `nowrap` und abgeschaltetem Schrumpfen ist das genau die
	// Frage „passt das?", und sie hat keine zweite Auslegung.
	function laeuftUeber() {
		var letztes = zeile.lastElementChild;

		if (!letztes) {
			return false;
		}

		return letztes.getBoundingClientRect().right - zeile.getBoundingClientRect().right > 1;
	}

	function heim() {
		// Dokumentreihenfolge, und vor dem Eintrag „mehr" — er steht immer am
		// Ende der Leiste.
		punkte.forEach(function (li) {
			liste.insertBefore(li, eintrag);
		});

		if (telefon && telefonHeimat && telefon.parentNode !== telefonHeimat) {
			telefonHeimat.appendChild(telefon);
		}

		menue.textContent = '';
	}

	function auslagern(li) {
		menue.appendChild(li);

		// Nach jedem Zug neu nach Dokumentreihenfolge sortieren: gewandert wird
		// nach Rang, gelesen wird nach Reihenfolge.
		punkte
			.filter(function (kandidat) {
				return kandidat.parentNode === menue;
			})
			.forEach(function (kandidat) {
				menue.appendChild(kandidat);
			});
	}

	function verteilen() {
		var offen = knopf.getAttribute('aria-expanded') === 'true';

		heim();
		eintrag.hidden = true;
		zeile.classList.add('lotzwoo-misst');

		if (!laeuftUeber()) {
			zeile.classList.remove('lotzwoo-misst');
			schliessen(false);

			return;
		}

		eintrag.hidden = false;

		for (var k = 0; k < rang.length; k++) {
			if (!laeuftUeber()) {
				break;
			}

			auslagern(rang[k].li);
		}

		// Die Nummer geht zuletzt — sie ist der Grund für die mittlere Bahn.
		if (telefon && laeuftUeber()) {
			var traeger = document.createElement('li');
			traeger.className = 'lotzwoo-mehr__telefon';
			traeger.appendChild(telefon);
			menue.appendChild(traeger);
		}

		zeile.classList.remove('lotzwoo-misst');
		menue.hidden = !offen;
		knopf.setAttribute('aria-expanded', offen ? 'true' : 'false');
	}

	function oeffnen() {
		menue.hidden = false;
		knopf.setAttribute('aria-expanded', 'true');
	}

	function schliessen(fokusZurueck) {
		menue.hidden = true;
		knopf.setAttribute('aria-expanded', 'false');

		if (fokusZurueck) {
			knopf.focus();
		}
	}

	knopf.addEventListener('click', function () {
		if (knopf.getAttribute('aria-expanded') === 'true') {
			schliessen(false);

			return;
		}

		oeffnen();
	});

	// Escape schließt und gibt den Fokus zurück — auch aus dem Untermenü
	// heraus, denn dort steht er, wenn jemand mit der Tastatur hineingelaufen
	// ist. Ohne die zweite Bedingung endete Escape dort im Nichts.
	eintrag.addEventListener('keydown', function (ereignis) {
		if (ereignis.key !== 'Escape') {
			return;
		}

		if (knopf.getAttribute('aria-expanded') !== 'true') {
			return;
		}

		ereignis.stopPropagation();
		schliessen(true);
	});

	// Wer weiterläuft, statt zu schließen, soll das Menü hinter sich lassen.
	// `focusout` und nicht `blur`: `blur` steigt nicht auf.
	eintrag.addEventListener('focusout', function (ereignis) {
		if (eintrag.contains(ereignis.relatedTarget)) {
			return;
		}

		schliessen(false);
	});

	document.addEventListener('click', function (ereignis) {
		if (eintrag.contains(ereignis.target)) {
			return;
		}

		schliessen(false);
	});

	var angemeldet = false;

	function anstossen() {
		if (angemeldet) {
			return;
		}

		angemeldet = true;
		requestAnimationFrame(function () {
			angemeldet = false;
			verteilen();
		});
	}

	window.addEventListener('resize', anstossen);

	// Falle 2: erst wenn die Schriften stehen. Wo `document.fonts` fehlt, wird
	// sofort gemessen — eine Messung mit Ersatzschrift ist besser als keine.
	if (document.fonts && document.fonts.ready) {
		document.fonts.ready.then(anstossen);
	}

	anstossen();
})();
