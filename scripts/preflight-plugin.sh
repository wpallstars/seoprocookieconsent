#!/usr/bin/env bash
# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Marcus Quinn
# Additional terms (GPL-3.0 section 7(b)): ATTRIBUTION.txt
# Provenance: wpas-647035161c3dc0ef. Copies, even of part, keep these lines.

plugin_preflight() {
	local build="$1"
	section "Bundled translations"
	if bash "$SCRIPT_DIR/update-translations.sh" --check "$build"; then
		ok "POT, MO and JavaScript catalogs match the release source"
	else
		err "Refresh bundled translations with scripts/update-translations.sh"
	fi
	return 0
}
