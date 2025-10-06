#!/bin/bash

# Plugin folder name (adjust as needed)
PLUGIN_DIR="the-tribal-plugin"

# Path to main plugin file (the one with the "Version:" header)
MAIN_FILE="$PLUGIN_DIR/the-tribal-plugin.php"

# Extract version number from plugin header
VERSION=$(grep -i "Version:" "$MAIN_FILE" | head -n 1 | awk -F': ' '{print $2}' | tr -d '[:space:]')

# Fallback if version not found
if [ -z "$VERSION" ]; then
  echo "⚠️ Could not detect version from $MAIN_FILE"
  VERSION="dev"
fi

# Output filename with version
OUTPUT_FILE="${PLUGIN_DIR}-${VERSION}.zip"

# Remove old zip if exists
rm -f "$OUTPUT_FILE"

# Create zip while excluding dev-only files/folders
zip -r "$OUTPUT_FILE" "$PLUGIN_DIR" \
  -x "*/.git/*" \
  -x "*/.gitignore" \
  -x "*/.gitattributes" \
  -x "*/node_modules/*" \
  -x "*/tests/*" \
  -x "*/bin/*" \
  -x "*/.idea/*" \
  -x "*/.vscode/*" \
  -x "*/.DS_Store" \
  -x "*/Thumbs.db" \
  -x "*/composer.*" \
  -x "*/package*.json" \
  -x "*/webpack.*" \
  -x "*/phpunit.xml*" \
  -x "*/.editorconfig" \
  -x "*/.eslint*" \
  -x "*/.stylelintrc*" \
  -x "*/README.md" \
  -x "*/CHANGELOG.md"\
  -x "build-zip.sh"


echo "✅ Created $OUTPUT_FILE"