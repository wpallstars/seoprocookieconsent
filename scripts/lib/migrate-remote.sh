#!/usr/bin/env bash
# Remote half of migrate-sites.sh; not an unattended production rollout.
# Usage: bash migrate-remote.sh list|apply|finish SLUG PARENT SOURCE VERSION ZIP SHA BACKUPS [SKIP]...
# MIGRATE_SOURCE_DIRS comes from the repository adapter constants, one per line.
# MIGRATE_USER optionally selects a WordPress administrator (otherwise the first).
# Multisite migration is refused before any change: rendering, import and cleanup
# are site-local. Network activation/deletion needs a separately reviewed plan.
# Every FAIL stops this install, not the host. Backups remain private and are
# never automatically imported. Finish is a separate, explicit destructive pass.
#
# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Marcus Quinn
# Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
# Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.

# shellcheck disable=SC2016 # Single-quoted eval arguments contain PHP variables.
set -uo pipefail
if ! declare -F active_guard >/dev/null; then
	# shellcheck source=scripts/lib/deploy-remote.sh
	DEPLOY_LIBRARY_ONLY=1 . "$(dirname "${BASH_SOURCE[0]}")/deploy-remote.sh"
fi
SOURCE=""
SOURCE_DIRS=()
MIGRATE_USER="${MIGRATE_USER:-}"
MIGRATE_BACKUP_COMPLETE=0

migrate_wp() {
	local root="$1"
	shift
	wp_checked "$root" --user="$MIGRATE_USER" "$@" || return 1
	return 0
}

migrate_backup() {
	local root="$1" dir="$2"
	local folder database canonical_root canonical_dir status=0
	local folders=()
	umask 077
	mkdir -p "$dir" && chmod 700 "$dir" || return 1
	canonical_root="$(cd "$root" && pwd -P)" || return 1
	canonical_dir="$(cd "$dir" && pwd -P)" || return 1
	if [[ "$canonical_dir" == "$canonical_root" || "$canonical_dir" == "$canonical_root/"* ]]; then
		say 'FAIL: backups must be outside the web root; nothing changed'
		return 1
	fi
	active_snapshot "$root" >"$dir/active.json" || return 1
	for folder in "$SLUG" "${SOURCE_DIRS[@]}"; do
		[[ ! -d "$root/wp-content/plugins/$folder" ]] || folders+=("$folder")
	done
	if [[ "${#folders[@]}" -gt 0 ]]; then
		tar -czf "$dir/plugins.tar.gz" -C "$root/wp-content/plugins" "${folders[@]}" || return 1
	else
		tar -czf "$dir/plugins.tar.gz" -T /dev/null || return 1
	fi
	# Do not pass database passwords on argv or print them. This file is private,
	# outside the docroot and removed even when mysqldump fails.
	FLEET_MYSQL_CONFIG="$dir/mysql.cnf" wp_checked "$root" --skip-plugins --skip-themes eval '
$quote = function ($s) { return "\"" . str_replace(array("\\", "\"", "\n", "\r"), array("\\\\", "\\\"", "\\n", "\\r"), $s) . "\""; };
$host = DB_HOST; $extra = "";
if (preg_match("~^([^:]+):(/.+)$~", $host, $m)) { $host = $m[1]; $extra = "socket=" . $quote($m[2]) . "\n"; }
elseif (preg_match("~^([^:]+):([0-9]+)$~", $host, $m)) { $host = $m[1]; $extra = "port=" . $m[2] . "\n"; }
elseif (strpos($host, ":") !== false) { WP_CLI::error("Unsupported DB_HOST; no migration performed"); }
$file = getenv("FLEET_MYSQL_CONFIG");
if (false === file_put_contents($file, "[client]\nuser=" . $quote(DB_USER) . "\npassword=" . $quote(DB_PASSWORD) . "\nhost=" . $quote($host) . "\n" . $extra) || !chmod($file, 0600)) { WP_CLI::error("Cannot create private dump configuration"); }
' >/dev/null || status=1
	if [[ "$status" -eq 0 ]]; then
		database="$(wp_bare "$root" eval 'echo DB_NAME;')" || status=1
	fi
	if [[ "$status" -eq 0 ]]; then
		mysqldump --defaults-extra-file="$dir/mysql.cnf" --single-transaction --skip-lock-tables -- "$database" >"$dir/database.sql" || status=1
		[[ -s "$dir/database.sql" ]] || status=1
	fi
	rm -f "$dir/mysql.cnf" || return 1
	[[ "$status" -eq 0 ]] || return 1
	chmod 600 "$dir/active.json" "$dir/plugins.tar.gz" "$dir/database.sql" || return 1
	return 0
}

# Select renderable pages from the actual plan. Refuse any unresolved source
# tokens rather than deactivate a plugin still needed to render a document.
migrate_pages() {
	local root="$1"
	FLEET_SOURCE="$SOURCE" migrate_wp "$root" eval '
$plan = SEOProCookieConsent_Migrate::plan(getenv("FLEET_SOURCE"));
if (is_wp_error($plan)) { WP_CLI::error($plan->get_error_message()); }
$ids = array();
foreach ($plan["pages"] as $page) {
    foreach ($page["tokens"] as $token) {
        if (empty($token["renderable"])) { WP_CLI::error("Unrenderable source document on page " . $page["id"] . "; keep source active and review"); }
    }
    if ($page["tokens"]) { $ids[] = (int) $page["id"]; }
}
echo implode(",", $ids);' || return 1
	return 0
}

migrate_verify() {
	local root="$1" dir="$2"
	local home code
	home="$(wp_bare "$root" option get home)" || return 1
	[[ "$home" == https://* || "$home" == http://* ]] || return 1
	code="$(curl -sS -L --max-time 30 -o "$dir/home.html" -w '%{http_code}' "${home%/}/")" || return 1
	[[ "$code" == 200 ]] || return 1
	# Markup exists even when the visitor-region decision hides the banner.
	grep -q 'id="spcc-banner"' "$dir/home.html" || return 1
	return 0
}

# Cleanup may remove generated source uploads as well as database rows. Keep
# those exact folders with the plugin tar and SQL dump before running uninstall.
migrate_upload_backup() {
	local root="$1" dir="$2"
	local base
	base="$(migrate_wp "$root" eval 'echo wp_upload_dir(null, false)["basedir"];')" || return 1
	FLEET_SOURCE="$SOURCE" migrate_wp "$root" eval '
$plan = SEOProCookieConsent_Migrate::cleanup(getenv("FLEET_SOURCE"), true);
if (is_wp_error($plan)) { WP_CLI::error($plan->get_error_message()); }
$base = realpath(wp_upload_dir(null, false)["basedir"]);
foreach ($plan["items"]["uploads"] ?? array() as $folder) {
    if (!preg_match("~^[a-zA-Z0-9_][a-zA-Z0-9_./-]*$~D", $folder) || strpos($folder, "..") !== false || strpos($folder, "/") === false) { WP_CLI::error("Unsafe source upload path"); }
    $real = $base ? realpath($base . "/" . $folder) : false;
    if (!$base || !$real || strpos($real . "/", $base . "/") !== 0 || is_link($base . "/" . $folder)) { WP_CLI::error("Cannot safely back up source uploads"); }
    echo $folder . "\n";
}' >"$dir/upload-paths" || return 1
	if [[ -s "$dir/upload-paths" ]]; then
		tar -czf "$dir/uploads.tar.gz" -C "$base" -T "$dir/upload-paths" || return 1
		printf '    restore uploads: tar -xzf %q -C %q\n' "$dir/uploads.tar.gz" "$base"
	fi
	return 0
}

migrate_one() {
	local root="$1" mode="$2" want="$3" zip="$4" dir="$5"
	local kind user files file pages imported dirs present=0
	for file in "${SOURCE_DIRS[@]}"; do
		[[ ! -d "$root/wp-content/plugins/$file" ]] || present=1
	done
	if [[ "$present" -eq 0 && ! -f "$root/wp-content/plugins/$SLUG/$SLUG.php" ]]; then
		printf '%s\tskipped (no source or destination)\n' "$root"
		return 0
	fi
	kind="$(wp_bare "$root" eval 'echo is_multisite() ? "multisite" : "single";')" || return 1
	[[ "$kind" == single ]] || { say 'FAIL: multisite migration requires a reviewed per-site/network plan'; return 1; }
	if [[ "$mode" == list ]]; then
		printf '%s\tsource=%s\tfolders_present=%s\tdry-run\n' "$root" "$SOURCE" "$present"
		return 0
	fi
	user="$MIGRATE_USER"
	if [[ -z "$user" ]]; then
		user="$(wp_bare "$root" eval '$users = get_users(array("role" => "administrator", "number" => 1, "fields" => "ID")); if (!$users) { WP_CLI::error("No administrator"); } echo $users[0];')" || return 1
	fi
	local MIGRATE_USER="$user"
	migrate_wp "$root" --skip-plugins --skip-themes eval 'if (!current_user_can("manage_options") || !current_user_can("activate_plugins") || !current_user_can("delete_plugins")) { WP_CLI::error("Administrator capabilities required"); }' >/dev/null || return 1
	# Missing sources with a complete import are idempotently skipped (including
	# finish: cleanup was already performed, or must be reviewed separately).
	if [[ "$present" -eq 0 ]]; then
		imported="$(FLEET_SOURCE="$SOURCE" migrate_wp "$root" eval 'echo SEOProCookieConsent_Migrate::imported(getenv("FLEET_SOURCE")) ? "yes" : "no";')" || return 1
		[[ "$imported" == yes ]] || return 1
		say 'ok: source gone and complete import exists; skipped'
		return 0
	fi
	migrate_backup "$root" "$dir" || return 1
	MIGRATE_BACKUP_COMPLETE=1
	if [[ ! -f "$root/wp-content/plugins/$SLUG/$SLUG.php" ]]; then
		[[ "$mode" != finish ]] || return 1
		active_guard "$root" "" "" --skip-plugins --skip-themes plugin install "$zip" >/dev/null || return 1
	fi
	[[ "$(plugin_version "$root/wp-content/plugins/$SLUG/$SLUG.php")" == "$want" ]] || { say 'FAIL: destination version differs from --tag; deploy that release first'; return 1; }
	if [[ "$mode" != finish ]]; then
		active_guard "$root" "$SLUG/$SLUG.php" "" --user="$user" plugin activate "$SLUG" >/dev/null || return 1
	fi
	# Adapter metadata is authoritative, not a duplicate plugin-slug map.
	dirs="$(FLEET_SOURCE="$SOURCE" migrate_wp "$root" eval '$sources = SEOProCookieConsent_Migrate::sources(); $source = $sources[getenv("FLEET_SOURCE")] ?? null; if (!$source) { WP_CLI::error("Unknown source"); } echo implode("\n", $source::SLUGS);')" || return 1
	[[ "$dirs" == "$MIGRATE_SOURCE_DIRS" ]] || { say 'FAIL: adapter metadata differs; refresh the rollout checkout'; return 1; }
	files="$(FLEET_DIRS="$dirs" migrate_wp "$root" eval 'require_once ABSPATH . "wp-admin/includes/plugin.php"; foreach (get_plugins() as $file => $data) { if (in_array(dirname($file), explode("\n", getenv("FLEET_DIRS")), true)) { echo $file . "\n"; } }')" || return 1
	if [[ "$mode" == finish ]]; then
		imported="$(FLEET_SOURCE="$SOURCE" migrate_wp "$root" eval 'echo SEOProCookieConsent_Migrate::imported(getenv("FLEET_SOURCE")) ? "yes" : "no";')" || return 1
		[[ "$imported" == yes ]] || { say 'FAIL: finish requires a complete import'; return 1; }
		migrate_upload_backup "$root" "$dir" || return 1
		# Core deletion runs uninstall hooks. Guard it too, and refuse active
		# sources before deletion instead of silently deactivating in finish.
		FLEET_FILES="$files" active_guard "$root" "" "" --user="$user" eval '
require_once ABSPATH . "wp-admin/includes/plugin.php";
$files = array_filter(explode("\n", getenv("FLEET_FILES")));
foreach ($files as $file) { if (is_plugin_active($file)) { WP_CLI::error("Source still active; run migration first"); } }
$result = $files ? delete_plugins($files) : true;
if (is_wp_error($result) || !$result) { WP_CLI::error("Source plugin deletion failed"); }' >/dev/null || return 1
		active_guard "$root" "" "" --user="$user" seoprocookieconsent migrate cleanup "$SOURCE" --yes >"$dir/cleanup.json" || return 1
	else
		migrate_wp "$root" seoprocookieconsent migrate plan "$SOURCE" >"$dir/plan.json" || return 1
		pages="$(migrate_pages "$root")" || return 1
		active_guard "$root" "" "" --user="$user" seoprocookieconsent migrate import "$SOURCE" --pages="$pages" >"$dir/import.json" || return 1
		FLEET_SOURCE="$SOURCE" active_guard "$root" "" "" --user="$user" eval 'if (!SEOProCookieConsent_Migrate::imported(getenv("FLEET_SOURCE"))) { WP_CLI::error("No complete persisted import record"); } SEOProCookieConsent_Settings::set("consent", true); if (!SEOProCookieConsent_Settings::get("consent")) { WP_CLI::error("Consent not enabled"); }' >/dev/null || return 1
		while IFS= read -r file; do
			[[ -n "$file" ]] || continue
			active_guard "$root" "" "$file" --user="$user" plugin deactivate "$file" >/dev/null || return 1
		done <<<"$files"
	fi
	# Purge after the switch too: a cached old page must not hide verification.
	if ! migrate_wp "$root" eval 'if (is_plugin_active("litespeed-cache/litespeed-cache.php")) { do_action("litespeed_purge_all"); }' >/dev/null; then
		return 1
	fi
	migrate_verify "$root" "$dir" || return 1
	say "ok: $mode $SOURCE; backup=$dir"
	return 0
}

migrate_main() {
	local mode="${1:-}" slug="${2:-}" parent="${3:-}" source="${4:-}"
	local want="${5:-}" zip="${6:-}" sha="${7:-}" backups="${8:-}"
	local roots root name folder dir count=0
	shift 8 || return 2
	SLUG="$slug"
	SOURCE="$source"
	SKIPS=("$@")
	[[ "$mode" == list || "$mode" == apply || "$mode" == finish ]] || return 2
	[[ "$SLUG" == seoprocookieconsent && "$SOURCE" =~ ^[a-z0-9-]+$ && -n "$parent" ]] || return 2
	SOURCE_DIRS=()
	while IFS= read -r folder; do
		[[ "$folder" =~ ^[a-z0-9-]+$ ]] || return 2
		SOURCE_DIRS+=("$folder")
	done <<<"${MIGRATE_SOURCE_DIRS:-}"
	command -v "$WP_CLI" >/dev/null || return 2
	if [[ "$mode" != list ]]; then
		command -v mysqldump >/dev/null || return 2
		[[ "$(sha256_of "$zip")" == "$sha" && "$backups" == /* ]] || return 2
	fi
	[[ "$parent" != \~/* ]] || parent="$HOME/${parent#\~/}"
	roots="$(compgen -G "$parent" | LC_ALL=C sort || true)"
	while IFS= read -r root; do
		[[ -f "$root/wp-config.php" ]] || continue
		name="$(install_name "$root")"
		skipped "$root" "$name" && continue
		count=$((count + 1))
		dir="$backups/migrate-$name-$(date -u +%Y%m%dT%H%M%SZ)-$$-$count"
		MIGRATE_BACKUP_COMPLETE=0
		if ! migrate_one "$root" "$mode" "$want" "$zip" "$dir"; then
			FAILED=1
			if [[ "$MIGRATE_BACKUP_COMPLETE" -eq 0 ]]; then
				printf '%s\tFAIL: stopped before changes; restore: none needed; never import an incomplete backup\n' "$name"
			else
				printf '%s\tFAIL: stopped; restore files: tar -xzf %q -C %q; restore database: %q --path=%q db import %q\n' \
					"$name" "$dir/plugins.tar.gz" "$root/wp-content/plugins" "$WP_CLI" "$root" "$dir/database.sql"
			fi
		fi
	done <<<"$roots"
	[[ "$count" -gt 0 && "$FAILED" -eq 0 ]] || return 1
	return 0
}

migrate_main "$@"
