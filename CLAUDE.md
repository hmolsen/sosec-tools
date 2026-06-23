# UI Style Guide for Software Security Helper Tools

All tools in this repository must follow the design pattern established by the `qr`, `jwt`, and `oidc` tools.

---

## Shared Assets (`commons/`)

A shared stylesheet and font live in `commons/` at the repo root. **Always use these instead of tool-local copies.**

| Asset | Path |
|---|---|
| Stylesheet | `/commons/style.css` |
| Open Sans woff2 | `/commons/fonts/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2` |

### Linking the stylesheet

**Tailwind projects** — add after the Tailwind CDN `<script>` tag and remove any inline `<style>` blocks that duplicate the common rules:
```html
<link rel="preload" href="/commons/fonts/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/commons/style.css">
```

**Bootstrap/PHP projects** — add after `bootstrap.min.css` and remove the old `style.css` font/color rules that are now in the common file:
```html
<link rel="preload" href="/commons/fonts/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/commons/style.css">
```

### CSS classes provided by `commons/style.css`

| Class | Purpose |
|---|---|
| `ss-nav` | "All Tools" back-navigation wrapper |
| `ss-header` | Centered page header wrapper |
| `ss-card` | White rounded card with shadow (use instead of Bootstrap `.card` overrides) |
| `ss-input` | Styled text input / textarea |
| `ss-btn` | Full-width dark-red primary button |
| `ss-status` + `ss-status-{success\|error\|warning\|info}` | Status/alert message box |
| `ss-footer` | Centered muted footer |

Bootstrap overrides (rounded corners, colors) are applied automatically when Bootstrap is present — no extra classes needed on `.card`, `.form-control`, `.btn-primary`, etc.

Do **not** copy the font file into individual tool directories. Do **not** re-declare `@font-face` locally.

---

## Back Navigation

Every tool page (except the index) must include an "All Tools" back link as the **first element inside `<body>`**, before the header. The body must use `p-4 md:p-8` padding (not flex centering) so the nav sits flush at the top-left.

```html
<nav class="mb-6">
    <a href="/" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-[#7B1717] transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        All Tools
    </a>
</nav>
```

The `ss-nav` class in `commons/style.css` provides equivalent styling for non-Tailwind tools.

Content centering is done on the **content container** (`max-w-4xl mx-auto` or `max-w-xl mx-auto`), never on the body.

---

## Brand Assets

- **Logo**: `software-security_logo.png` — display centered at the top of the page.
  - Tailwind projects: `<img src="https://hannesmolsen.de/images/software-security_logo.png" alt="Software Security" class="mx-auto h-20 mb-4 rounded-lg" />`
  - Bootstrap/PHP projects: serve from `public/img/software-security_logo.png`, display at `width="200px"` centered.
- **Page title pattern**: Logo → `<h1>` (tool name) → `<p>` subtitle (one-liner describing what the tool does).

---

## Typography

- **Font**: Open Sans — served from `/commons/fonts/` (declared in `commons/style.css`). Never load from Google Fonts CDN and never copy the font into a tool's own directory.
- `body` gets `font-family: 'Open Sans', sans-serif` automatically via `commons/style.css`.
- All `<input>`, `<textarea>`, and `<select>` elements use `font-family: monospace` automatically via `commons/style.css`.

---

## Color Palette

| Role | Hex |
|---|---|
| Page background | `#E7E6E6` |
| Primary action / CTA (dark red) | `#7B1717` |
| Primary hover | `#C05C5C` |
| Input / area background | `#E7E6E6` |
| Focus ring / info accent (dark blue) | `#17177B` |
| Success text | `#177B17` |
| Success background | `#E6F3E6` |
| Error text | `#7B1717` |
| Error background | `#F8E6E6` |
| Warning text | `#DFDF17` |
| Warning background | `#FFFDE6` |
| Info text | `#17177B` |
| Info background | `#E6E6F8` |
| Headings / labels | `#000000` |

Set `<meta name="theme-color" content="#7B1717">` in the `<head>`.

---

## CSS Framework

- **New single-page tools**: use Tailwind CSS via CDN (`https://cdn.tailwindcss.com`).
- **PHP / multi-page tools**: use Bootstrap 5 served locally from `public/css/bootstrap.min.css`, with overrides in `public/css/style.css`.
- Do not mix frameworks within a single tool.

---

## Layout

- **Page background**: `background-color: #E7E6E6` on `body`.
- **Content cards** (Tailwind): `bg-white p-6 md:p-8 rounded-xl shadow-2xl`
- **Content cards** (Bootstrap): standard `card` or plain `<div>` with `border border-gray-200 rounded-lg shadow-lg bg-white p-4`.
- **Max-width container**:
  - Single-column tools: `max-w-4xl mx-auto` (Tailwind) or `max-width: 700px` (Bootstrap).
  - Two-column tools: `max-w-7xl mx-auto` with a responsive `grid grid-cols-1 lg:grid-cols-2 gap-6`.
- **Header**: centered, `text-center mb-10`, containing logo, `<h1>`, and subtitle.
- **Section numbering**: number multi-step sections (1., 2., 3.) in their headings.
- **Section heading style** (Tailwind): `text-2xl font-bold text-black mb-6 border-b pb-2`
- **Section heading style** (Bootstrap): `<h4 class="mb-3">`

---

## Buttons

- Background: `#7B1717`; text: white; hover: `#C05C5C`.
- Full width: `w-full` (Tailwind) / `w-100` (Bootstrap).
- Tailwind class: `px-4 py-3 bg-[#7B1717] text-white font-semibold rounded-lg hover:bg-[#C05C5C] transition duration-150 shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#7B1717] focus:ring-offset-2`
- Bootstrap override in `style.css`:
  ```css
  .btn-primary {
      --bs-btn-bg: #7B1717;
      --bs-btn-border-color: #7B1717;
      --bs-btn-hover-bg: #C05C5C;
      --bs-btn-hover-border-color: #7B1717;
      --bs-btn-active-bg: #C05C5C;
  }
  ```

---

## Form Elements

- All `<input>`, `<textarea>`, `<select>` use `font-family: monospace` and `background-color: #E7E6E6`.
- Focus ring color: `#17177B` (dark blue) — `focus:ring-[#17177B] focus:border-[#17177B]`.
- Border: `border border-gray-300 rounded-lg` (Tailwind) or Bootstrap default.

---

## Status Messages

Four states: `success`, `error`, `warning`, `info`. Always show as a bordered, padded box.

```css
.status-success { background-color: #E6F3E6; color: #177B17; border-color: #177B17; }
.status-error   { background-color: #F8E6E6; color: #7B1717; border-color: #7B1717; }
.status-warning { background-color: #FFFDE6; color: #DFDF17; border-color: #DFDF17; }
.status-info    { background-color: #E6E6F8; color: #17177B; border-color: #17177B; }
```

- Auto-dismiss after 5 seconds.
- Apply class `p-3 mb-4 rounded-lg border` plus the state class.

---

## Footer

Every tool must include a footer. Use the snippet that matches the CSS framework.

**Tailwind:**
```html
<footer class="mt-12 py-6 text-center text-sm text-gray-500">
    <p class="mb-2">&copy; 2026 Software Security</p>
    <ul class="flex justify-center gap-6 list-none p-0">
        <li><a href="https://hannesmolsen.de/impressum.html" class="hover:underline">Impressum</a></li>
        <li><a href="https://hannesmolsen.de/datenschutz.html" class="hover:underline">Datenschutz</a></li>
        <li><a href="https://hannesmolsen.de" class="hover:underline">Hannes Molsen</a></li>
    </ul>
</footer>
```

**Bootstrap / PHP:**
```html
<footer class="my-5 pt-5 text-muted text-center text-small">
    <p class="mb-1">&copy; 2026 Software Security</p>
    <ul class="list-inline">
        <li class="list-inline-item"><a href="https://hannesmolsen.de/impressum.html">Impressum</a></li>
        <li class="list-inline-item"><a href="https://hannesmolsen.de/datenschutz.html">Datenschutz</a></li>
        <li class="list-inline-item"><a href="https://hannesmolsen.de">Hannes Molsen</a></li>
    </ul>
</footer>
```

Links: Impressum → `https://hannesmolsen.de/impressum.html`, Datenschutz → `https://hannesmolsen.de/datenschutz.html`, Hannes Molsen → `https://hannesmolsen.de`. Copyright year is 2026.

---

## Clipboard Copy Pattern

Use `document.execCommand('copy')` via a temporary `<textarea>` (not `navigator.clipboard`) for compatibility in iframe/sandbox environments:

```js
const tmp = document.createElement('textarea');
tmp.value = text;
document.body.appendChild(tmp);
tmp.select();
document.execCommand('copy');
document.body.removeChild(tmp);
```
