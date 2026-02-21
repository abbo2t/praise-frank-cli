# praise-frank-cli
ASCII animation of Frank

## Terminal playback

Play the JSON animation in a terminal using the included CLI:

- Run once (default file `fareway-frank-keir.json`):

```bash
python run_animation.py
```

- Play a specific file and don't loop:

```bash
python run_animation.py --file fareway-frank-keir.json --no-loop
```

- Make the script executable and run directly:

```bash
chmod +x run_animation.py
./run_animation.py
```

The CLI reads frames from the JSON `frames` array and uses each frame's `duration` (milliseconds) to schedule playback.

## Building a standalone binary (PyInstaller)

You can package `run_animation.py` into a single native executable so Python is not required on the target host.



```bash
./build_pyinstaller.sh
```

This will call PyInstaller and place a single-file binary under `dist/` (e.g. `dist/run_animation`). The JSON asset `fareway-frank-keir.json` is bundled as data and will be placed next to the binary by PyInstaller.


```bash
python3 -m pip install --user pyinstaller
pyinstaller --onefile --add-data "fareway-frank-keir.json:." run_animation.py
```


```bash
./dist/run_animation --file fareway-frank-keir.json
```

Notes:

## Makefile

A `Makefile` is included with a convenience target.

- Build (invokes the included build script):

```bash
make build
```

- Clean PyInstaller artifacts:

```bash
make clean
```

## CI: GitHub Actions

There's a GitHub Actions workflow at `.github/workflows/pyinstaller-build.yml` that builds the Linux binary on pushes and pull requests to `main` and uploads the `dist/` directory as an artifact. Use the artifact from the workflow run to download the built executable.
