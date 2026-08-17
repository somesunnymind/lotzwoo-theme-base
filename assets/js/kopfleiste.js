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
 * ## Zwei Darstellungen, eine gemessene Grenze
 *
 * **„mehr" ist keine Leiter bis 320 px hinunter.** Klarstellung des
 * Auftraggebers vom 2026-08-17: unterhalb der Stelle, an der nur noch **zwei**
 * Menüpunkte in der Leiste stünden, verschwinden **alle** im Hamburger. Eine
 * Leiste aus zwei Punkten und einem „mehr", hinter dem vier liegen, ist keine
 * Leiste mehr — sie ist ein Menü, das so tut, als wäre es keines.
 *
 * Also zwei Darstellungen:
 *
 *   - **„mehr"**, solange mindestens drei Punkte ausgeschrieben bleiben. Der
 *     Öffner des Kerns ist weg, auch unter 600 px.
 *   - **Hamburger**, sobald es weniger wären. Alle Punkte gehen zurück in die
 *     Leiste — die ist dann der Inhalt des Overlays —, „mehr" verschwindet,
 *     und Woos Öffner übernimmt.
 *
 * **Die Grenze ist gemessen, nicht geschrieben.** Der Kern schaltet bei 600 px,
 * und diese Zahl beschreibt nichts an diesem Kopf. Umgeschaltet wird deshalb
 * über eine Klasse, die aus der Verteilung folgt — in **beide** Richtungen:
 * unter 600 px ohne Hamburger, über 600 px mit, wenn die Punkte es so ergeben.
 * Das Stylesheet dreht Kerns beide Medienregeln entsprechend um.
 *
 * ## Die Nummer
 *
 * Die Bestellannahme (`data-lotzwoo-headerphone`, gesetzt vom Plugin) ist der
 * **letzte**, der geht — sie ist der Grund, warum es die mittlere Bahn gibt.
 * Sie wandert erst, wenn alles andere schon gewandert ist, und je nach
 * Darstellung ins „mehr" oder in das Overlay. Findet dieses Skript den Haken
 * nicht, hängt es eben nur Menüpunkte um: die Sonde darf das Plugin kennen,
 * das Plugin nie die Sonde.
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
	var telefonTraeger = null;

	/**
	 * Ab wann der Hamburger übernimmt.
	 *
	 * Zwei: „mehr" bleibt einen Menüpunkt länger stehen, als es zuerst gebaut
	 * war — erst wenn nur noch **einer** ausgeschrieben bliebe, übernimmt der
	 * Hamburger. Entscheidung des Auftraggebers vom 2026-08-17, nachgeschärft
	 * am selben Abend. Die Zahl steht hier und nicht als Breite im Stylesheet
	 * — die Menüpunkte stehen im Kindtheme und sind vom Kunden änderbar, eine
	 * Breite wäre morgen falsch.
	 */
	var MINDESTENS_IN_DER_LEISTE = 2;

	var BURGER = 'lotzwoo-kopf--burger';
	var MESSEN = 'lotzwoo-misst';
	var KONTOICON = 'lotzwoo-kopf--kontoicon';

	// Der Konto-Chip (`data-lotzwoo-customer`, gesetzt vom Plugin). Findet
	// dieses Skript ihn nicht, verteilt es eben nur Menuepunkte — dieselbe
	// Richtung wie bei der Bestellannahme: die Sonde darf das Plugin kennen,
	// das Plugin nie die Sonde.
	var konto = document.querySelector('[data-lotzwoo-customer]');

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

		// Der Träger, in dem die Nummer gewandert ist — je nach Darstellung
		// hing er im „mehr" oder in der Leiste. Bleibt er liegen, steht im
		// Overlay ein leerer Eintrag.
		if (telefonTraeger && telefonTraeger.parentNode) {
			telefonTraeger.parentNode.removeChild(telefonTraeger);
		}

		telefonTraeger = null;
		menue.textContent = '';

		// Auch der Chip geht in den vollen Zustand zurueck — sonst gilt Falle 1
		// fuer ihn: ein Chip, der schon Symbol ist, misst die Breite seines
		// Symbols, und die Zeile scheint fuer immer zu passen.
		zeile.classList.remove(KONTOICON);
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

	/** Die Nummer bekommt einen Listeneintrag, wo immer sie gerade hinsoll. */
	function telefonAuslagern(ziel, vor) {
		if (!telefon) {
			return;
		}

		telefonTraeger = document.createElement('li');
		telefonTraeger.className = 'lotzwoo-mehr__telefon';
		telefonTraeger.appendChild(telefon);

		if (vor) {
			ziel.insertBefore(telefonTraeger, vor);

			return;
		}

		ziel.appendChild(telefonTraeger);
	}

	/**
	 * Der Konto-Chip wird zum Symbol — die **letzte** Stufe.
	 *
	 * Er gibt nach, wenn alles andere schon nachgegeben hat: Menuepunkte
	 * gewandert, Bestellannahme gewandert, Hamburger uebernommen. Der Grund
	 * ist derselbe, aus dem die Nummer vor ihm geht — sie hat einen zweiten
	 * Weg (das Overlay), der Chip hat keinen: er ist der einzige Kontoweg im
	 * Kopf, seit AP-48 Woos `customer-account` entfallen ist.
	 *
	 * Gemessen wird, nicht geschwellt: der Name kommt aus dem Kundenkonto, und
	 * „Musterfirma GmbH & Co KG" und „M. Muster" brauchen nicht dieselbe
	 * Breite. Eine Zahl im Stylesheet waere fuer den einen zu frueh und fuer
	 * den anderen zu spaet.
	 */
	function kontoNachgeben() {
		if (!konto || !laeuftUeber()) {
			return;
		}

		zeile.classList.add(KONTOICON);
	}

	function abschliessen(offen, burger) {
		zeile.classList.remove(MESSEN);

		// Im Hamburger gibt es kein „mehr" — und damit auch keinen offenen
		// Zustand, den man retten müsste.
		menue.hidden = burger ? true : !offen;
		knopf.setAttribute('aria-expanded', !burger && offen ? 'true' : 'false');
	}

	function verteilen() {
		var offen = knopf.getAttribute('aria-expanded') === 'true';

		// **Jeder Durchgang stellt zuerst den vollen Zustand her** — Punkte in
		// der Leiste, Nummer in ihrer Bahn, keine der beiden Darstellungen
		// erzwungen. Nur so hat die Messung natürliche Breiten vor sich
		// (Falle 1), und nur so tritt der Hamburger beim Vergrößern wieder
		// zurück.
		heim();
		zeile.classList.remove(BURGER);
		eintrag.hidden = true;
		zeile.classList.add(MESSEN);

		if (!laeuftUeber()) {
			abschliessen(offen, false);

			return;
		}

		// --- Erste Darstellung: „mehr" ---------------------------------
		eintrag.hidden = false;

		var ausgelagert = 0;

		for (var k = 0; k < rang.length; k++) {
			if (!laeuftUeber()) {
				break;
			}

			auslagern(rang[k].li);
			ausgelagert++;
		}

		if (punkte.length - ausgelagert >= MINDESTENS_IN_DER_LEISTE) {
			// Die Nummer geht zuletzt — sie ist der Grund für die mittlere Bahn.
			if (telefon && laeuftUeber()) {
				telefonAuslagern(menue, null);
			}

			kontoNachgeben();
			abschliessen(offen, false);

			return;
		}

		// --- Zweite Darstellung: der Hamburger -------------------------
		//
		// Weniger als drei ausgeschriebene Punkte: die Leiste hat aufgehört,
		// eine zu sein. Alles geht zurück — die Punkteliste **ist** der Inhalt
		// des Overlays, es wird nichts kopiert und nichts zweimal gehalten.
		heim();
		eintrag.hidden = true;
		zeile.classList.add(BURGER);

		// Der Öffner ist schmaler als die Leiste, die er ersetzt; die Nummer
		// passt danach fast immer wieder. Wenn nicht, geht sie mit ins Overlay
		// — vor „mehr", das dort ohnehin nicht steht.
		if (telefon && laeuftUeber()) {
			telefonAuslagern(liste, eintrag);
		}

		kontoNachgeben();
		abschliessen(offen, true);
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
