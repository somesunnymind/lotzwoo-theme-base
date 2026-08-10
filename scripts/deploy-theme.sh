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

SLUG="lotzapp-base"
THEMES_DIR="${1:-/var/www/b2b/wp-content/themes}"
TARGET="$THEMES_DIR/$SLUG"

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

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
