# Test loop — camping-sopalmo-preview

This repo is a static preview site. There is no build step and no backend.

## Important: `main` is the only published branch

The legacy GitHub Pages setup serves the `main` branch only.
Feature branches get **no preview URL**. Review happens locally;
merging to `main` publishes, usually in under a minute.

## Pre-merge loop (run every time)

1. Serve the site locally from the repo root:

   ```bash
   python3 -m http.server 8000
   ```

   (`php -S localhost:8000` also works. Requests to `api/*` will
   fail locally and on Pages — that is the expected honest error
   state, not a bug.)

2. Open and click through every page, including fragment links:

   - `http://localhost:8000/index.html`
   - `http://localhost:8000/casa-cortijillo.html`
   - `http://localhost:8000/casa-mirador.html`
   - `http://localhost:8000/condiciones.html`
   - `http://localhost:8000/privacidad.html`

   Every page and every internal link must return HTTP 200.
   No internal link may target a `.php` URL.

3. Run the link checker before pushing (same scan CI runs):

   ```bash
   lychee --verbose --no-progress --exclude 'api/*' './**/*.html'
   ```

   The `fetch('api/calcular.php')` POST in `js/app.js` is excluded
   from link checks: it always fails on Pages by design and shows
   the honest error state instead.

4. Validate the sitemap parses and lists the five static URLs:

   ```bash
   python3 -c "import xml.dom.minidom; xml.dom.minidom.parse('sitemap.xml')"
   ```

5. Push or open the PR. The `Link check` workflow runs lychee on
   every push and pull request and **blocks the merge** on any
   internal 404.

## Publish

Merge to `main`. Pages republishes automatically. Then verify:

- the five page URLs return 200 on the live site, and
- `/sitemap.xml` returns 200 with all five `<loc>` entries.

## Rollback

Each slice is one revertible commit on `main`:

```bash
git revert <slice-commit-sha>
```
