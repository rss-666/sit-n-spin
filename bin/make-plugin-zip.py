#!/usr/bin/env python3
"""Create a deterministic, WordPress-compatible plugin ZIP.

Entries use the DOS ZIP origin and omit Unix UID/GID and extended timestamp
fields that have triggered ZipArchive consistency errors on some WordPress hosts.
"""

from __future__ import annotations

import argparse
import os
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


def dos_datetime(timestamp: float) -> tuple[int, int, int, int, int, int]:
    value = list(__import__("time").localtime(timestamp)[:6])
    value[0] = max(1980, min(2107, value[0]))
    value[5] -= value[5] % 2
    return tuple(value)  # type: ignore[return-value]


def build(plugin_dir: Path, archive: Path) -> None:
    if not plugin_dir.is_dir():
        raise SystemExit(f"Plugin directory does not exist: {plugin_dir}")

    excluded_names = {".DS_Store"}
    archive.unlink(missing_ok=True)

    with ZipFile(archive, "w", compression=ZIP_DEFLATED, compresslevel=9) as output:
        for source in sorted(plugin_dir.rglob("*")):
            if not source.is_file() or source.name in excluded_names:
                continue
            if source.suffix.lower() in {".log", ".tmp"}:
                continue

            relative = source.relative_to(plugin_dir.parent).as_posix()
            info = ZipInfo(relative, dos_datetime(source.stat().st_mtime))
            info.create_system = 0  # MS-DOS/FAT, avoiding Unix extra metadata.
            info.compress_type = ZIP_DEFLATED
            info.external_attr = 0
            info.internal_attr = 0
            info.extra = b""
            info.comment = b""
            output.writestr(info, source.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)

    with ZipFile(archive, "r") as check:
        bad = check.testzip()
        if bad:
            raise SystemExit(f"Archive verification failed at {bad}")
        names = check.namelist()
        expected_prefix = plugin_dir.name + "/"
        if not names or any(not name.startswith(expected_prefix) for name in names):
            raise SystemExit("Archive does not contain exactly one top-level plugin directory")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("plugin_dir", type=Path)
    parser.add_argument("archive", type=Path)
    args = parser.parse_args()
    build(args.plugin_dir.resolve(), args.archive.resolve())
    print(f"Created {args.archive.resolve()}")


if __name__ == "__main__":
    main()
