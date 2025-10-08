#!/bin/bash

# Current folder is the plugin folder: /plugin/the-tech-tribe
PLUGIN_DIR="."
PLUGIN_ROOT="the-tech-tribe"
MAIN_FILE="the-tribal-plugin.php"

# Extract version number from plugin header
VERSION=$(grep -i "^[[:space:]]*\*.*Version:" "$MAIN_FILE" | head -n 1 | sed -E 's/.*Version:[[:space:]]*([0-9.]+).*/\1/')

# Fallback if version not found
if [ -z "$VERSION" ]; then
  echo "⚠️ Could not detect version from $MAIN_FILE"
  VERSION="dev"
fi

# Output filename inside current folder
OUTPUT_FILE="TTT_WordPress_Plugin.${VERSION}.zip"

# Remove old zip if exists
rm -f "$OUTPUT_FILE"

# Create a temporary folder structure
TEMP_DIR="./temp-plugin-build"
mkdir -p "$TEMP_DIR/$PLUGIN_ROOT"

# Copy plugin files into the root folder inside temp
find "$PLUGIN_DIR" -mindepth 1 -maxdepth 1 ! -name "temp-plugin-build" ! -name "$OUTPUT_FILE" \
  ! -name "build-zip.sh" \
  ! -name ".git" \
  ! -name "node_modules" \
  -exec cp -r {} "$TEMP_DIR/$PLUGIN_ROOT/" \;

# Zip the folder
cd "$TEMP_DIR"
zip -r "../$OUTPUT_FILE" "$PLUGIN_ROOT"

# Cleanup
cd ..
rm -rf "$TEMP_DIR"

echo "✅ Created $OUTPUT_FILE"