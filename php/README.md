# PHP rewrite

Fully functional PHP port of `run_animation.py`. It reads the same animation JSON and
renders it in the terminal with optional color support.

## Requirements

- PHP 8.0+ with the `pcntl` extension (optional, used for graceful SIGTERM handling).
- Composer (optional). A simple PSR-4 autoloader is bundled, so Composer is only needed if
	you later add dependencies and want `vendor/autoload.php`.

## Quick start

Run directly from the repo root:

```bash
php php/index.php --file fareway-frank-keir.json --no-loop --no-color
```

Available flags mirror the Python CLI:

- `-f, --file`: path to the animation JSON (default `fareway-frank-keir.json`).
- `--no-loop`: play once and exit.
- `--fps <value>`: override FPS for frames that lack `duration`.
- `--no-color`: disable color sequences.
- `--force-truecolor` / `--force-256`: force a color mode.
- `--prefer-flicker`: clear the screen each frame if you prefer less stutter over flicker.

## Composer (optional)

If you prefer Composer’s autoloader:

```bash
cd php
composer install
```

The CLI still works without this step thanks to the inline fallback autoloader.
