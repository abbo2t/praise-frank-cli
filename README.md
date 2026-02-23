# praise-frank-cli
ASCII animation of Frank

## Terminal playback

Play the JSON animation in a terminal using the included CLI. This repository now provides a PHP CLI port in the `php/` directory that replaces the original Python script.

- Run once (default file `fareway-frank-keir.json`):

```bash
php php/index.php --file fareway-frank-keir.json --no-loop
```

- Play a specific file and don't loop:

```bash
php php/index.php --file fareway-frank-keir.json --no-loop
```

- The CLI reads frames from the JSON `frames` array and uses each frame's `duration` (milliseconds) to schedule playback.

## Packaging and distribution

This project previously used PyInstaller to build a native binary for the Python CLI. The Python packaging artifacts and build scripts were removed in favor of the PHP rewrite. Recommended distribution options now:

- Use the PHP CLI directly (requires PHP 8+):

```bash
php php/index.php --file fareway-frank-keir.json
```

- Containerize with Docker for reproducible runtime across hosts:

```dockerfile
FROM php:8-cli
WORKDIR /app
COPY . .
CMD ["php", "php/index.php", "--file", "fareway-frank-keir.json"]
```

- If you still need a single native binary for distribution, consider tools that target PHP packaging (e.g., creating a small PHAR or building a lightweight Docker image). PHARs are limited for native terminal control and native packaging is generally more common for compiled languages.

## Notes

- The Python `run_animation.py` and the PyInstaller build script were intentionally removed from this branch; see the `php/` directory for the active implementation.
- If you want the Python implementation preserved, I can add it to a separate branch or a `python/` directory instead of keeping it in `main`.
