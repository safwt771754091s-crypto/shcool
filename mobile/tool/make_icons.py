#!/usr/bin/env python3
"""Generate the installable-app (PWA) icon set for the Flutter web build.

Draws the platform mark — a white graduation cap on the brand green — at a
high resolution and downsamples it, then writes every size the manifest and
iOS need:

  icons/Icon-192.png, icons/Icon-512.png            standard (rounded square)
  icons/Icon-maskable-192.png, ...-512.png          maskable (full-bleed, safe zone)
  icons/apple-touch-icon-180.png                     iOS home screen
  favicon.png                                        browser tab

Run: python3 tool/make_icons.py   (from mobile/)
"""
import os
from PIL import Image, ImageDraw

GREEN = (47, 106, 67)      # #2f6a43 brand primary
GREEN_LIGHT = (63, 138, 87)  # #3f8a57
GOLD = (212, 175, 55)
WHITE = (255, 255, 255)

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "web", "icons")
FAVICON = os.path.join(HERE, "..", "web", "favicon.png")

SS = 1024  # supersample canvas


def gradient(size, top, bottom):
    img = Image.new("RGB", (size, size), top)
    d = ImageDraw.Draw(img)
    for y in range(size):
        t = y / (size - 1)
        color = tuple(round(top[i] + (bottom[i] - top[i]) * t) for i in range(3))
        d.line([(0, y), (size, y)], fill=color)
    return img


def draw_cap(img, cx, cy, span):
    """Draw a graduation cap centred at (cx, cy); `span` is the board width."""
    d = ImageDraw.Draw(img, "RGBA")
    half = span / 2

    # Mortarboard: a flattened diamond.
    board_h = span * 0.62
    d.polygon(
        [(cx, cy - board_h / 2), (cx + half, cy), (cx, cy + board_h / 2), (cx - half, cy)],
        fill=WHITE,
    )

    # Cap base (the head part) peeking below the board.
    bw, bh = span * 0.5, span * 0.30
    d.rounded_rectangle(
        [cx - bw / 2, cy + board_h * 0.18, cx + bw / 2, cy + board_h * 0.18 + bh],
        radius=bh * 0.35,
        fill=WHITE,
    )

    # Tassel: a thin line off the right corner ending in a gold bead.
    tx, ty = cx + half * 0.92, cy + board_h * 0.02
    d.line([(tx, ty), (tx, ty + span * 0.30)], fill=GOLD, width=max(2, int(span * 0.022)))
    r = span * 0.045
    d.ellipse([tx - r, ty + span * 0.30 - r, tx + r, ty + span * 0.30 + r], fill=GOLD)


def standard_icon(size):
    """Rounded square with a small margin — used for the browser/desktop icon."""
    img = gradient(SS, GREEN_LIGHT, GREEN).convert("RGBA")
    draw_cap(img, SS / 2, SS / 2, SS * 0.56)
    # Rounded mask (Android/desktop apply their own, but this looks right in tabs).
    mask = Image.new("L", (SS, SS), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, SS - 1, SS - 1], radius=SS * 0.22, fill=255)
    img.putalpha(mask)
    return img.resize((size, size), Image.LANCZOS)


def maskable_icon(size):
    """Full-bleed square; art kept inside the ~80% safe zone so any mask crops it well."""
    img = gradient(SS, GREEN_LIGHT, GREEN).convert("RGBA")
    draw_cap(img, SS / 2, SS / 2, SS * 0.44)
    return img.resize((size, size), Image.LANCZOS)


def apple_icon(size):
    """iOS: no transparency, no rounding (iOS masks it itself)."""
    img = gradient(SS, GREEN_LIGHT, GREEN).convert("RGB")
    draw_cap(img, SS / 2, SS / 2, SS * 0.54)
    return img.resize((size, size), Image.LANCZOS)


def main():
    os.makedirs(OUT, exist_ok=True)
    outputs = {
        "Icon-192.png": standard_icon(192),
        "Icon-512.png": standard_icon(512),
        "Icon-maskable-192.png": maskable_icon(192),
        "Icon-maskable-512.png": maskable_icon(512),
        "apple-touch-icon-180.png": apple_icon(180),
    }
    for name, img in outputs.items():
        path = os.path.join(OUT, name)
        img.save(path)
        print("wrote", os.path.relpath(path))

    fav = apple_icon(32)
    fav.save(FAVICON)
    print("wrote", os.path.relpath(FAVICON))


if __name__ == "__main__":
    main()
