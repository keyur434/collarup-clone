# Poppler (inside TalentPulse only)

No system install. No Apache restart. PHP calls `.exe` files from this folder by full path.

## Setup on Windows server

1. Download: https://github.com/oschwartz10612/poppler-windows/releases  
   File: `Release-XX.XX.X-0.zip` (or latest).

2. Unzip on your PC. Inside the zip you want the folder that contains **`pdftotext.exe`**, **`pdfseparate.exe`**, **`pdfunite.exe`**  
   (often `Library\bin\`).

3. Either:

**A) Leave the zip layout in the project** (easiest):

```
collarup-clone/poppler-26.09.0/Library/bin/pdftotext.exe
```

TalentPulse auto-detects `poppler-*/Library/bin` — no copy, no `.env`.

**B) Copy** `.exe` + `.dll` into `collarup-clone/tools/poppler/bin/`

Example on prod (option B):

```
C:\Apache24\htdocs\collarup-clone\tools\poppler\bin\pdftotext.exe
C:\Apache24\htdocs\collarup-clone\tools\poppler\bin\pdfseparate.exe
C:\Apache24\htdocs\collarup-clone\tools\poppler\bin\pdfunite.exe
```

4. Optional in `.env` (if you use a different subfolder):

```
POPPLER_BIN_DIR=C:/Apache24/htdocs/collarup-clone/tools/poppler/bin
```

Use forward slashes or escaped backslashes; path must be readable by the Apache user.

5. Test (no Apache restart):

```bat
cd C:\Apache24\htdocs\collarup-clone
php scripts\check_poppler.php
```

You should see `[OK] Poppler bin: ...` and `[OK] pdftotext`.

## Git

Binary `.exe` / `.dll` are not committed. Each server copies them into `tools/poppler/bin/` once.

## If Poppler is missing

Bulk import still works: multiple files = one candidate each; compiled PDF = text-based split (weaker, may not attach per-person PDF).
