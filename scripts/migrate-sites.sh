#!/usr/bin/env bash
# Repository-owned, opt-in replacement-plugin migration over SSH.
# Usage: scripts/migrate-sites.sh --host SSH_HOST --tag vX.Y.Z --source SOURCE
#          [--parent PATTERN] [--skip NAME]... [--wp-cli COMMAND] [--user ID]
#          [--dry-run | --apply] [--finish]
# Defaults to listing candidate single sites without changing anything.
# --apply backs up, installs if missing, activates, imports renderable pages,
# enables consent, deactivates sources and verifies HTTP 200/banner markup.
# --finish --apply is a SEPARATE destructive pass: deletes inactive sources,
# cleans up their data, purges LiteSpeed and verifies again. Review the site
# and its import record first. Multisite requires a separate reviewed plan.
# Backups: ~/.seoprocookieconsent-deploy/migrate-NAME-STAMP (700, files 600).
# Source keys and plugin directories come from the repository adapters.
# Published zip digest/provenance checks follow deploy-sites.sh.
#
# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Marcus Quinn
# Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
# Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.

# PHP expressions below are intentionally single-quoted.
# shellcheck disable=SC2016
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST="" TAG="" SOURCE="" USER_ID="" WP_CLI="wp" APPLY=0 FINISH=0 TMP_DIR=""
# shellcheck disable=SC2088 # Expanded by the remote runner, not locally.
PARENT='~/domains/*/public_html'
SKIPS=()
SLUG=seoprocookieconsent
REPO=wpallstars/seoprocookieconsent

die() {
	local message="$1"
	printf 'migrate-sites: %s\n' "$message" >&2
	exit 2
}

cleanup() {
	[[ -z "$TMP_DIR" || ! -d "$TMP_DIR" ]] || rm -rf "$TMP_DIR"
	return 0
}

remote_words() {
	local word out=""
	for word in "$@"; do
		out+="$(printf '%q' "$word") "
	done
	printf '%s' "${out% }"
	return 0
}

remote() {
	local command="$1"
	ssh -o BatchMode=yes "$HOST" "$command" || return 1
	return 0
}

sha256_of() {
	local file="$1"
	if command -v sha256sum >/dev/null 2>&1; then
		sha256sum "$file" | awk '{print $1}'
	else
		shasum -a 256 "$file" | awk '{print $1}'
	fi
	return 0
}

parse_args() {
	local arg value
	while [[ $# -gt 0 ]]; do
		arg="$1" value="${2:-}"
		case "$arg" in
		--host | --tag | --source | --parent | --skip | --wp-cli | --user)
			[[ $# -ge 2 && -n "$value" ]] || die "$arg needs a value"
			case "$arg" in
			--host) HOST="$value" ;;
			--tag) TAG="$value" ;;
			--source) SOURCE="$value" ;;
			--parent) PARENT="$value" ;;
			--skip) SKIPS+=("$value") ;;
			--wp-cli) WP_CLI="$value" ;;
			--user) USER_ID="$value" ;;
			*) ;;
			esac
			shift
			;;
		--apply) APPLY=1 ;;
		--dry-run) APPLY=0 ;;
		--finish) FINISH=1 ;;
		-h | --help) sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) die "unknown argument: $arg" ;;
		esac
		shift
	done
	[[ -n "$HOST" && "$HOST" != -* ]] || die '--host is required and cannot start with -'
	[[ "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || die '--tag must be vX.Y.Z'
	[[ "$SOURCE" =~ ^[a-z0-9-]+$ && -n "$PARENT" ]] || die '--source and nonempty --parent are required'
	return 0
}

download_zip() {
	local name="$SLUG-${TAG#v}.zip" bundle="provenance-$SLUG-${TAG#v}.sigstore.json"
	local assets digest checked=0
	gh release download "$TAG" --repo "$REPO" --pattern "$name" --dir "$TMP_DIR" || die 'release download failed'
	ZIP="$TMP_DIR/$name"
	SHA="$(sha256_of "$ZIP")"
	assets="$(gh api "repos/$REPO/releases/tags/$TAG" --jq '.assets[] | [.name, (.digest // "")] | @tsv')" || die 'cannot read release assets'
	digest="$(awk -F '\t' -v n="$name" '$1 == n { print $2 }' <<<"$assets")"
	if [[ -n "$digest" ]]; then
		[[ "$digest" == "sha256:$SHA" ]] || die 'download does not match release SHA-256'
		checked=1
	fi
	if awk -F '\t' -v n="$bundle" '$1 == n { found = 1 } END { exit !found }' <<<"$assets"; then
		gh release download "$TAG" --repo "$REPO" --pattern "$bundle" --dir "$TMP_DIR" || die 'cannot download provenance'
		gh attestation verify "$ZIP" --bundle "$TMP_DIR/$bundle" --repo "$REPO" \
			--signer-workflow "$REPO/.github/workflows/release.yml" --source-ref "refs/tags/$TAG" >/dev/null || die 'provenance verification failed'
		checked=1
	fi
	[[ "$checked" -eq 1 ]] || die 'release has neither a digest nor provenance'
	printf 'Release %s: SHA-256/provenance verified\n' "$TAG"
	return 0
}

main() {
	parse_args "$@"
	local tool dirs mode=list command dir base run stamp file
	for tool in gh ssh php; do
		command -v "$tool" >/dev/null || die "needs $tool"
	done
	command -v sha256sum >/dev/null || command -v shasum >/dev/null || die 'needs sha256sum or shasum'
	# Loading declarations only needs no WordPress runtime. Never copy the
	# adapter's plugin-directory list into this operator script.
	dirs="$(php -r 'define("ABSPATH", $argv[1] . "/"); require $argv[1] . "/includes/migrate/class-seoprocookieconsent-migrate-source.php"; foreach (glob($argv[1] . "/includes/migrate/class-seoprocookieconsent-migrate-*.php") as $file) { require_once $file; } foreach (get_declared_classes() as $class) { if (is_subclass_of($class, "SEOProCookieConsent_Migrate_Source") && $class::KEY === $argv[2]) { echo implode("\n", $class::SLUGS); exit(0); } } exit(1);' "$SCRIPT_DIR/.." "$SOURCE")" || die 'unknown source adapter'
	[[ -n "$dirs" ]] || die 'source has no plugin folders'
	trap cleanup EXIT
	TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/$SLUG-migrate.XXXXXX")"
	ZIP="" SHA=""
	if [[ "$APPLY" -eq 1 ]]; then
		download_zip
		mode=apply
		[[ "$FINISH" -eq 0 ]] || mode=finish
	fi
	command="$(remote_words env "DEPLOY_LIBRARY_ONLY=1" "DEPLOY_WP_CLI=$WP_CLI" "MIGRATE_USER=$USER_ID" "MIGRATE_SOURCE_DIRS=$dirs")"
	if [[ "$APPLY" -eq 0 ]]; then
		command+=" $(remote_words bash -s -- list "$SLUG" "$PARENT" "$SOURCE" "${TAG#v}" '' '' '' ${SKIPS[@]+"${SKIPS[@]}"})"
		cat "$SCRIPT_DIR/lib/deploy-remote.sh" "$SCRIPT_DIR/lib/migrate-remote.sh" | remote "$command" || die 'dry run failed'
		printf 'Dry run: nothing changed; --finish also requires --apply.\n'
		return 0
	fi
	stamp="$(date -u +%Y%m%dT%H%M%SZ)"
	base=".$SLUG-deploy"
	run="$base/migrate-run-$SOURCE-$stamp"
	dir="$(remote "umask 077 && cd && mkdir -p $run && chmod 700 $base $run && cd $run && pwd")" || die 'cannot create private remote folder'
	[[ "$dir" == /* ]] || die 'unexpected remote folder'
	remote "umask 077; cat >$(remote_words "$dir/plugin.zip")" <"$ZIP" || die 'cannot copy zip'
	for file in deploy-remote.sh migrate-remote.sh; do
		remote "umask 077; cat >$(remote_words "$dir/$file")" <"$SCRIPT_DIR/lib/$file" || die 'cannot copy remote runner'
	done
	command+=" $(remote_words bash "$dir/migrate-remote.sh" "$mode" "$SLUG" "$PARENT" "$SOURCE" "${TAG#v}" "$dir/plugin.zip" "$SHA" "${dir%/*}" ${SKIPS[@]+"${SKIPS[@]}"})"
	remote "$command" || return 1
	return 0
}

main "$@"
