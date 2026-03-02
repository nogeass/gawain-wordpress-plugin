# WordPress.org SVN Release Guide

## URLs

- **SVN repo**: `https://plugins.svn.wordpress.org/gawain-ai-video`
- **Plugin page**: https://wordpress.org/plugins/gawain-ai-video

## Preconditions

- WordPress.org SVN commit access has been granted (email confirmation after plugin approval)
- SVN username: `nogeass` (case-sensitive)
- SVN password: set at https://login.wordpress.org/ — this is your WordPress.org account password

## Initial Release (0.1.0)

### 1. Checkout the SVN repo

```bash
svn checkout https://plugins.svn.wordpress.org/gawain-ai-video gawain-svn
cd gawain-svn
```

This creates `trunk/`, `tags/`, `branches/`, and `assets/` directories.

### 2. Copy release files into trunk/

Build the ZIP first, then extract its contents **directly** into `trunk/`:

```bash
# Build
./scripts/build-zip.sh

# Clear trunk and copy
rm -rf trunk/*
cp -R /path/to/extracted-zip-contents/* trunk/
```

Verify the main plugin file is at `trunk/gawain-ai-video.php` — not nested inside a subfolder.

### 3. Stage and commit trunk

```bash
cd trunk
svn add --force .
svn status | grep '^!' | awk '{print $2}' | xargs -r svn delete
cd ..
svn commit -m "Initial release 0.1.0" --username nogeass
```

### 4. Tag the release

```bash
svn copy trunk tags/0.1.0
svn commit -m "Tag 0.1.0" --username nogeass
```

### 5. Verify readme.txt Stable tag

In `trunk/readme.txt` and `tags/0.1.0/readme.txt`, confirm:

```
Stable tag: 0.1.0
```

This value tells WordPress.org which tag to serve. A mismatch means the plugin page won't update.

## Assets (Icons & Banners)

Place these in the SVN `assets/` directory (top-level, **not** inside `trunk/`):

| File | Size | Purpose |
|------|------|---------|
| `assets/icon-256x256.png` | 256x256 px | Plugin icon (search results, directory) |
| `assets/banner-1544x500.png` | 1544x500 px | Plugin page header banner |

```bash
cp /path/to/icon-256x256.png assets/
cp /path/to/banner-1544x500.png assets/
svn add assets/*
svn commit -m "Add plugin icon and banner" --username nogeass
```

Assets update immediately — no new tag required.

## Troubleshooting

### 403 Forbidden on commit

- Commit access not yet granted — check for the approval email from `plugins@wordpress.org`
- Wrong credentials — username is `nogeass` (lowercase, no email)
- Password is your WordPress.org account password, not a separate SVN password

### Plugin page shows wrong version

- `Stable tag` in `trunk/readme.txt` doesn't match the tag directory name
- The tag directory must exist under `tags/` (e.g., `tags/0.1.0`)

### Extra top-level folder inside trunk

Wrong:
```
trunk/
  gawain-ai-video/      <-- extra folder
    gawain-ai-video.php
```

Correct:
```
trunk/
  gawain-ai-video.php   <-- directly in trunk
  includes/
  assets/
  readme.txt
```

If you extracted the ZIP directly into `trunk/` without removing the wrapper directory, you'll get the nested structure. Always verify with `ls trunk/` before committing.
