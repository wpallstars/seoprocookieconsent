#!/usr/bin/env bash
# Refresh the bundled catalogs before committing a release; --check writes only to temp.
# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Marcus Quinn
# Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
# Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
SOURCE="${2:-$ROOT}"
DOMAIN=seoprocookieconsent
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
WP=(wp)
SOURCE_PATH="$SOURCE"
OUTPUT_PATH="$WORK"
if ! command -v wp >/dev/null 2>&1; then
	# Release CI already has Docker; do not require extra host packages there.
	WP=(docker run --rm --user "$(id -u):$(id -g)" -v "$SOURCE:/source:ro" -v "$WORK:/output" --entrypoint wp wordpress:cli)
	SOURCE_PATH=/source
	OUTPUT_PATH=/output
fi
"${WP[@]}" i18n make-pot "$SOURCE_PATH" "$OUTPUT_PATH/$DOMAIN.pot" --slug="$DOMAIN" --exclude=dist,scripts,languages \
	--headers='{"POT-Creation-Date":"","Report-Msgid-Bugs-To":"https://github.com/wpallstars/seoprocookieconsent/issues"}'
# Drop messages that are only a URL (the header's Plugin URI and Author URI):
# nobody translates them, as in SEO Pro Stack's scripts/translations.sh.
# Plain awk, so CI needs no gettext tools for it.
drop_urls() {
	local file="$1"
	awk 'BEGIN { RS = ""; ORS = "\n\n" } !/(^|\n)msgid "https?:\/\/[^" ]*"\nmsgstr/' "$file" >"$file.tmp"
	# Paragraph mode leaves one extra blank line at the end of the file.
	sed -e '$ { /^$/ d; }' "$file.tmp" >"$file"
	rm -f "$file.tmp"
	return 0
}
drop_urls "$WORK/$DOMAIN.pot"
for locale in nl_BE de_DE fr_CA; do
	cp "$SOURCE/languages/$DOMAIN-$locale.po" "$WORK/"
	drop_urls "$WORK/$DOMAIN-$locale.po"
	if command -v msgfmt >/dev/null 2>&1; then
		msgfmt --check --check-format "$WORK/$DOMAIN-$locale.po" -o /dev/null
	fi
done
"${WP[@]}" i18n make-mo "$OUTPUT_PATH"
"${WP[@]}" i18n make-json "$OUTPUT_PATH" --no-purge --pretty-print
if [[ "${1:-}" == --check ]]; then
	for file in "$WORK/"*.pot "$WORK/"*.po "$WORK/"*.mo "$WORK/"*.json; do
		cmp "$file" "$SOURCE/languages/${file##*/}" || {
			printf 'Stale translation: %s; run scripts/update-translations.sh\n' "${file##*/}" >&2
			exit 1
		}
	done
else
	cp "$WORK/"*.pot "$WORK/"*.po "$WORK/"*.mo "$WORK/"*.json "$ROOT/languages/"
fi
