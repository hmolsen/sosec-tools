# `vm/` — Certificate provisioning for the security-training VMs

This folder is the small server-side plumbing that keeps the two training domains
**`vulnerads.de`** and **`attacat.de`** supplied with valid Let's Encrypt certificates, and the
one-liner the training VMs run at boot to pick those certificates up.

Unlike the other tools in this repo it is not a client-side toy: the real work happens in PHP on
the server, plus a shell script that runs on the VM. Only the operator UI (`certgen.php`) follows
the SoSecTools house style and is linked from the root overview page.

The whole folder ships with the rest of this repo to the SoSecTools host and is served at
**`https://cqrity.de/vm/`** — that URL is hardcoded in `update_certs.sh`, so the VMs break if the
folder ever moves.

## The two halves

### 1. Server side — issue certificates (manual, ~4× a year)

| File | Purpose |
|---|---|
| `certgen.php` | The UI, in SoSecTools house style. Reads the current `certs/` contents server-side with `openssl_x509_parse` and shows subject, validity window, days remaining, issuer, serial and chain length per domain; below that, the KAS password + 2FA form, which posts to `certgen_post.php` and streams the run log back into the page. |
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

### 2. VM side — update code and certificates (every boot)

`update_certs.sh` runs on each Kali training VM at startup. The name is historical: it now does
**four** jobs. The invocation is baked into the VM images and cannot be changed, so this one file at
this one URL is the only lever for changing what happens at boot — hence the misnomer, and hence why
the filename must stay as it is.

**Phase 1 — sync the exercise code from GitHub.** For `/home/kali/Vulnerads/vulnerads` and
`/home/kali/Vulnerads/attacat-8666`:

1. `git fetch --prune origin` on the repo's current branch (skipped on a detached HEAD).
2. If `HEAD` already matches `origin/<branch>`, log "already current" and move on.
3. If tracked files are modified, save them with `git stash push -m "sosec-autoupdate <timestamp>"`
   so participant work is never silently destroyed — recover it with `git stash list` /
   `git stash pop`. **Untracked** files are deliberately left alone.
4. `git reset --hard origin/<branch>`, then log the commits that arrived.

**Phase 1b — purge stale build outputs.** After each sync, `build/`, `out/`, `.gradle/` and
`bin/main`, `bin/test` are deleted from every repo.

This is not housekeeping, it fixes a real failure. `git reset --hard` restores **tracked** files
only; compiled classes under `build/` are untracked, so they survive a sync and stay on the runtime
classpath. A class referring to something long since deleted from the source — a `javax.servlet`
import, say — then still crashes the app at startup, while the source looks perfectly correct.

It runs on **every** invocation, not only when git reported new commits: a checkout can be fully
current while `build/` is left over from an older commit, which is exactly that failure mode. It
runs even if the fetch failed, since a stale build is worth clearing either way. Downloaded
dependencies live in `~/.gradle` and are deliberately **not** touched, so this costs a recompile,
never a re-download. Source files, untracked participant files and the installed keystores are all
left alone.

**Phase 2 — install the certificates** (unchanged behaviour, now with error checks):

1. downloads `https://cqrity.de/vm/certs.tar` into `/home/kali/Vulnerads`, unpacks, removes the tar
2. warns loudly in the log if a downloaded certificate is already expired (it still installs it —
   an expired cert lets the apps start, a missing keystore does not)
3. converts each PEM pair into a **PKCS#12 keystore** with alias `sosec` and password `sosec`:
   - `vulnerads.de` → `/home/kali/Vulnerads/vulnerads/src/main/resources/vulnerads.de.p12`
     (Spring Boot app resource)
   - `attacat.de` → `/home/kali/Vulnerads/attacat-8666/conf/attacat.de.p12`

**Phase 3 — repair IntelliJ's Gradle JVM.** Gradle 9 refuses to run on a JVM older than 17, and
IntelliJ picks that JVM from its own *Gradle JVM* setting — which is independent of the toolchain in
`build.gradle`. When it points at the old JDK 13 still on the image, every sync fails with:

> Your build is currently configured to use incompatible Java 13.0.14 and Gradle 9.7.1.
> Cannot sync the project.

**Do not accept IntelliJ's offer to downgrade to Gradle 8.14** — Spring Boot 4 needs Gradle 9. The
correct fix is to select the JDK 17 that is already installed on the VM, which this phase does
without opening the IDE:

1. Finds the lowest usable JDK >= 17, searching `/usr/lib/jvm`, `~/.jdks`, `/opt/java` and finally
   IntelliJ's own bundled JetBrains Runtime under
   `~/.local/share/JetBrains/Toolbox/apps/*/jbr`. Each candidate is canonicalised with
   `readlink -f` and must have `bin/javac`. All three details matter:
   - IntelliJ will not accept the Debian alternatives symlink `/usr/lib/jvm/default-java` as an
     SDK home; it needs the real directory.
   - **A JRE is not a JDK.** These images ship `openjdk-17-jre` without `javac`, and pointing the
     Gradle JVM at it is exactly what produces the invalid-JDK error below.
   - The bundled JBR is a genuine JDK and is always present when the IDE is, so it is a reliable
     fallback on a JRE-only image.

   When nothing qualifies, the log lists every candidate and why it was rejected, then points at
   `sudo apt install openjdk-17-jdk` — the real fix if no JDK is installed at all.
2. If `.idea/gradle.xml` already selects it, logs one line and stops — importantly, it does **not**
   disturb a running IDE when nothing needs changing.
3. Otherwise stops IntelliJ (`pkill -f idea`, waiting for it to actually exit, escalating to
   `-9` after 30s) — the IDE rewrites its config on exit and would discard the fix.
4. Registers the JDK in `~/.config/JetBrains/*/options/jdk.table.xml` — including a full
   `<classPath>` of `jrt://` module roots generated from `java --list-modules` — and sets
   `gradleJvm` in the project's `.idea/gradle.xml`.

An SDK entry with an **empty `<classPath>`** is the thing IntelliJ reports as:

> Invalid Gradle JDK configuration found. Open Gradle Settings

so the module roots are not optional decoration. If `java --list-modules` returns nothing, the
phase refuses to write an entry at all and fails loudly, rather than silently recreating that
broken state. A previously-written rootless entry is detected and rebuilt in place.

Finally it repairs `.idea/misc.xml`: these images ship with `project-jdk-name="azul-13"` and
`languageLevel="JDK_13"`, which points the whole project at Java 13 and becomes a dangling SDK
entry once `~/.jdks/azul-13.0.14` is gone. Both are set to the JDK 17 the Gradle JVM now uses.

Needs no `sudo`: everything it writes is under `$HOME` or the project.

**The order is deliberate.** `git reset --hard` reverts tracked files — and both the `vulnerads`
keystore and `.idea/gradle.xml` live *inside* a synced repo. So the code sync must run first, or a
reset would revert the keystore and the IntelliJ fix that were just applied.

Everything is logged with timestamps to stdout (so it lands in the boot journal) and to
`/home/kali/Vulnerads/update.log`. The phases are independent: a failed `git fetch` does not
stop the certificates from being installed, a missing JDK does not stop either of the first two, and
so on. The script exits non-zero if any phase failed, naming which in the final line.

> **Syncing source does not rebuild or restart anything.** New code takes effect the next time a
> participant starts the app themselves (e.g. `./gradlew bootRun`, or a sync in IntelliJ).

## Operating it

**Renewing (server, from a browser):**

1. Open `https://cqrity.de/vm/certgen.php`. The top card shows what the VMs are currently being
   served — check the expiry dates there first; it also warns when the 80-day guard will block a
   reissue, or when `certs.tar` is older than the PEMs beside it.
2. Enter the KAS password and the current 2FA OTP.
3. Watch the run log stream into the page. The status box above it summarises the outcome —
   green on success, yellow if the 80-day guard stopped the run early, red on a reported error.
4. On success, `certs.tar` is regenerated and served at `https://cqrity.de/vm/certs.tar`. Hit
   **Refresh** on the top card to re-read the new certificate details.

The page submits over `fetch` and strips all markup out of the response before rendering it, so the
backend's raw HTML is never inserted as live HTML. Without JS it degrades to a plain form POST and
you get `certgen_post.php`'s unstyled output directly.

**Updating a VM by hand — run this on the VM, from any directory:**

```sh
curl -fsS https://cqrity.de/vm/update_certs.sh | bash
```

That pulls the latest exercise code from GitHub *and* installs the current certificates, then
prints a timestamped summary. Use `-fsS`, not a bare `-s`: `-f` makes curl fail on an HTTP error
instead of piping the server's error page into `bash`, and `-sS` stays quiet while still showing
real errors.

To see what it did, or what it did on the last boot:

```sh
tail -40 ~/Vulnerads/update.log
```

The same script runs automatically at every boot through the older invocation baked into the VM
images (`curl -s ... | bash -s`). That one cannot be changed, which is why the script itself has to
carry all the error handling.

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
- `git`, `tar`, `openssl`, `timeout`, `python3`, `pgrep`/`pkill` and `curl` (or `wget`) on `PATH`.
- A **JDK 17 or newer** installed for the IntelliJ fix (the images already ship OpenJDK 17.0.6).
  Without one, phase 3 logs the `apt install openjdk-17-jdk` hint and reports a failure.
- Both training repos cloned with an `origin` remote pointing at the **public** GitHub repos, so an
  anonymous HTTPS fetch works with no credentials on the VM.
- Network up **before** the script runs. If it is wired into boot via systemd, order it
  `After=network-online.target` / `Wants=network-online.target`, otherwise both the code sync and
  the download fail and the VM silently keeps yesterday's code and keystores.
- Write access to both `.p12` target paths and to `/home/kali/Vulnerads/update.log`.
- The consuming apps must expect keystore password **`sosec`** and key alias **`sosec`**.
- If the boot hook runs the script **as root**, `sudo` or `runuser` must be present: the repos are
  owned by `kali`, and git refuses to touch another user's repo ("detected dubious ownership"), so
  the script re-runs every git command as `kali`.

## Gotchas

- **`git status` on a VM proves nothing about being up to date.** It never contacts the network: it
  compares the local branch against the remote-tracking ref `origin/<branch>`, which is a cache
  written by the last `git fetch`. On a VM that has never fetched, that cache is as old as the
  image, so "Your branch is up to date with 'origin/master'" is stale-vs-stale and always true.
  Always `git fetch origin` first, or just read `/home/kali/Vulnerads/update.log`.
- **The baked-in boot command is `curl -s ...| bash -s`, with no `-f`.** On an HTTP error the
  server's error page gets piped into `bash` instead of the script. Nothing in this repo can fix
  that; switch to `curl -fsS` whenever the VM images are next re-baked.
- **A `sosec-autoupdate` stash accumulates per dirty boot.** Participants who reboot repeatedly with
  uncommitted work will collect several; they are recoverable but never cleaned up automatically.
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
