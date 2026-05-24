import re
import glob
from pathlib import Path

# Find files containing 'image-rendering' and update img tags:
pattern_has_class = re.compile(r'(<img\b[^>]*?)class\s*=\s*"([^"]*)"([^>]*?)style\s*=\s*"([^"]*image-rendering\s*:\s*pixelated;?[^"]*)"', re.IGNORECASE)
pattern_no_class = re.compile(r'(<img\b[^>]*?)style\s*=\s*"([^"]*image-rendering\s*:\s*pixelated;?[^"]*)"([^>]*?>)', re.IGNORECASE)

files = []
# Search common extensions and .body/.txt fragments
for ext in ['html','php','body','txt']:
    files.extend(glob.glob(f"**/*.{ext}", recursive=True))

files = [f for f in files if 'vendor' not in f and 'node_modules' not in f]
updated = []
for fp in files:
    p = Path(fp)
    try:
        text = p.read_text(encoding='utf-8')
    except Exception:
        continue
    if 'image-rendering' not in text.lower():
        continue
    new = text
    # Case: tag already has class attribute
    new = pattern_has_class.sub(lambda m: f"{m.group(1)}class=\"{m.group(2).strip()} pixelated\"{m.group(3)}", new)
    # Case: tag has no class attribute
    new = pattern_no_class.sub(lambda m: f"{m.group(1)} class=\"pixelated\"{m.group(3)}", new)
    if new != text:
        bak = p.with_suffix(p.suffix + '.bak')
        p.write_text(new, encoding='utf-8')
        try:
            bak.write_text(text, encoding='utf-8')
        except Exception:
            pass
        updated.append(fp)

print(f"Processed {len(updated)} files")
for u in updated:
    print(u)
