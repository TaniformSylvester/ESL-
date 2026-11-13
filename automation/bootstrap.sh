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

# Vector emoji artwork (Noto Color Emoji, OFL-1.1) for sharp pictures in the
# PDFs; see tlgen/vector_emoji.py. Optional: without it the bitmap emoji font is used.
EMOJI_VER=5.3.2
if [ ! -d .cache/noto-color-emoji/package/files ] && command -v npm >/dev/null; then
    mkdir -p .cache/noto-color-emoji
    (cd .cache/noto-color-emoji && npm pack -q "@fontsource/noto-color-emoji@${EMOJI_VER}" >/dev/null \
        && tar xzf "fontsource-noto-color-emoji-${EMOJI_VER}.tgz" && rm -f package/files/*.woff "fontsource-noto-color-emoji-${EMOJI_VER}.tgz") \
        || echo "Vector emoji not installed (npm unavailable); PDFs will use the bitmap emoji font."
fi

.venv/bin/python -m tlgen check
