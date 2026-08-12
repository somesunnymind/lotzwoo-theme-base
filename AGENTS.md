# Agenten-Kontext — lotzwoo-theme-base

Dieses Repo trägt **nur** das Basis-Theme. Alles Planerische — Wayfinder-Karten, `AD-*`-Entscheidungen,
Befunde, Runbooks — liegt im Plugin-Repo `somesunnymind/lotzapp-for-woocommerce`.

**Lies dessen `AGENTS.md`, bevor du hier arbeitest.** Die Regeln stehen dort und werden hier
absichtlich nicht wiederholt: Regeln an zwei Stellen laufen auseinander, und genau das hat dieses
Projekt bei AD-1 schon einmal bezahlt — drei Fassungen, eine davon einen Tag lang nirgends.

Was hier gilt und dort nicht steht:

- **Ein Branch: `main`.** Keine Feature-Branches, keine Worktrees.
- **Committen ja, pushen nur auf ausdrückliche Zusage.** Ein Tag ist die Auslieferung an Kunden und
  ein eigenes Gate — siehe README.
- **Vor jeder Änderung**: `git status --short`. Das Deploy-Skript bricht bei unsauberer Arbeitskopie
  ab, weil es `HEAD` ausliefert und nicht, was hier liegt.
- **Prüfen vor dem Commit:** `npm run check`.

## Die drei Kopplungen nach außen

1. **Der Platzhalter-Block in `parts/header.html`** — dieses Theme registriert ihn, das Plugin füllt
   ihn. Wer ihn umbenennt, bricht das Plugin, ohne dass hier etwas rot wird.
2. **Der Anker im Warenkorb** — `lotzwoo/cart-slot` in `templates/page-cart.html`, dessen
   `render_callback` `<div id="lotzwoo-cart-slot"></div>` liefert. Zwei Zeichenketten statt einer:
   Blockname **und** Div-ID, letztere gegen `MOUNT_ID` in `assets/js/b2b-cart.js`. Ticket 15.
3. **Die Token-Brücke in `style.css`** — sie übersetzt `--wp--preset--*` in die `--lwb-*` des
   Plugins, auf den Wurzeln `.lotzwoo-b2b-shop`, `.lotzwoo-reorder` und `.lotzwoo-heldback`. Die
   Richtung ist Absicht (AD-8): das Theme hängt am Plugin, nie umgekehrt.

4. **Die zwei Parts, die dem Kundentheme gehören** — `parts/navigation.html` und
   `parts/footer-links.html`. AD-11: die Seitenstruktur eines Kunden ist kundenspezifisch und darf
   hier nicht stehen. **Beide Dateien sind leer**, und das ist die Vorgabe für eine Installation
   ohne Kindtheme: die Basis hat zur Seitenstruktur eines Kunden keine Meinung. Eine generische
   `core/page-list` stand hier kurz als Rückfall und ist am 2026-08-12 wieder herausgeflogen — sie
   zieht die Seiten aus der **Datenbank** und lieferte auf dieser Installation prompt „Über uns",
   „Produkte", „Cart", „Checkout" und „Sample Page" in die Kopfzeile. Kundenspezifisch bleibt
   kundenspezifisch, auch wenn es nicht im Theme steht.

   Dass ein leerer Part **lautlos** leer ist, fängt `scripts/check-frontend.php` ab: es warnt, wenn
   ein Kindtheme aktiv ist und seinen Part nicht mitbringt. Der Rahmen drumherum — Logo,
   `lotzwoo/header-slot`, Konto,
   Warenkorb, der Knopf „Zum Sortiment", die Rechtsspalte des Fußes — bleibt hier und verbessert
   sich damit für alle Kunden zugleich.

   Wer hier einen Menüpunkt einträgt, den ein bestimmter Kunde braucht, hat den Fehler gemacht, den
   Ticket 16 gefunden hat.

`scripts/check-frontend.php` deckt 1 mit zwei, 2 mit drei und 4 mit zwei Prüfungen ab. Es braucht
eine laufende Installation und läuft nicht in der CI.

## Was hier nicht hingehört

Kein `docs/`, kein `.hermes/`, kein `CHANGELOG.md`, kein `composer.json`, kein PHPCS, kein
`.pot`-Bau. Die Begründung je Posten steht in Ticket 06 der Karte `theme-templates-und-repo`,
Abschnitt 13.
