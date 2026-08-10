# Agenten-Kontext — lotzapp-base

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

## Die zwei Kopplungen nach außen

1. **Der Platzhalter-Block in `parts/header.html`** — dieses Theme registriert ihn, das Plugin füllt
   ihn. Wer ihn umbenennt, bricht das Plugin, ohne dass hier etwas rot wird.
2. **Die Token-Brücke in `style.css`** — sie übersetzt `--wp--preset--*` in die `--lwb-*` des
   Plugins, auf den Wurzeln `.lotzwoo-b2b-shop` und `.lotzwoo-reorder`. Die Richtung ist Absicht
   (AD-8): das Theme hängt am Plugin, nie umgekehrt.

## Was hier nicht hingehört

Kein `docs/`, kein `.hermes/`, kein `CHANGELOG.md`, kein `composer.json`, kein PHPCS, kein
`.pot`-Bau. Die Begründung je Posten steht in Ticket 06 der Karte `theme-templates-und-repo`,
Abschnitt 13.
