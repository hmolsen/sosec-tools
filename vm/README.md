# `vm/` — Certificate provisioning for the security-training VMs

This folder is the small server-side plumbing that keeps the two training domains
**`vulnerads.de`** and **`attacat.de`** supplied with valid Let's Encrypt certificates, and the
one-liner the training VMs run at boot to pick those certificates up.

It is **not** part of the SoSecTools browser-tool collection — no Tailwind, no house style, it is
plain PHP that runs server-side plus a shell script that runs on the VM.

The whole folder ships with the rest of this repo to the SoSecTools host and is served at
**`https://cqrity.de/vm/`** — that URL is hardcoded in `update_certs.sh`, so the VMs break if the
folder ever moves.

## The two halves

### 1. Server side — issue certificates (manual, ~4× a year)

| File | Purpose |
|---|---|
| `certgen.php` | The UI, in SoSecTools house style: KAS password + 2FA one-time PIN, posts to `certgen_post.php` and streams the run log back into the page. |
| `certgen_post.php` | Does all the work: KAS login → ACME `dns-01` order → writes PEMs → builds `certs.tar`. |
| `ACMECert/` | Vendored [skoerfgen/ACMECert](https://github.com/skoerfgen/ACMECert) 3.4.0 (MIT), the ACME v2 client. Unmodified upstream — do not patch, replace wholesale on upgrade. |
| `certs/` | Output: `<domain>.fullchain.pem` and `<domain>.private_key.pem` for both domains. |

What `certgen_post.php` does, in order:

1. Opens a **KAS API session** against `https://kasapi.kasserver.com` (All-Inkl) using the posted
   password and OTP. The KAS login is hardcoded to `w0060714`; the `username` field on the form is
   ignored.
2. Points ACMECert at the **Let's Encrypt production** directory. (The staging URL is present but
   commented out — uncomment it while testing so you don't burn rate limits.)
3. **Renewal guard:** if `certs/vulnerads.de.fullchain.pem` or `certs/attacat.de.fullchain.pem` has
   more than 80 days left, the script prints a message and `exit()`s. See "Gotchas" below.
4. Loads `account_key.pem` next to the script, or generates + registers a fresh 2048-bit RSA ACME
   account key (contact `mail@sosec.de`) and saves it on first run.
5. For each domain, solves a **`dns-01`** challenge by calling the KAS API to `add_dns_settings` a
   `_acme-challenge` TXT record, then cleans up afterwards by enumerating
   `get_dns_settings` on `ns5.kasserver.com` and `delete_dns_settings` on every `_acme-challenge`
   record it finds (with a 1s sleep between deletes as KAS flood prevention).
6. Writes `certs/<domain>.fullchain.pem` + `certs/<domain>.private_key.pem`.
7. Packs the `certs/` directory into **`certs.tar`** via `PharData`, which is what the VMs download.

Progress is streamed to the browser as HTML; PHP errors go to `php-error.log` in the same directory.

### 2. VM side — consume certificates (every boot)

`update_certs.sh` runs on each Kali training VM at startup. It:

1. `cd /home/kali/Vulnerads`
2. downloads `https://cqrity.de/vm/certs.tar`, unpacks it in place, deletes the tarball
3. converts each PEM pair into a **PKCS#12 keystore** with alias `sosec` and password `sosec`:
   - `vulnerads.de` → `/home/kali/Vulnerads/vulnerads/src/main/resources/vulnerads.de.p12`
     (Spring Boot app resource)
   - `attacat.de` → `/home/kali/Vulnerads/attacat-8666/conf/attacat.de.p12`

The whole thing is a single `&&`-chain, so any failed step aborts the rest silently.

## Operating it

**Renewing (server, from a browser):**

1. Open `https://cqrity.de/vm/certgen.php`, enter the KAS password and the current 2FA OTP.
2. Watch the run log stream into the page. The status box above it summarises the outcome —
   green on success, yellow if the 80-day guard stopped the run early, red on a reported error.
3. On success, `certs.tar` is regenerated and served at `https://cqrity.de/vm/certs.tar`.

The page submits over `fetch` and strips all markup out of the response before rendering it, so the
backend's raw HTML is never inserted as live HTML. Without JS it degrades to a plain form POST and
you get `certgen_post.php`'s unstyled output directly.

**Refreshing a VM (on the VM, or wired into boot):**

```sh
curl -s https://cqrity.de/vm/update_certs.sh | bash -s
```

## Server requirements

The web host serving `/vm/` needs:

- **PHP ≥ 5.6** (ACMECert's floor; anything modern is fine).
- **`ext-openssl`** — key generation, CSRs, certificate parsing.
- **`ext-soap`** — the KAS API is SOAP (`SoapClient`). This is the extension most likely to be
  missing; without it `certgen_post.php` dies at the login step.
- **`ext-phar`** — `PharData` builds `certs.tar`. (Data archives like `.tar` are writable even with
  `phar.readonly=1`, so no ini change is needed.)
- **`ext-curl`** *or* `allow_url_fopen=1` — ACMECert falls back to `file_get_contents` if curl is
  absent, but curl is preferred.
- **Outbound HTTPS** to `acme-v02.api.letsencrypt.org` and `kasapi.kasserver.com`.
- **Write permission for the PHP user** on this directory and `certs/`, because the script creates
  `account_key.pem`, `php-error.log`, `certs.tar` and the four PEM files.
- Enough **execution time** — a `dns-01` order waits on DNS propagation for both domains; a run can
  take minutes. Raise `max_execution_time` if it gets cut off.
- **`certs.tar` and `update_certs.sh` must be publicly readable** over HTTPS — that is the VMs'
  only channel.

## DNS / account prerequisites

- Both `vulnerads.de` and `attacat.de` are DNS-hosted at **All-Inkl (KAS)**, nameserver
  `ns5.kasserver.com`, under KAS account **`w0060714`**, and the KAS API must be enabled for that
  account with 2FA active.
- The `_acme-challenge` TXT record is created and deleted through the API — nothing to prepare by
  hand, but manual `_acme-challenge` records in either zone will be deleted by the cleanup pass.

## VM requirements

- Kali VM with user **`kali`** and the training checkout at **`/home/kali/Vulnerads`**, containing
  `vulnerads/src/main/resources/` and `attacat-8666/conf/`.
- `wget`, `tar`, `openssl` and `curl` on `PATH`.
- Network up **before** the script runs. If it is wired into boot via systemd, order it
  `After=network-online.target` / `Wants=network-online.target`, otherwise the download fails and
  the VM silently keeps yesterday's keystores.
- Write access to both `.p12` target paths.
- The consuming apps must expect keystore password **`sosec`** and key alias **`sosec`**.

## Gotchas

- **The 80-day guard is an early `exit()`, not a per-domain skip.** If `vulnerads.de` is still
  fresh, the script exits before it even looks at `attacat.de` — and before rebuilding `certs.tar`.
  To force a reissue, move the existing `certs/*.fullchain.pem` out of the way first.
- **Production CA by default.** Repeated forced runs will hit Let's Encrypt rate limits. Switch to
  the staging directory (commented out near the top) while debugging.
- **`certs.tar` is appended to, not rebuilt.** `PharData` opens an existing archive; same-named
  entries are replaced, but stale entries from removed domains would linger. Delete `certs.tar`
  before a run if the domain list ever changes.
- **`certgen.php` is unauthenticated.** Anyone who reaches the URL gets a form that posts a KAS
  password; the endpoint should sit behind HTTP basic auth / IP restriction and HTTPS only.
- **Private keys live in `certs/` and are in this repo.** They are training-only certificates for
  throwaway domains, but treat any commit of `certs/*.private_key.pem` as publishing that key —
  the corresponding certificate is burned and must be reissued.
- **Keystore password `sosec` is intentionally trivial** — these are deliberately vulnerable
  training targets, not production systems.
