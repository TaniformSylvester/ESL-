#!/usr/bin/env bash
# Sets up the TeachLuma resource generator in a fresh Claude Code cloud session.
# Usage (from the repo root):  bash automation/bootstrap.sh
set -euo pipefail
cd "$(dirname "$0")"

need_apt=()
command -v soffice  >/dev/null || need_apt+=(libreoffice-writer libreoffice-impress)
command -v pdftoppm >/dev/null || need_apt+=(poppler-utils)
fc-list | grep -qi "Noto Color Emoji" || need_apt+=(fonts-noto-color-emoji)
if [ ${#need_apt[@]} -gt 0 ]; then
    echo "Installing: ${need_apt[*]}"
    (apt-get update -qq && apt-get install -y -qq "${need_apt[@]}") >/dev/null
fi

if [ ! -x .venv/bin/python ]; then
    python3 -m venv .venv
fi
.venv/bin/pip install -q -r requirements.txt

.venv/bin/python -m tlgen check
