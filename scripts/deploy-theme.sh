#!/usr/bin/env bash
#
# Das Theme in eine WordPress-Installation ausliefern.
#
#   scripts/deploy-theme.sh [ZIEL-THEMES-VERZEICHNIS]
#
# Standardziel ist die B2B-Entwicklungsinstallation.
#
# Ausgeliefert wird der verfolgte Baum von HEAD, ohne die `export-ignore`-Pfade
# aus `.gitattributes`. Zwei Eigenschaften, auf die es dabei ankommt:
#
#   * Das Ziel ist der **Theme-Ordner**, nicht das Verzeichnis darueber. Ein
#     frueherer Kopiervorgang hat die Repo-Wurzel nach `themes/` ausgeliefert;
#     daher liegt dort bis heute eine verirrte `.gitignore`.
#
#   * `git archive` **loescht nie**. Das aktive Theme kann durch einen Deploy
#     also nicht wegfallen. Die Kehrseite: eine im Repo geloeschte Datei bleibt
#     im Ziel als Waise liegen — deshalb die Waisenpruefung am Ende.
#
# Was ein Deploy zerstoert: nichts. Anders als beim Plugin gibt es im Ziel keine
# lokale Aenderung, die zurueckgestellt werden muesste (geprueft 2026-08-10).
# Sobald das einmal nicht mehr stimmt, braucht auch das Theme seine eigene
# Tabelle im Runbook.

set -euo pipefail

SLUG="lotzwoo-theme-base"
THEMES_DIR="${1:-/var/www/b2b/wp-content/themes}"
TARGET="$THEMES_DIR/$SLUG"

# Das Repo kommt aus dem **Skriptpfad**, nicht aus dem Arbeitsverzeichnis.
#
# Bis zum 2026-08-26 stand hier `git rev-parse --show-toplevel`. Damit kam
# `SLUG` aus dem Skript und das Repo aus dem `cwd` — zwei Quellen, die
# auseinanderlaufen koennen. Aus dem Plugin-Verzeichnis heraus mit absolutem
# Pfad aufgerufen hat das Skript dann `git archive HEAD` des **Plugins** in den
# **Theme**-Ordner entpackt, 325 fremde Dateien, und danach wahrheitsgemaess
# "Deploy verifiziert" gemeldet: geprueft hatte es, dass die falschen Dateien
# heil angekommen sind. Kommen beide aus dem Skript, ist der Fall zu.
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
REPO_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd -P)"
cd "$REPO_ROOT"

# Zwei Riegel dahinter, denn der Skriptpfad allein ist nur so gut wie seine
# Lage: eine Kopie oder ein Symlink an anderer Stelle traegt ihn falsch.
#
#   1. Das Skript muss in `<repo>/scripts/` liegen — sonst zeigt `..` nicht auf
#      die Repo-Wurzel und `git archive` liefert einen fremden Baum.
#   2. Ein Theme-Repo traegt eine `style.css` in der Wurzel, ein Plugin-Repo
#      nicht. Genau daran waere der Unfall oben auch ohne 1. gescheitert.
if [ "$(git rev-parse --show-toplevel 2>/dev/null || true)" != "$REPO_ROOT" ]; then
  echo "$REPO_ROOT ist keine Repo-Wurzel — liegt dieses Skript in <repo>/scripts/?" >&2
  exit 1
fi

if [ ! -f "$REPO_ROOT/style.css" ]; then
  echo "$REPO_ROOT traegt keine style.css und ist damit kein Theme-Repo." >&2
  echo "Abbruch, bevor ein fremder Baum nach $TARGET ausgeliefert wird." >&2
  exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
  echo "Arbeitskopie ist nicht sauber — ausgeliefert wird HEAD, nicht was hier liegt." >&2
  git status --short >&2
  echo "Abbruch. Erst committen oder verwerfen." >&2
  exit 1
fi

if [ ! -d "$THEMES_DIR" ]; then
  echo "Kein Theme-Verzeichnis: $THEMES_DIR" >&2
  exit 1
fi

mkdir -p "$TARGET"

echo "Deploy $SLUG @ $(git rev-parse --short HEAD) -> $TARGET"
git archive HEAD | tar -x -C "$TARGET"

# Verifikation: jede ausgelieferte Datei muss im Ziel byte-gleich ankommen.
fail=0
while IFS= read -r -d '' f; do
  if ! cmp -s <(git show "HEAD:$f") "$TARGET/$f"; then
    echo "Abweichung nach dem Deploy: $f" >&2
    fail=1
  fi
done < <(git archive HEAD | tar -t --quoting-style=literal | grep -v '/$' | tr '\n' '\0')

# Waisen: was im Ziel liegt, aber nicht mehr ausgeliefert wird.
shipped="$(git archive HEAD | tar -t --quoting-style=literal | grep -v '/$' | sort)"
present="$(cd "$TARGET" && find . -type f -printf '%P\n' | sort)"
orphans="$(comm -13 <(echo "$shipped") <(echo "$present") || true)"

if [ -n "$orphans" ]; then
  echo "Waisen im Ziel (git archive loescht nicht — von Hand pruefen):" >&2
  echo "$orphans" >&2
fi

if [ "$fail" -ne 0 ]; then
  exit 1
fi

echo "Deploy verifiziert."

# Die Frontend-Pruefung hinterher.
#
# Der Deploy ist der einzige Vorgang, der sicher weiss, dass sich am Theme
# etwas geaendert hat und dass eine Installation danebensteht — der richtige
# Moment also, um zu fragen, ob noch jede Seite ihr Template bekommt.
#
# **Nicht blockierend, mit Absicht.** Der Deploy bleibt erfolgreich, auch wenn
# die Pruefung etwas findet; er sagt nur, dass sie etwas gefunden hat. Sonst
# passiert das, was solche Kopplungen immer erledigt: die erste unbequeme
# Warnung, und jemand baut den Aufruf wieder aus.
#
# Aufgerufen wird die Datei **aus dem Repo**, nicht aus dem Ziel: `scripts/`
# ist `export-ignore` und wird gar nicht ausgeliefert.
#
# Fehlt `wp` oder liegt das Ziel nicht in einer WordPress-Installation, wird
# uebersprungen statt zu scheitern. Dieses Skript liefert aus; das Pruefen ist
# die Zugabe.
CHECK="$REPO_ROOT/scripts/check-frontend.php"
WP_ROOT="${THEMES_DIR%/wp-content/themes}"

if [ ! -f "$CHECK" ]; then
  echo "Hinweis: $CHECK fehlt — Frontend-Pruefung uebersprungen."
  exit 0
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "Hinweis: kein wp-cli gefunden — Frontend-Pruefung uebersprungen."
  exit 0
fi

if [ "$WP_ROOT" = "$THEMES_DIR" ] || [ ! -f "$WP_ROOT/wp-load.php" ]; then
  echo "Hinweis: keine WordPress-Wurzel ueber $THEMES_DIR — Frontend-Pruefung uebersprungen."
  exit 0
fi

echo
if sg www-data -c "wp --path=$WP_ROOT eval-file $CHECK"; then
  exit 0
fi

echo
echo "Die Frontend-Pruefung hat etwas gefunden. Der Deploy selbst ist durch." >&2
exit 0
