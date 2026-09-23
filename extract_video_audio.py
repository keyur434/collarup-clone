#!/usr/bin/env python3
"""
Extract audio from large interview videos using ffmpeg (500 MB+ safe).

No full-file load into RAM — ffmpeg streams decode to disk.

Examples:
  python extract_video_audio.py "D:/videos/interview1.mp4"
  python extract_video_audio.py "D:/videos" --output "D:/audio_out"
  python extract_video_audio.py "D:\videos" --format wav --stt
  python extract_video_audio.py interview.mp4 --format mp3 --bitrate 192k

Requires: ffmpeg on PATH, or set FFMPEG_PATH / --ffmpeg
"""

from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path

VIDEO_EXT = {".mp4", ".mov", ".mkv", ".webm", ".avi", ".m4v", ".wmv", ".flv", ".mpeg", ".mpg"}

# Telephony cleanup (optional)
STT_AUDIO_FILTERS = (
    "highpass=f=200,lowpass=f=3400,"
    "afftdn=nf=-20,"
    "acompressor=threshold=-18dB:ratio=3:attack=5:release=50,"
    "loudnorm=I=-16:TP=-1.5:LRA=11"
)


def resolve_ffmpeg(explicit: str | None) -> str:
    for candidate in (explicit, os.environ.get("FFMPEG_PATH"), "ffmpeg"):
        if not candidate:
            continue
        if os.path.isfile(candidate):
            return candidate
        found = shutil.which(candidate)
        if found:
            return found
    raise SystemExit(
        "ffmpeg not found. Install ffmpeg and add to PATH, or pass --ffmpeg C:\\path\\to\\ffmpeg.exe"
    )


def ffprobe_duration(ffmpeg: str, path: Path) -> float:
    ffprobe = Path(ffmpeg).with_name("ffprobe.exe" if ffmpeg.lower().endswith(".exe") else "ffprobe")
    if not ffprobe.is_file():
        ffprobe_name = shutil.which("ffprobe")
        if not ffprobe_name:
            return 0.0
        ffprobe = Path(ffprobe_name)

    cmd = [
        str(ffprobe),
        "-v", "error",
        "-show_entries", "format=duration",
        "-of", "json",
        str(path),
    ]
    try:
        out = subprocess.check_output(cmd, stderr=subprocess.DEVNULL, text=True)
        data = json.loads(out)
        return float(data.get("format", {}).get("duration", 0) or 0)
    except Exception:
        return 0.0


def parse_ffmpeg_time(line: str) -> float | None:
    m = re.search(r"time=(\d+):(\d+):(\d+(?:\.\d+)?)", line)
    if not m:
        return None
    h, mi, s = m.groups()
    return int(h) * 3600 + int(mi) * 60 + float(s)


def collect_inputs(path: Path, recursive: bool) -> list[Path]:
    if path.is_file():
        return [path] if path.suffix.lower() in VIDEO_EXT else []
    if not path.is_dir():
        raise SystemExit(f"Not found: {path}")

    pattern = "**/*" if recursive else "*"
    files = []
    for p in path.glob(pattern):
        if p.is_file() and p.suffix.lower() in VIDEO_EXT:
            files.append(p)
    return sorted(files)


def output_path_for(video: Path, out_dir: Path, fmt: str) -> Path:
    return out_dir / f"{video.stem}.{fmt}"


def build_ffmpeg_cmd(
    ffmpeg: str,
    video: Path,
    dest: Path,
    fmt: str,
    stt: bool,
    bitrate: str,
    audio_filters: str | None,
) -> list[str]:
    cmd = [ffmpeg, "-hide_banner", "-nostats", "-y", "-i", str(video), "-vn"]

    if audio_filters:
        cmd.extend(["-af", audio_filters])

    if fmt == "wav":
        cmd.extend(["-ac", "1", "-ar", "16000", "-c:a", "pcm_s16le"])
    elif fmt == "mp3":
        cmd.extend(["-acodec", "libmp3lame"])
        if bitrate:
            cmd.extend(["-b:a", bitrate])
        else:
            cmd.extend(["-q:a", "2"])
    elif fmt == "m4a":
        cmd.extend(["-acodec", "aac", "-b:a", bitrate or "128k"])
    else:
        raise SystemExit(f"Unsupported format: {fmt}")

    cmd.append(str(dest))
    return cmd


def extract_one(
    ffmpeg: str,
    video: Path,
    dest: Path,
    fmt: str,
    stt: bool,
    bitrate: str,
    skip_existing: bool,
    audio_filters: str | None,
) -> bool:
    if skip_existing and dest.exists():
        if dest.stat().st_mtime >= video.stat().st_mtime and dest.stat().st_size > 0:
            print(f"  skip (up to date): {dest.name}")
            return True

    dest.parent.mkdir(parents=True, exist_ok=True)
    size_mb = video.stat().st_size / (1024 * 1024)
    duration = ffprobe_duration(ffmpeg, video)
    dur_note = f", {duration:.0f}s" if duration else ""
    print(f"\n→ {video.name} ({size_mb:.1f} MB{dur_note})")
    print(f"  out: {dest}")

    cmd = build_ffmpeg_cmd(ffmpeg, video, dest, fmt, stt, bitrate, audio_filters)
    started = time.time()

    proc = subprocess.Popen(
        cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        bufsize=1,
        universal_newlines=True,
    )

    last_pct = -1
    assert proc.stderr is not None
    for line in proc.stderr:
        line = line.rstrip()
        if duration > 0:
            t = parse_ffmpeg_time(line)
            if t is not None:
                pct = min(100, int(t / duration * 100))
                if pct >= last_pct + 5:
                    elapsed = time.time() - started
                    print(f"  progress: {pct}% ({elapsed:.0f}s elapsed)")
                    last_pct = pct
        if "Error" in line or "error" in line.lower():
            if "deprecated" not in line.lower():
                print(f"  ffmpeg: {line}")

    code = proc.wait()
    elapsed = time.time() - started

    if code != 0 or not dest.exists() or dest.stat().st_size == 0:
        print(f"  FAILED (exit {code})")
        return False

    out_mb = dest.stat().st_size / (1024 * 1024)
    print(f"  OK in {elapsed:.1f}s → {out_mb:.1f} MB")
    return True


def main() -> int:
    parser = argparse.ArgumentParser(description="Extract audio from large videos via ffmpeg")
    parser.add_argument("input", help="Video file or folder containing videos")
    parser.add_argument(
        "-o", "--output",
        help="Output folder (default: <input>/audio_out or ./audio_out for single file)",
    )
    parser.add_argument(
        "-f", "--format",
        choices=["mp3", "wav", "m4a"],
        default="mp3",
        help="Output audio format (default: mp3)",
    )
    parser.add_argument(
        "--stt",
        action="store_true",
        help="STT mode: 16 kHz mono WAV + telephony filters (overrides --format to wav)",
    )
    parser.add_argument("--bitrate", default="", help="MP3/M4A bitrate e.g. 192k")
    parser.add_argument("--ffmpeg", default=None, help="Path to ffmpeg executable")
    parser.add_argument("-r", "--recursive", action="store_true", help="Scan subfolders")
    parser.add_argument("--no-skip", action="store_true", help="Re-encode even if output exists")
    parser.add_argument(
        "--filters",
        default="",
        help="Custom ffmpeg -af filter chain (empty = none unless --stt)",
    )
    args = parser.parse_args()

    ffmpeg = resolve_ffmpeg(args.ffmpeg)
    print(f"Using ffmpeg: {ffmpeg}")

    inp = Path(args.input).expanduser().resolve()
    videos = collect_inputs(inp, args.recursive)
    if not videos:
        print(f"No video files found under {inp}")
        return 1

    if args.output:
        out_dir = Path(args.output).expanduser().resolve()
    elif inp.is_file():
        out_dir = inp.parent / "audio_out"
    else:
        out_dir = inp / "audio_out"

    fmt = "wav" if args.stt else args.format
    filters = args.filters or (STT_AUDIO_FILTERS if args.stt else None)

    print(f"Found {len(videos)} video(s). Output: {out_dir} ({fmt})")

    ok = 0
    fail = 0
    for video in videos:
        dest = output_path_for(video, out_dir, fmt)
        if extract_one(
            ffmpeg,
            video,
            dest,
            fmt,
            args.stt,
            args.bitrate,
            skip_existing=not args.no_skip,
            audio_filters=filters,
        ):
            ok += 1
        else:
            fail += 1

    print(f"\nDone: {ok} ok, {fail} failed")
    return 0 if fail == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
