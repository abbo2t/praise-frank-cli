#!/usr/bin/env bash
set -euo pipefail
# Build a standalone Linux binary using PyInstaller.
# Run this on the same OS/arch where you will run the binary.

if ! command -v pyinstaller >/dev/null 2>&1; then
  echo "PyInstaller not found — installing into the current Python environment (user)."
  python3 -m pip install --user pyinstaller
fi

# Produce one-file bundle and include the JSON animation data next to the binary
pyinstaller --onefile --add-data "fareway-frank-keir.json:." run_animation.py

echo "Build complete. Binary is in dist/ (e.g. dist/run_animation)."
