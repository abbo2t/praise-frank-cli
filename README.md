# praise-frank-cli
ASCII animation of Frank

![praise-frank preview](https://github.com/user-attachments/assets/681997b7-99da-4114-b625-f0f770c7ced2)

## Usage

Requires Python 3 (no additional dependencies).

```bash
./praise-frank          # loop forever (Ctrl+C to exit)
./praise-frank --once   # play once and exit
```

## Files

- **`praise-frank`** — executable Python 3 script; plays the animation
- **`animation.json`** — animation data (frames, colors, timing); edit this to customise the animation

## Animation format (`animation.json`)

```json
{
  "version": "1",
  "width": 56,
  "height": 13,
  "loop_start": 8,
  "frames": [
    {
      "duration": 100,
      "lines": [
        "",
        [{"text": "hello", "fg": "bright_yellow", "bold": true}]
      ]
    }
  ]
}
```

Each frame's `lines` array contains either plain strings or arrays of **spans**. Each span supports:

| Field  | Type    | Description                                      |
|--------|---------|--------------------------------------------------|
| `text` | string  | The text to display                              |
| `fg`   | string  | Foreground color (see supported colors below)    |
| `bold` | boolean | Whether to render in bold                        |

**Supported colors:** `black`, `red`, `green`, `yellow`, `blue`, `magenta`, `cyan`, `white`, and their `bright_` variants (e.g. `bright_cyan`).

`loop_start` specifies which frame index the animation loops back to after the first full play-through (default `0`).
