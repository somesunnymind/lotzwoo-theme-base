# Mitarbeiten

## Ablauf

- Ein Branch je Änderung, Pull Request gegen `main`. CI muss grün sein.
- Commit-Nachrichten nach Conventional Commits: `feat:`, `fix:`, `refactor:`, `docs:`, `test:`,
  `chore:`, `perf:`, `ci:`.
- Arbeitssprache in Kommentaren und Doku: Deutsch.
- Kommentare beschreiben den Code, wie er ist: keine Vorgangsnummern, keine Daten, keine Namen von
  Kunden oder Personen, keine Serverpfade. Die Sperrliste (`scripts/paket-sperrliste.txt`) prüft
  Repo und Release-ZIP.

## Prüfen

```bash
npm ci
npm run check
php scripts/check-updater.php
```

Eine Kommentar-Änderung ändert keinen Code. Nachweis für PHP:

```bash
diff <(git show main:functions.php | php -w) <(php -w functions.php)
```

## Release

1. `Version:` in `style.css` erhöhen (Semver), committen, nach `main`.
2. Tag `X.Y.Z` ohne `v` setzen und pushen.
3. Der Workflow `release.yml` prüft Tag gegen Header, ZIP gegen `check_package()` und Sperrliste,
   und hängt `lotzwoo-theme-base.zip` an das Release.

Ein Tag ist die Auslieferung an alle Installationen.
