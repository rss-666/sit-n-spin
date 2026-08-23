#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="plague-dr-suno-publisher"
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/$PLUGIN/$PLUGIN.php" | head -1 | tr -d '\r')"
ARCHIVE="$ROOT/${PLUGIN}-${VERSION}.zip"

if command -v php >/dev/null 2>&1; then
    while IFS= read -r -d '' file; do
        php -l "$file" >/dev/null
    done < <(find "$ROOT/$PLUGIN" -type f -name '*.php' -print0)
fi

python3 "$ROOT/bin/make-plugin-zip.py" "$ROOT/$PLUGIN" "$ARCHIVE"
