#!/bin/bash

# Current folder is the plugin folder
PLUGIN_DIR="."
MAIN_FILE="the-tribal-plugin.php"

# Extract version number from plugin header
VERSION=$(grep -i "^[[:space:]]*\*.*Version:" "$MAIN_FILE" | head -n 1 | sed -E 's/.*Version:[[:space:]]*([0-9.]+).*/\1/')

# Fallback if version not found
if [ -z "$VERSION" ]; then
  echo "⚠️ Could not detect version from $MAIN_FILE"
  VERSION="dev"
fi

# Output filename with version (one level up so zip isn’t inside itself)
OUTPUT_FILE="the-tribal-plugin-${VERSION}.zip"

# Remove old zip if exists
rm -f "$OUTPUT_FILE"

# Zip everything in the current folder, excluding dev files and this script
zip -r "$OUTPUT_FILE" . \
  -x "./.git/*" \
  -x "./.gitignore" \
  -x "./.gitattributes" \
  -x "./node_modules/*" \
  -x "./tests/*" \
  -x "./bin/*" \
  -x "./.idea/*" \
  -x "./.vscode/*" \
  -x "./.DS_Store" \
  -x "./Thumbs.db" \
  -x "./composer.*" \
  -x "./package*.json" \
  -x "./webpack.*" \
  -x "./phpunit.xml*" \
  -x "./.editorconfig" \
  -x "./.eslint*" \
  -x "./.stylelintrc*" \
  -x "./README.md" \
  -x "./CHANGELOG.md" \
  -x "./build-zip.sh"

echo "✅ Created $OUTPUT_FILE"