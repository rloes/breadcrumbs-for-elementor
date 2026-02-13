#!/bin/bash

# Define plugin directory and output ZIP file
PLUGIN_DIR="."
ZIP_FILE="breadcrumbs-for-elementor.zip"

# Remove existing ZIP file if it exists
rm -f $ZIP_FILE

# Zip the plugin while excluding specific files
zip -r $ZIP_FILE $PLUGIN_DIR \
    -x "$PLUGIN_DIR/node_modules/*" \
    -x "$PLUGIN_DIR/.git/*" \
    -x "$PLUGIN_DIR/.idea/*" \
    -x "$PLUGIN_DIR/.gitignore" \
    -x "$PLUGIN_DIR/*.iml" \
    -x "$PLUGIN_DIR/.DS_Store" \
    -x "$PLUGIN_DIR/zip.sh" \
    -x "$PLUGIN_DIR/includes/test.php" \
    -x "$PLUGIN_DIR/*.log" \
    -x "$PLUGIN_DIR/vendor/**/.git/*" \
    -x "$PLUGIN_DIR/vendor/**/.github/*" \
    -x "$PLUGIN_DIR/vendor/**/.vscode/*" \
    -x "$PLUGIN_DIR/vendor/**/tests/*" \
    -x "$PLUGIN_DIR/vendor/**/test/*" \
    -x "$PLUGIN_DIR/vendor/**/Tests/*" \
    -x "$PLUGIN_DIR/vendor/**/docs/*" \
    -x "$PLUGIN_DIR/vendor/**/examples/*" \
    -x "$PLUGIN_DIR/vendor/**/demo/*" \
    -x "$PLUGIN_DIR/vendor/**/bin/*"

# Check if the zip was created successfully
if [ -f "$ZIP_FILE" ]; then
    echo "✅ Plugin successfully zipped: $ZIP_FILE"
else
    echo "❌ Error: ZIP creation failed!"
fi
