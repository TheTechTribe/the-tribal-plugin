#!/bin/bash
set -e

# Current folder is the plugin folder: /plugin/the-tech-tribe
PLUGIN_DIR="."
PLUGIN_ROOT="the-tech-tribe"
MAIN_FILE="the-tribal-plugin.php"

echo "🔍 Reading plugin version..."

# Extract version number from plugin header (matches '* Version: X.Y.Z' exactly)
VERSION=$(grep -i "^[[:space:]]*\* Version:" "$MAIN_FILE" | head -n 1 | sed -E 's/.*Version:[[:space:]]*([0-9.]+).*/\1/')

# Fail if version not found
if [ -z "$VERSION" ]; then
  echo "❌ Could not detect version from $MAIN_FILE — aborting."
  exit 1
fi

echo "📦 Building version: $VERSION"

# Output filename
OUTPUT_FILE="TTT_WordPress_Plugin.${VERSION}.zip"

# ---------------------------------------------------------------
# Composer: verify available and install production dependencies
# ---------------------------------------------------------------
if ! command -v composer &> /dev/null; then
  echo "❌ composer not found — please install Composer and try again."
  exit 1
fi

echo "🎵 Running composer install (no-dev, optimized autoloader)..."
composer install --no-dev --optimize-autoloader --quiet

# ---------------------------------------------------------------
# Clean up any old zip files before building
# ---------------------------------------------------------------
rm -f TTT_WordPress_Plugin.*.zip

# Create a temporary folder structure
TEMP_DIR="./temp-plugin-build"
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR/$PLUGIN_ROOT"

echo "📂 Copying plugin files..."

# Copy plugin files — exclude dev/build artefacts
find "$PLUGIN_DIR" -mindepth 1 -maxdepth 1 \
  ! -name "temp-plugin-build" \
  ! -name "build-zip.sh" \
  ! -name "composer.json" \
  ! -name "composer.lock" \
  ! -name ".git" \
  ! -name ".gitignore" \
  ! -name ".gitattributes" \
  ! -name ".DS_Store" \
  ! -name "node_modules" \
  ! -name "tests" \
  -exec cp -r {} "$TEMP_DIR/$PLUGIN_ROOT/" \;

# Zip with the-tech-tribe/ folder as the top-level entry
echo "🗜️  Zipping..."
cd "$TEMP_DIR"
zip -r "../$OUTPUT_FILE" "$PLUGIN_ROOT" -x "*.DS_Store" -x "*/__MACOSX/*"

# Cleanup
cd ..
rm -rf "$TEMP_DIR"

# Verify the zip was created
if [ ! -f "$OUTPUT_FILE" ]; then
  echo "❌ Build failed — zip file not found."
  exit 1
fi

echo "✅ Created $OUTPUT_FILE ($(du -sh "$OUTPUT_FILE" | cut -f1))"