# SoSecTools

A collection of small, self-contained security/utility web tools hosted on a PHP server at
`cqrity.de`. Each tool lives in its own top-level folder and is a single static
`index.html` (HTML + Tailwind via CDN + vanilla JS in one file) served at `/<tool>/`. There is
no build step, no bundler, no npm project — everything runs directly in the browser.

The site root `index.html` is a card-grid overview page linking to every tool.

## Two domains — don't mix them up

- **`cqrity.de` is this repo.** It is where the tools are deployed and the doc root everything
  root-relative resolves against: tool links (`/groups/`, `/vm/certgen.php`), the shared assets
  under `/commons/` (`style.css`, `fonts/`), and the `/` "All Tools" back-link. Anything that is
  part of this site is referenced root-relative and lands on `cqrity.de`.
- **`hannesmolsen.de` is Hannes' personal website**, a separate site. This repo links *out* to it
  in exactly three places, always with an absolute `https://hannesmolsen.de/...` URL: the header
  logo image, and the Impressum / Datenschutz / name links in the footer.

So: a new in-repo path is root-relative (`/tool/`), and the only absolute `hannesmolsen.de` URLs
in a new tool are the logo plus the three footer links copied verbatim. Never serve or link a tool
under `hannesmolsen.de`, and never make the logo or legal links root-relative.

## Reference implementation

**`groups/index.html` is the best and most recent example of house style.** When writing a new
tool, model it after `groups/`, not the older tools (`qr`, `jwt`, `ik_gen`, `oidc` are legacy and
inconsistent — don't copy their patterns; they predate the current conventions).

## Adding a new tool — required steps

1. Create `/<tool-name>/index.html` following the style guide below.
2. **Always add a matching card to the root `index.html`** overview page (in the
   `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6` inside `<main>`), using the same card
   markup pattern as the existing entries (icon block + title + description + "Open tool →").
   Never leave a new tool unlinked from the overview page.
3. Pick a Heroicons outline SVG (see "Icons" below) that reasonably represents the tool for both
   the card icon and the root overview card icon.

## Page structure (based on `groups/index.html`)

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#7B1717">
    <title>Software Security – <Tool Name></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="/commons/fonts/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/commons/style.css">
    <!-- optional <style> block for tool-specific CSS Tailwind can't express -->
</head>
<body class="min-h-screen p-4 md:p-8">

    <nav class="mb-6">
        <a href="/" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-[#7B1717] transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            All Tools
        </a>
    </nav>

    <header class="text-center mb-10">
        <img src="https://hannesmolsen.de/images/software-security_logo.png" alt="Software Security" class="mx-auto h-20 mb-4 rounded-lg" />
        <h1 class="text-4xl font-extrabold text-gray-900 mb-2"><Tool Name></h1>
        <p class="text-xl text-gray-500"><One-line description of what it does></p>
    </header>

    <main class="max-w-4xl mx-auto space-y-6">
        <!-- tool content: one or more bg-white p-6 md:p-8 rounded-xl shadow-2xl "cards" -->
    </main>

    <footer class="mt-12 py-6 text-center text-sm text-gray-500">
        <p class="mb-2">&copy; 2026 Software Security</p>
        <ul class="flex justify-center gap-6 list-none p-0">
            <li><a href="https://hannesmolsen.de/impressum.html" class="hover:underline">Impressum</a></li>
            <li><a href="https://hannesmolsen.de/datenschutz.html" class="hover:underline">Datenschutz</a></li>
            <li><a href="https://hannesmolsen.de" class="hover:underline">Hannes Molsen</a></li>
        </ul>
    </footer>

    <script>
        // all JS for the tool goes here, plain vanilla JS, no framework
    </script>
</body>
</html>
```

Key points:
- `<meta name="theme-color" content="#7B1717">` on every page.
- Load Tailwind from `https://cdn.tailwindcss.com` (no config file needed; `groups` shows an
  inline `tailwind.config` block is acceptable if you need custom theme colors, e.g. `totp` does
  this to define `primary`/`success`/etc. as Tailwind color names).
- Font: `/commons/fonts/....woff2` preload + `/commons/style.css` stylesheet — this stylesheet
  already defines the `Open Sans` `@font-face` and base body styles (background `#E7E6E6`, font
  family). Do **not** redefine `@font-face` or `body { font-family }` per-tool; that's legacy
  (`qr`, `jwt`) from before `/commons/style.css` existed.
- `<nav>` "All Tools" back-link at the very top of the body, before the header, on every page.
- `<title>` pattern: `Software Security – <Tool Name>` (em dash) or `Software Security <Tool
  Name>` — both appear; prefer the em-dash form used in `groups`/`punycode`.
- Root `<main>` wrapped at `max-w-4xl mx-auto` (wider tools like `groups` use `max-w-6xl`, table
  layouts like `oose` go full-bleed with a custom fixed layout — pick width to fit content).
- Footer is byte-for-byte identical across all tools — copy it verbatim.

## Visual style / color palette

Brand primary is dark red `#7B1717`. There is a fixed 4-color semantic status palette used for
inline success/error/warning/info messages, consistent across every tool:

| Purpose | Background | Text/Border  |
|---------|-----------|----------|
| success | `#E6F3E6` | `#177B17` |
| error   | `#F8E6E6` | `#7B1717` |
| warning | `#FFFDE6` | `#DFDF17` |
| info    | `#E6E6F8` | `#17177B` |

Common pattern for a status/toast element:
```js
const map = {
    success: 'bg-[#E6F3E6] text-[#177B17] border-[#177B17]',
    error:   'bg-[#F8E6E6] text-[#7B1717] border-[#7B1717]',
    warning: 'bg-[#FFFDE6] text-[#DFDF17] border-[#DFDF17]',
    info:    'bg-[#E6E6F8] text-[#17177B] border-[#17177B]',
};
el.className = `p-3 rounded-lg border text-sm ${map[type]}`;
```
See `groups`' `showToast()` and `punycode`'s `showStatus()` for two equivalent implementations
(fixed-position toast vs. inline status box) — pick whichever fits the tool's interaction model.

Other palette notes:
- Neutral input/code background: `#E7E6E6`.
- Hover state for primary red buttons: `#C05C5C`.
- Secondary accent (used for focus rings on textareas/inputs, drag-drop highlight, links inside
  info boxes): indigo `#17177B`.
- Page background is `#E7E6E6` (set globally by `/commons/style.css`).

Layout conventions:
- Content sections are white "cards": `bg-white p-6 md:p-8 rounded-xl shadow-2xl`, each with an
  `<h2 class="text-2xl font-bold text-black mb-6 border-b pb-2">` section title if there's more
  than one section.
- Primary action buttons: `px-4 py-3 bg-[#7B1717] text-white font-semibold rounded-lg
  hover:bg-[#C05C5C] transition duration-150 shadow-md focus:outline-none focus:ring-2
  focus:ring-[#7B1717] focus:ring-offset-2`.
- Secondary/outline buttons: `border-2 border-gray-300 text-gray-700 ... hover:border-gray-400
  hover:bg-gray-50` (neutral) or `border-2 border-[#7B1717] text-[#7B1717] ...
  hover:bg-[#F8E6E6]` (destructive/red-outline, e.g. a "reset everything" action).
- Inputs/textareas: `border border-gray-300 rounded-lg focus:ring-[#17177B]
  focus:border-[#17177B] transition shadow-sm`, monospace font for code-like content, often on
  the `#E7E6E6` neutral background.
- Icons are Heroicons (outline, 1.5 stroke width), inlined as `<svg>` — see "Icons" below.

## Icons

All card/nav icons are from [Heroicons](https://heroicons.com) outline set, inlined directly as
`<svg xmlns="http://www.w3.org/2000/svg" ... fill="none" viewBox="0 0 24 24" stroke="currentColor"
stroke-width="1.5">` with a single `<path stroke-linecap="round" stroke-linejoin="round"
d="..."/>` (or multiple paths for compound icons). No icon font, no external icon library except
where a tool needs something Heroicons doesn't have (e.g. `oose` pulls in `flag-icons` for
country flags — that's a justified one-off exception, not the norm).

Root overview card icon wrapper: `<div class="bg-[#F8E6E6] flex items-center justify-center
p-6">` containing `<svg class="h-12 w-12 text-[#7B1717]" ...>`.

## JS conventions

- Plain vanilla JS in a single `<script>` block at the end of `<body>`, no imports/modules, no
  build step. External libraries (if needed) are loaded via `<script src="https://cdn.jsdelivr.
  net/...">` in `<head>` (e.g. `qrcode-svg`, `jsQR`, `punycode`).
- Grab DOM refs once at the top via `document.getElementById(...)` into `const`s.
- Section comments use a `// ── Label ──────...` banner style to divide logical regions of the
  script (state, persistence, render, actions, etc.) — see `groups/index.html`.
- Client-side only: no backend calls, no data leaves the browser (tools that state this
  explicitly, e.g. TOTP, call it out in a small footer note — do so if relevant/reassuring for a
  security tool).
- State persistence (if the tool needs it) uses `localStorage` with a namespaced key like
  `ss-<tool>-tool`, with a `save()`/`load()` pair and defensive `try/catch` + shape-checking on
  load (see `groups`' `save()`/`load()`).
- Copy-to-clipboard uses the `document.execCommand('copy')` via a temporary offscreen textarea
  pattern (works reliably in the iframe/embedded contexts these tools may run in) — see
  `copyOutput`/`copyDecodedText`/`copyCode` implementations across tools — rather than
  `navigator.clipboard` alone.
- Toast/status feedback auto-hides after ~3.5–5s via `setTimeout` + `clearTimeout` on a stored
  timer handle.

## When asked to build a new tool

1. Scaffold `/<tool-name>/index.html` from the `groups/index.html` skeleton (head, nav, header,
   footer, section-card layout) per the "Page structure" section above.
2. Implement the tool's logic as vanilla JS, client-side only, following the JS conventions
   above.
3. Use the semantic status color palette for any success/error/warning/info feedback.
4. Add a card for it to the root `index.html` overview grid with a fitting Heroicons icon,
   title, and one-sentence description, following the existing card markup exactly.
5. Do not introduce a build step, framework, or package.json — these tools are meant to be
   dropped straight onto the PHP host as static files.
