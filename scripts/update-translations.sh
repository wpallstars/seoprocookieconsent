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
# Machine-translated entries carry a "# machine translation" translator comment,
# so a reviewer can tell them apart. WP-CLI's update-po drops translator
# comments; these two helpers list those entries (by their msgctxt/msgid text)
# before the merge and put the comment back after it. Plain awk, no gettext.
MACHINE_AWK='function key(rec, n, i, l, k, t, v) {
	n = split(rec, l, "\n"); k = ""; t = ""
	for (i = 1; i <= n; i++) {
		if (l[i] ~ /^msgstr/) break
		if (l[i] ~ /^(msgctxt|msgid_plural|msgid) /) {
			t = l[i]; sub(/ .*/, "", t); k = k "\001" t "="
			v = l[i]; sub(/^[a-z_]+ "/, "", v); sub(/"$/, "", v); k = k v
		} else if (t != "" && l[i] ~ /^"/) {
			v = l[i]; sub(/^"/, "", v); sub(/"$/, "", v); k = k v
		}
	}
	return k
}'
machine_keys() {
	local file="$1" out="$2"
	awk "$MACHINE_AWK"'
		BEGIN { RS = ""; ORS = "\n" }
		/(^|\n)# machine translation(\n|$)/ { print key($0) }' "$file" >"$out"
	return 0
}
machine_restore() {
	local file="$1" keys="$2"
	awk -v keys="$keys" "$MACHINE_AWK"'
		BEGIN {
			while ((getline line < keys) > 0) have[line] = 1
			RS = ""; ORS = "\n\n"
		}
		!/(^|\n)# machine translation(\n|$)/ && (key($0) in have) { print "# machine translation\n" $0; next }
		{ print }' "$file" >"$file.tmp"
	sed -e '$ { /^$/ d; }' "$file.tmp" >"$file"
	rm -f "$file.tmp"
	return 0
}
# Every bundled locale: each PO the plugin ships (wpallstars/seoprocookieconsent#130).
shopt -s nullglob
for po in "$SOURCE/languages/$DOMAIN-"*.po; do
	cp "$po" "$WORK/"
	drop_urls "$WORK/${po##*/}"
	machine_keys "$WORK/${po##*/}" "$WORK/${po##*/}.keys"
done
# Merge the new POT strings into every PO, so a string added to the code cannot
# stay missing from a locale (wpallstars/seoprocookieconsent#157).
"${WP[@]}" i18n update-po "$OUTPUT_PATH/$DOMAIN.pot" "$OUTPUT_PATH"
# update-po drops translator comments: put "# machine translation" back.
for po in "$WORK/$DOMAIN-"*.po; do
	machine_restore "$po" "$po.keys"
	rm -f "$po.keys"
done
# Count messages with no translation yet: plain awk, one record per message.
count_untranslated() {
	local file="$1"
	awk 'BEGIN { RS = ""; n = 0 }
		/(^|\n)msgid ""\nmsgstr ""\n"/ && !/(^|\n)msgid "[^"]/ { next }
		/^#~/ { next }
		/(^|\n)#, [^\n]*fuzzy/ { n++; next }
		{
			s = substr($0, index($0, "\nmsgstr") + 1)
			gsub(/(^|\n)msgstr(\[[0-9]+\])?/, "\n", s)
			gsub(/[ \n]|""/, "", s)
			if (s == "") n++
		}
		END { print n }' "$file"
	return 0
}
for po in "$WORK/$DOMAIN-"*.po; do
	if command -v msgfmt >/dev/null 2>&1; then
		msgfmt --check --check-format "$po" -o /dev/null
	fi
	if [[ "${1:-}" == --check ]]; then
		untranslated="$(count_untranslated "$po")"
		if [[ "$untranslated" != 0 ]]; then
			printf 'Warning: %s has %s untranslated messages\n' "${po##*/}" "$untranslated" >&2
		else
			printf '%s: 0 untranslated\n' "${po##*/}"
		fi
	fi
done
shopt -u nullglob
"${WP[@]}" i18n make-mo "$OUTPUT_PATH"
"${WP[@]}" i18n make-json "$OUTPUT_PATH" --no-purge --pretty-print
# A removed locale or script must not leave a compiled catalogue in the build.
# Keep PO sources and files belonging to other domains untouched.
shopt -s nullglob
CATALOG_DIR="$ROOT/languages"
if [[ "${1:-}" == --check ]]; then
	CATALOG_DIR="$SOURCE/languages"
fi
for file in "$CATALOG_DIR/$DOMAIN-"*.mo "$CATALOG_DIR/$DOMAIN-"*.json; do
	if [[ ! -f "$WORK/${file##*/}" ]]; then
		if [[ "${1:-}" == --check ]]; then
			printf 'Superseded translation: %s; run scripts/update-translations.sh\n' "${file##*/}" >&2
			exit 1
		fi
		rm -- "$file"
	fi
done
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
