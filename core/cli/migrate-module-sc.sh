#!/bin/bash
#
# migrate-module-sc.sh
#
# Removes [#module ...#] shortcodes and load_module() calls from an ext repo.
# These are no-ops since core auto-discovers all modules via find_path().
#
# Usage:
#   bash core/cli/migrate-module-sc.sh            # runs on ./ext
#   bash core/cli/migrate-module-sc.sh /path/ext  # runs on given path

EXT="${1:-./ext}"

if [ ! -d "$EXT" ]; then
    echo "Error: directory not found: $EXT"
    exit 1
fi

echo "Removing [#module ...#] and load_module() from: $EXT"

# Remove lines containing [#module ...#] from templates
find "$EXT" -name "*.tpl" -o -name "*.inc" \
    | grep -v "_dep_" | grep -v "_old_" \
    | xargs sed -i '/\[#module /d'

# Remove load_module() calls from PHP files
find "$EXT" -name "*.php" -o -name "*.inc" \
    | grep -v "_dep_" | grep -v "_old_" \
    | xargs sed -i '/load_module(/d'

# Report any remaining occurrences (should be none)
REMAINING=$(grep -rn "\[#module \|load_module(" "$EXT" \
    --include="*.tpl" --include="*.php" --include="*.inc" \
    | grep -v "_dep_" | grep -v "_old_" | wc -l)

if [ "$REMAINING" -gt 0 ]; then
    echo "Warning: $REMAINING occurrence(s) could not be removed automatically:"
    grep -rn "\[#module \|load_module(" "$EXT" \
        --include="*.tpl" --include="*.php" --include="*.inc" \
        | grep -v "_dep_" | grep -v "_old_"
else
    echo "Done. No remaining occurrences."
fi
