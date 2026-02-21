#!/usr/bin/env python3
"""Terminal CLI to play an ASCII motion animation stored in the JSON export.

Usage examples:
  python run_animation.py                     # uses fareway-frank-keir.json
  python run_animation.py --file my.json --no-loop
  python run_animation.py --file fareway-frank-keir.json --fps 24
"""
from __future__ import annotations

import argparse
import json
import signal
import sys
import os
import time
from pathlib import Path
from typing import List
 



def load_frames_from_json(path: Path) -> List[dict]:
    with path.open("r", encoding="utf-8") as f:
        data = json.load(f)

    frames = data.get("frames") or []
    metadata = data.get("metadata") or {}
    canvas = data.get("canvas") or {}
    return frames, metadata, canvas


def frame_to_text(frame: dict) -> str:
    if not frame:
        return ""
    # Prefer pre-joined string when available
    s = frame.get("contentString")
    if s:
        return s.rstrip("\n")
    content = frame.get("content")
    if isinstance(content, list):
        return "\n".join(line.rstrip('\n') for line in content)
    # fallback to an empty frame
    return ""


def hex_to_rgb(hex_color: str) -> tuple[int, int, int]:
    hex_color = hex_color.lstrip('#')
    if len(hex_color) == 3:
        hex_color = ''.join(ch*2 for ch in hex_color)
    r = int(hex_color[0:2], 16)
    g = int(hex_color[2:4], 16)
    b = int(hex_color[4:6], 16)
    return r, g, b


def rgb_to_ansi256(r: int, g: int, b: int) -> int:
    # Convert 24-bit RGB to the nearest xterm-256 color index
    # Grayscale approximation
    if r == g == b:
        if r < 8:
            return 16
        if r > 248:
            return 231
        return 232 + int(round((r - 8) / 247 * 24))
    # Color cube approximation
    ri = int(round(r / 255 * 5))
    gi = int(round(g / 255 * 5))
    bi = int(round(b / 255 * 5))
    return 16 + 36 * ri + 6 * gi + bi


def supports_truecolor() -> bool:
    val = (os.environ.get('COLORTERM') or '').lower()
    if val in ('truecolor', '24bit'):
        return True
    return False


def supports_256color() -> bool:
    term = (os.environ.get('TERM') or '').lower()
    return '256color' in term


def render_frame_with_colors(frame: dict, width: int | None = None, color_mode: str = 'truecolor') -> str:
    text = frame_to_text(frame)
    lines = text.splitlines()
    if width is None:
        width = max((len(l) for l in lines), default=0)

    # Build color map: keys in JSON are "col,row"
    color_map = {}
    colors = frame.get('colors') or {}
    fg = colors.get('foreground')
    if isinstance(fg, str):
        try:
            parsed = json.loads(fg)
            for key, val in parsed.items():
                try:
                    col_s, row_s = key.split(',')
                    col = int(col_s)
                    row = int(row_s)
                    color_map[(row, col)] = val
                except Exception:
                    continue
        except Exception:
            # not a JSON map — ignore
            pass

    out_lines = []
    for row_idx in range(len(lines)):
        line = lines[row_idx]
        # pad to width to keep alignment
        if width and len(line) < width:
            line = line + ' ' * (width - len(line))

        out = []
        prev_color = None
        for col_idx, ch in enumerate(line):
            color = color_map.get((row_idx, col_idx))
            if color:
                try:
                    r, g, b = hex_to_rgb(color)
                except Exception:
                    r = g = b = None
            else:
                r = g = b = None

            if color_mode == 'truecolor':
                if (r, g, b) != prev_color:
                    if prev_color is not None:
                        out.append('\x1b[0m')
                    if r is not None:
                        out.append(f'\x1b[38;2;{r};{g};{b}m')
                    prev_color = (r, g, b)
            elif color_mode == '256':
                code = None
                if r is not None:
                    try:
                        code = rgb_to_ansi256(r, g, b)
                    except Exception:
                        code = None
                if code != prev_color:
                    if prev_color is not None:
                        out.append('\x1b[0m')
                    if code is not None:
                        out.append(f'\x1b[38;5;{code}m')
                    prev_color = code
            # else: no color
            out.append(ch)
        if prev_color is not None:
            out.append('\x1b[0m')
        out_lines.append(''.join(out))

    return '\n'.join(out_lines)


def hide_cursor():
    sys.stdout.write("\x1b[?25l")


def show_cursor():
    sys.stdout.write("\x1b[?25h")
    sys.stdout.flush()


def clear_screen():
    # Clear and home
    sys.stdout.write("\x1b[2J\x1b[H")


def move_cursor_home():
    sys.stdout.write("\x1b[H")


def enable_alternate_buffer():
    sys.stdout.write("\x1b[?1049h")


def disable_alternate_buffer():
    sys.stdout.write("\x1b[?1049l")


def play_animation(frames: List[dict], *, loop: bool = True, fps: float | None = None, canvas_width: int | None = None, enable_color: bool = True, forced_color_mode: str | None = None, prefer_flicker: bool = False):
    if not frames:
        print("No frames found in animation.")
        return

    frame_count = len(frames)
    idx = 0

    # Precompute texts and durations (with optional colors)
    # Choose color rendering mode (allow forced override)
    color_mode = 'none'
    if enable_color:
        if forced_color_mode in ('truecolor', '256'):
            color_mode = forced_color_mode
        else:
            if supports_truecolor():
                color_mode = 'truecolor'
            elif supports_256color():
                color_mode = '256'
            else:
                color_mode = 'none'

    if color_mode != 'none':
        texts = [render_frame_with_colors(f, width=canvas_width, color_mode=color_mode) for f in frames]
    else:
        texts = [frame_to_text(f) for f in frames]

    # Pre-encode frames to bytes to avoid per-frame encoding overhead
    encoded_frames = [t.encode('utf-8') for t in texts]
    durations = []
    for f in frames:
        d = f.get("duration")
        if d is None:
            d = 1000.0 / (fps or 24.0)
        durations.append((d / 1000.0) if d is not None else (1.0 / (fps or 24.0)))

    last_time = time.perf_counter()
    hide_cursor()
    using_alt = False
    try:
        if not prefer_flicker:
            # switch to alternate buffer to reduce visible flicker
            enable_alternate_buffer()
            using_alt = True
            # initial full clear
            clear_screen()

        fd = sys.stdout.fileno()

        while True:
            # choose write strategy: prefer_flicker -> clear each frame; else in-place overwrite (may flicker less)
            if prefer_flicker:
                # low-level clear + write to reduce Python overhead
                out = b"\x1b[2J\x1b[H" + encoded_frames[idx]
                os.write(fd, out)
            else:
                # move to home and write (no extra clear)
                os.write(fd, b"\x1b[H" + encoded_frames[idx])

            wait = durations[idx]
            # Sleep while still responsive to SIGINT
            end = time.perf_counter() + wait
            while True:
                now = time.perf_counter()
                if now >= end:
                    break
                time.sleep(min(0.01, end - now))

            idx += 1
            if idx >= frame_count:
                if loop:
                    idx = 0
                else:
                    break

    except KeyboardInterrupt:
        pass
    finally:
        # restore
        show_cursor()
        try:
            disable_alternate_buffer()
        except Exception:
            pass


def main(argv: List[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Play an ASCII animation JSON in the terminal.")
    parser.add_argument("--file", "-f", default="fareway-frank-keir.json", help="Path to animation JSON file")
    parser.add_argument("--no-loop", dest="loop", action="store_false", help="Play once then exit")
    parser.add_argument("--fps", type=float, default=None, help="Override FPS (frame durations may still be used)")
    parser.add_argument("--no-color", dest="color", action="store_false", help="Disable color output in terminal")
    parser.add_argument("--force-truecolor", dest="force_truecolor", action="store_true", help="Force truecolor output (may not be supported)")
    parser.add_argument("--force-256", dest="force_256", action="store_true", help="Force 256-color output (may not be supported)")
    parser.add_argument("--prefer-flicker", dest="prefer_flicker", action="store_true", help="Prefer flicker (clear each frame) to reduce stutter")
    args = parser.parse_args(argv)

    path = Path(args.file)
    if not path.exists():
        print(f"Animation file not found: {path}")
        return 2

    frames, metadata, canvas = load_frames_from_json(path)

    # If fps override provided, adjust durations if frames lack duration
    if args.fps:
        for f in frames:
            if f.get("duration") is None:
                f["duration"] = 1000.0 / args.fps

    # Ensure graceful exit on SIGTERM
    def _term_handler(signum, frame):
        show_cursor()
        sys.exit(0)

    signal.signal(signal.SIGTERM, _term_handler)

    try:
        canvas_w = canvas.get('width') if isinstance(canvas, dict) else None
        # Determine forced color mode if requested
        if args.force_truecolor:
            mode = 'truecolor'
        elif args.force_256:
            mode = '256'
        else:
            mode = None

        # If forced mode provided, pass it through to the player
        if mode is not None:
            play_animation(frames, loop=args.loop, fps=args.fps, canvas_width=canvas_w, enable_color=bool(args.color), forced_color_mode=mode, prefer_flicker=bool(args.prefer_flicker))
        else:
            play_animation(frames, loop=args.loop, fps=args.fps, canvas_width=canvas_w, enable_color=bool(args.color), prefer_flicker=bool(args.prefer_flicker))
    except Exception as exc:
        show_cursor()
        raise

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
