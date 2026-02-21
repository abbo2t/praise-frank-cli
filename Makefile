PY=python3
BUILD_SCRIPT=./build_pyinstaller.sh

.PHONY: all build clean

all: build

build:
	@echo "Building standalone binary via build_pyinstaller.sh"
	@$(BUILD_SCRIPT)

clean:
	@echo "Cleaning PyInstaller outputs"
	@rm -rf build dist __pycache__ *.spec
