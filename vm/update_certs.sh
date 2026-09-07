#!/usr/bin/env bash
#
# SoSec training VM update script.
#
# ─────────────────────────────────────────────────────────────────────────────
#  RUN THIS ON THE VM TO UPDATE IT (copy/paste, from any directory):
#
#      curl -fsS https://cqrity.de/vm/update_certs.sh | bash
#
#  -f = fail on an HTTP error instead of piping the server's error page into
#       bash;  -sS = quiet, but still print real errors.
# ─────────────────────────────────────────────────────────────────────────────
#
# The same script also runs automatically on every VM boot, via the older
# invocation baked into the VM images:
#
#      curl -s https://cqrity.de/vm/update_certs.sh | bash -s
#
# That baked-in command cannot be changed, so this file at this URL is the only
# lever for changing what happens at boot. The name is historical — it now does
# three jobs:
#
#   1. sync the exercise repos from GitHub   (see update_repo)
#   1b. purge stale build outputs            (see purge_build_outputs)
#   2. install the current TLS certificates  (see install_certs)
#   3. fix IntelliJ's Gradle JVM (needs 17+) (see configure_intellij_jvm)
#
# Job 3 stops a running IntelliJ first, because the IDE rewrites its own config
# on exit and would otherwise discard the fix.
#
# Certificates are issued separately, beforehand, at
# https://cqrity.de/vm/certgen.php — that rebuilds the certs.tar this downloads.
#
# Everything is logged to /home/kali/Vulnerads/update.log.

# Deliberately no `set -e`: a git failure must not stop the certificates from
# being installed, and vice versa. Each phase reports its own outcome.
set -uo pipefail

BASE=/home/kali/Vulnerads
REPO_USER=kali
CERT_URL=https://cqrity.de/vm/certs.tar
LOG=$BASE/update.log
FETCH_TIMEOUT=120

REPOS=(
    "$BASE/vulnerads"
    "$BASE/attacat-8666"
)

# domain:target .p12 path
CERT_TARGETS=(
    "vulnerads.de:$BASE/vulnerads/src/main/resources/vulnerads.de.p12"
    "attacat.de:$BASE/attacat-8666/conf/attacat.de.p12"
)

# Never block boot on a credential prompt. If a repo is renamed, deleted or
# turned private, git must fail immediately instead of waiting on stdin.
export GIT_TERMINAL_PROMPT=0
export GIT_ASKPASS=/bin/true

git_failed=0
cert_failed=0
idea_failed=0
build_failed=0

# ── Logging ────────────────────────────────────────────────────────────────
log() {
    local line
    line="[$(date -u '+%Y-%m-%d %H:%M:%S')] $*"
    echo "$line"
    echo "$line" >>"$LOG" 2>/dev/null || true
}

# ── git helper ─────────────────────────────────────────────────────────────
# The repos belong to kali. If the boot hook runs this as root, git refuses to
# operate on them ("detected dubious ownership") and every fetch fails, so drop
# back to the owning user when we are root.
# Set GIT_TIMEOUT before a call to bound how long it may run.
run_git() {
    local dir=$1
    shift
    local pre=()
    [ -n "${GIT_TIMEOUT:-}" ] && pre=(timeout "$GIT_TIMEOUT")

    if [ "$(id -u)" -eq 0 ] && [ "$(id -un)" != "$REPO_USER" ]; then
        if command -v sudo >/dev/null 2>&1; then
            ${pre[@]+"${pre[@]}"} sudo -n -u "$REPO_USER" git -C "$dir" "$@"
        else
            ${pre[@]+"${pre[@]}"} runuser -u "$REPO_USER" -- git -C "$dir" "$@"
        fi
    else
        ${pre[@]+"${pre[@]}"} git -C "$dir" "$@"
    fi
}

# ── Phase 1: sync one repo to its origin branch ────────────────────────────
update_repo() {
    local dir=$1
    local name branch before after

    name=$(basename "$dir")

    if [ ! -d "$dir/.git" ]; then
        log "REPO $name: no .git directory at $dir, skipping"
        return 0
    fi

    branch=$(run_git "$dir" rev-parse --abbrev-ref HEAD 2>/dev/null)
    if [ -z "$branch" ] || [ "$branch" = "HEAD" ]; then
        log "REPO $name: detached HEAD or unreadable branch, skipping"
        return 1
    fi

    # git status alone is offline and would report a stale cache as current;
    # only a fetch tells us what origin actually holds.
    if ! GIT_TIMEOUT=$FETCH_TIMEOUT run_git "$dir" fetch --prune origin >>"$LOG" 2>&1; then
        log "REPO $name: git fetch from origin failed (network down, or repo moved/private)"
        return 1
    fi

    before=$(run_git "$dir" rev-parse HEAD 2>/dev/null)
    after=$(run_git "$dir" rev-parse "origin/$branch" 2>/dev/null)

    if [ -z "$after" ]; then
        log "REPO $name: origin/$branch does not exist on the remote, skipping"
        return 1
    fi

    if [ "$before" = "$after" ]; then
        log "REPO $name: already current on $branch (${before:0:8})"
        return 0
    fi

    # Preserve participant edits to tracked files. No -u: untracked files are
    # left alone, so the installed .p12 and any scratch files survive.
    if ! run_git "$dir" diff --quiet 2>/dev/null || ! run_git "$dir" diff --cached --quiet 2>/dev/null; then
        local label="sosec-autoupdate $(date -u '+%Y-%m-%d %H:%M:%S')"
        if run_git "$dir" stash push -m "$label" >>"$LOG" 2>&1; then
            log "REPO $name: local changes stashed as '$label' (recover with: git -C $dir stash list)"
        else
            log "REPO $name: local changes present but stash failed, NOT resetting"
            return 1
        fi
    fi

    if ! run_git "$dir" reset --hard "origin/$branch" >>"$LOG" 2>&1; then
        log "REPO $name: reset to origin/$branch failed"
        return 1
    fi

    log "REPO $name: updated $branch ${before:0:8} -> ${after:0:8}"
    run_git "$dir" log --oneline --no-decorate "$before..$after" 2>/dev/null |
        head -20 | while read -r l; do log "REPO $name:   $l"; done

    return 0
}

# ── Phase 1b: purge stale build outputs ────────────────────────────────────
# `git reset --hard` restores tracked files only. Compiled classes under build/
# and out/ are untracked, so they survive a sync and stay on the runtime
# classpath — which is how a reference that no longer exists anywhere in the
# source (a javax.servlet import, say) can still crash the app at startup.
#
# Purged on every run, not just when git reported a change: a checkout can be
# current while the build directory is from an older commit, which is exactly
# that failure. Dependencies live in ~/.gradle and are deliberately NOT touched,
# so this costs a recompile, not a re-download.
BUILD_DIRS=(build out .gradle bin/main bin/test)

purge_build_outputs() {
    local dir=$1 name sub removed=0

    case "$dir" in '' | / | /home | /home/*/) return 1 ;; esac
    [ -d "$dir" ] || return 0
    name=$(basename "$dir")

    for sub in "${BUILD_DIRS[@]}"; do
        [ -d "$dir/$sub" ] || continue
        if rm -rf "${dir:?}/$sub"; then
            log "BUILD $name: purged $sub/"
            removed=$((removed + 1))
        else
            log "BUILD $name: could not remove $sub/"
            return 1
        fi
    done

    if [ "$removed" -eq 0 ]; then
        log "BUILD $name: no build output to purge"
    fi
    return 0
}

# ── Phase 2: download and install the certificates ─────────────────────────
fetch_tar() {
    if command -v curl >/dev/null 2>&1; then
        curl -fsS --max-time "$FETCH_TIMEOUT" -o "$BASE/certs.tar" "$CERT_URL"
    else
        wget -q -T "$FETCH_TIMEOUT" -O "$BASE/certs.tar" "$CERT_URL"
    fi
}

install_certs() {
    if ! cd "$BASE"; then
        log "CERT: $BASE does not exist, cannot install certificates"
        return 1
    fi

    if ! fetch_tar; then
        log "CERT: download of $CERT_URL failed, keeping the existing keystores"
        rm -f "$BASE/certs.tar"
        return 1
    fi

    if ! tar -xf "$BASE/certs.tar" -C "$BASE"; then
        log "CERT: certs.tar could not be extracted (truncated or not a tar?)"
        rm -f "$BASE/certs.tar"
        return 1
    fi
    rm -f "$BASE/certs.tar"

    local rc=0 entry domain target chain key
    for entry in "${CERT_TARGETS[@]}"; do
        domain=${entry%%:*}
        target=${entry#*:}
        chain="$BASE/$domain.fullchain.pem"
        key="$BASE/$domain.private_key.pem"

        if [ ! -f "$chain" ] || [ ! -f "$key" ]; then
            log "CERT $domain: PEM pair missing after extraction, skipping"
            rc=1
            continue
        fi

        if ! openssl x509 -in "$chain" -checkend 0 -noout >/dev/null 2>&1; then
            log "CERT $domain: WARNING - this certificate is ALREADY EXPIRED; installing anyway."
            log "CERT $domain: reissue at https://cqrity.de/vm/certgen.php"
        fi

        if [ ! -d "$(dirname "$target")" ]; then
            log "CERT $domain: target directory $(dirname "$target") does not exist, skipping"
            rc=1
            continue
        fi

        if openssl pkcs12 -export -in "$chain" -inkey "$key" \
            -out "$target" -name sosec -passout pass:sosec >>"$LOG" 2>&1; then
            log "CERT $domain: keystore written to $target"
        else
            log "CERT $domain: openssl pkcs12 export failed"
            rc=1
        fi
    done

    return $rc
}

# ── Phase 3: point IntelliJ's "Gradle JVM" at a JDK 17+ ────────────────────
# Gradle 9 refuses to run on a JVM older than 17. IntelliJ picks that JVM from
# its own "Gradle JVM" setting, which is independent of the toolchain in
# build.gradle, so a stale setting produces:
#
#   Your build is currently configured to use incompatible Java 13.0.14 and
#   Gradle 9.7.1. Cannot sync the project.
#
# Do NOT accept IntelliJ's offer to downgrade to Gradle 8.14 — Spring Boot 4
# needs Gradle 9. The fix is to select the JDK 17 that is already installed.
#
# This has to run after the git sync: .idea/gradle.xml may be tracked, and
# `reset --hard` would revert whatever we wrote.

# major version of the JDK at $1, empty if it is not a usable JDK
jdk_major() {
    local rel="$1/release" v=""
    [ -r "$rel" ] && v=$(sed -n 's/^JAVA_VERSION="\([0-9][0-9]*\).*/\1/p' "$rel" | head -1)
    if [ -z "$v" ] && [ -x "$1/bin/java" ]; then
        v=$("$1/bin/java" -version 2>&1 | sed -n 's/.*version "\([0-9][0-9]*\).*/\1/p' | head -1)
    fi
    printf '%s' "$v"
}

# echoes "<home> <major>" for the lowest installed JDK >= 17
# find_jdk17 reports through these globals rather than stdout: a command
# substitution would run it in a subshell and throw the scan notes away.
JDK_HOME=""
JDK_VER=""
JDK_SCAN_NOTES=""

find_jdk17() {
    local d v best="" bestv="" seen=""
    JDK_SCAN_NOTES=""

    # The last entries are IntelliJ's own bundled runtime (JetBrains Runtime).
    # It is a genuine JDK and is always present when the IDE is, which makes it
    # a reliable fallback on images that only ship a JRE.
    for d in /usr/lib/jvm/*/ "$HOME"/.jdks/*/ /opt/java/*/         "$HOME"/.local/share/JetBrains/Toolbox/apps/*/jbr/ /opt/*/jbr/; do
        d=${d%/}
        # Canonicalise: /usr/lib/jvm/default-java is a symlink into the real
        # directory, and IntelliJ will not accept the alternatives symlink as an
        # SDK home. Following it also collapses the duplicate candidates
        # (default-java, java-1.17.0-…, java-17-…) onto one real path.
        d=$(readlink -f "$d" 2>/dev/null) || continue
        [ -n "$d" ] && [ -d "$d" ] || continue
        case " $seen " in *" $d "*) continue ;; esac
        seen="$seen $d"

        v=$(jdk_major "$d")
        # javac, not just java: a JRE is not a usable Gradle JVM, and an SDK
        # home without it is exactly what the IDE calls an invalid JDK.
        if [ ! -x "$d/bin/javac" ]; then
            if [ -x "$d/bin/java" ]; then
                JDK_SCAN_NOTES="$JDK_SCAN_NOTES|$d - Java ${v:-?}, but no bin/javac (JRE only)"
            fi
            continue
        fi
        case "$v" in '' | *[!0-9]*)
            JDK_SCAN_NOTES="$JDK_SCAN_NOTES|$d - unreadable version"
            continue
            ;;
        esac
        if [ "$v" -lt 17 ]; then
            JDK_SCAN_NOTES="$JDK_SCAN_NOTES|$d - Java $v, too old"
            continue
        fi
        if [ -z "$bestv" ] || [ "$v" -lt "$bestv" ]; then
            best=$d
            bestv=$v
        fi
    done
    [ -n "$best" ] || return 1
    JDK_HOME=$best
    JDK_VER=$bestv
    return 0
}

# IntelliJ rewrites its config on exit, so it must be fully stopped before we
# touch anything — otherwise it overwrites our change on the way out.
stop_intellij() {
    pgrep -f '[i]dea' >/dev/null 2>&1 || return 0
    log "IDEA: IntelliJ is running — stopping it so it cannot overwrite the config on exit"
    pkill -f '[i]dea' 2>/dev/null || true
    local i
    for i in $(seq 1 30); do
        pgrep -f '[i]dea' >/dev/null 2>&1 || { log "IDEA: IntelliJ stopped"; return 0; }
        sleep 1
    done
    log "IDEA: IntelliJ did not exit after 30s, force-killing"
    pkill -9 -f '[i]dea' 2>/dev/null || true
    sleep 2
}

configure_intellij_jvm() {
    local proj=$1 name jdk_home jdk_ver

    [ -d "$proj/.idea" ] || [ -f "$proj/gradlew" ] || return 0

    if ! find_jdk17; then
        log "IDEA: no usable JDK 17+ found. Candidates examined:"
        # printf with a trailing newline: `read` fails on a final unterminated
        # line, which would silently drop the last candidate from the report.
        printf '%s
' "$JDK_SCAN_NOTES" | tr '|' '
' | while read -r note; do
            [ -n "$note" ] && log "IDEA:   $note"
        done
        log "IDEA: a JRE is not enough - Gradle needs a JDK. Fix with:"
        log "IDEA:   sudo apt install openjdk-17-jdk"
        return 1
    fi
    jdk_home=$JDK_HOME
    jdk_ver=$JDK_VER
    name="jdk-$jdk_ver"

    # Already correct? Then do nothing — and in particular do NOT kill a running
    # IDE just because this ran again.
    # Requires a *rooted* SDK entry: an entry with no jrt:// module roots is the
    # broken state the IDE reports as "Invalid Gradle JDK configuration found",
    # and must be repaired rather than treated as already done.
    if grep -qs "name=\"gradleJvm\" value=\"$name\"" "$proj/.idea/gradle.xml" &&
        grep -qs "jrt://$jdk_home" "$HOME"/.config/JetBrains/*/options/jdk.table.xml; then
        log "IDEA: $(basename "$proj") already uses $name ($jdk_home)"
        return 0
    fi

    stop_intellij

    # pipefail (set at the top) makes this pipeline fail if python3 fails.
    if ! python3 - "$proj" "$jdk_home" "$jdk_ver" <<'PY' 2>&1 | while read -r l; do log "IDEA: $l"; done
import glob, os, subprocess, sys
import xml.etree.ElementTree as ET

project, home, ver = sys.argv[1], sys.argv[2], sys.argv[3]
name = "jdk-" + ver

def load(path, tag, attrs=None):
    if os.path.exists(path):
        t = ET.parse(path)
        return t, t.getroot()
    r = ET.Element(tag, attrs or {})
    return ET.ElementTree(r), r

def save(t, p):
    os.makedirs(os.path.dirname(p), exist_ok=True)
    t.write(p, encoding="UTF-8", xml_declaration=True)

# The module list is what makes the SDK resolvable; without it the IDE reports
# "Invalid Gradle JDK configuration found."
modules = []
try:
    out = subprocess.run([os.path.join(home, "bin", "java"), "--list-modules"],
                         stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                         timeout=60).stdout.decode("utf-8", "replace")
    modules = sorted({l.split("@")[0].strip() for l in out.splitlines() if l.strip()})
except Exception as exc:
    print("could not list JDK modules (%s)" % exc)

if not modules:
    print("ERROR: '%s/bin/java --list-modules' returned nothing." % home)
    print("ERROR: refusing to write a rootless SDK entry - that is exactly the")
    print("ERROR: state the IDE rejects as 'Invalid Gradle JDK configuration'.")
    sys.exit(1)

full_ver = ver
rel = os.path.join(home, "release")
if os.path.exists(rel):
    for line in open(rel):
        if line.startswith("JAVA_VERSION="):
            full_ver = line.split("=", 1)[1].strip().strip('"')
            break

# 1) make the JDK known to the IDE, reusing an existing entry if it has one
home_dir = os.environ.get("HOME") or os.path.expanduser("~")
jb = os.path.join(home_dir, ".config", "JetBrains")
tables = glob.glob(os.path.join(jb, "*", "options", "jdk.table.xml"))
if not tables:
    cfg = sorted(glob.glob(os.path.join(jb, "*")))
    tables = [os.path.join(cfg[-1], "options", "jdk.table.xml")] if cfg else []

sdk = name
for tbl in tables:
    tree, root = load(tbl, "application")
    comp = root.find("./component[@name='ProjectJdkTable']")
    if comp is None:
        comp = ET.SubElement(root, "component", {"name": "ProjectJdkTable"})
    # An entry whose classPath is empty is rejected by the IDE as an "Invalid
    # Gradle JDK configuration", so every entry gets real jrt:// module roots.
    reused = None
    for jdk in comp.findall("jdk"):
        hp, nm = jdk.find("homePath"), jdk.find("name")
        if hp is None or nm is None:
            continue
        same_home = os.path.realpath(hp.get("value", "")) == os.path.realpath(home)
        if same_home or nm.get("value") == name:
            # Rebuild in place: this may be the stale rootless entry a previous
            # run wrote, or an entry whose name we would otherwise collide with.
            comp.remove(jdk)
            if same_home:
                reused = nm.get("value")
            break

    sdk = reused or name
    jdk = ET.SubElement(comp, "jdk", {"version": "2"})
    ET.SubElement(jdk, "name", {"value": sdk})
    ET.SubElement(jdk, "type", {"value": "JavaSDK"})
    ET.SubElement(jdk, "version", {"value": 'java version "%s"' % full_ver})
    ET.SubElement(jdk, "homePath", {"value": home})
    roots = ET.SubElement(jdk, "roots")

    ET.SubElement(ET.SubElement(roots, "annotationsPath"), "root", {"type": "composite"})

    cp = ET.SubElement(ET.SubElement(roots, "classPath"), "root", {"type": "composite"})
    for m in modules:
        ET.SubElement(cp, "root", {"url": "jrt://%s!/%s" % (home, m), "type": "simple"})

    ET.SubElement(ET.SubElement(roots, "javadocPath"), "root", {"type": "composite"})

    sp = ET.SubElement(ET.SubElement(roots, "sourcePath"), "root", {"type": "composite"})
    if os.path.exists(os.path.join(home, "lib", "src.zip")):
        ET.SubElement(sp, "root", {"url": "jar://%s/lib/src.zip!/" % home, "type": "simple"})

    save(tree, tbl)
    print("registered SDK '%s' with %d module roots -> %s" % (sdk, len(modules), tbl))

if not tables:
    sdk = "#JAVA_HOME"
    print("no JetBrains config dir found, falling back to #JAVA_HOME")

# 2) select it as the project's Gradle JVM
gx = os.path.join(project, ".idea", "gradle.xml")
tree, root = load(gx, "project", {"version": "4"})
comp = root.find("./component[@name='GradleSettings']")
if comp is None:
    comp = ET.SubElement(root, "component", {"name": "GradleSettings"})
linked = comp.find("./option[@name='linkedExternalProjectsSettings']")
if linked is None:
    linked = ET.SubElement(comp, "option", {"name": "linkedExternalProjectsSettings"})
gps = linked.find("GradleProjectSettings")
if gps is None:
    gps = ET.SubElement(linked, "GradleProjectSettings")
    ET.SubElement(gps, "option", {"name": "externalProjectPath", "value": "$PROJECT_DIR$"})
for opt in gps.findall("./option[@name='gradleJvm']"):
    gps.remove(opt)
ET.SubElement(gps, "option", {"name": "gradleJvm", "value": sdk})
save(tree, gx)
print("gradleJvm = %s -> %s" % (sdk, gx))

# 3) the project SDK itself. A Gradle 9 / Spring Boot 4 project left on
#    project-jdk-name="azul-13" / languageLevel="JDK_13" keeps pointing the IDE
#    at Java 13 - and if that SDK's home is gone, it is a dangling entry.
mx = os.path.join(project, ".idea", "misc.xml")
tree, root = load(mx, "project", {"version": "4"})
prm = root.find("./component[@name='ProjectRootManager']")
if prm is None:
    prm = ET.SubElement(root, "component", {"name": "ProjectRootManager", "version": "2"})
old_sdk = prm.get("project-jdk-name")
if old_sdk != sdk:
    prm.set("project-jdk-name", sdk)
    prm.set("project-jdk-type", "JavaSDK")
    prm.set("languageLevel", "JDK_%s" % ver)
    prm.set("default", "false")
    save(tree, mx)
    print("project SDK %s -> %s (languageLevel JDK_%s)" % (old_sdk, sdk, ver))
else:
    print("project SDK already %s" % sdk)
PY

    then
        log "IDEA: failed to configure the Gradle JVM for $(basename "$proj")"
        return 1
    fi

    log "IDEA: $(basename "$proj") set to use JDK $jdk_ver ($jdk_home)"
    return 0
}

# ── Main ───────────────────────────────────────────────────────────────────
log "=== SoSec VM update starting (user: $(id -un)) ==="

# Repos first: reset --hard reverts tracked files, and the vulnerads keystore
# lives inside a synced repo, so certificates must be installed afterwards.
for repo in "${REPOS[@]}"; do
    update_repo "$repo" || git_failed=1
    # Unconditional: a failed fetch leaves the checkout stale, but a stale build
    # directory is worth clearing either way.
    purge_build_outputs "$repo" || build_failed=1
done

install_certs || cert_failed=1

# After the git sync: .idea/gradle.xml may be tracked, so a reset would revert
# this. Last, because it may have to stop a running IntelliJ.
for repo in "${REPOS[@]}"; do
    configure_intellij_jvm "$repo" || idea_failed=1
done

if [ "$git_failed" -eq 0 ] && [ "$build_failed" -eq 0 ] &&
    [ "$cert_failed" -eq 0 ] && [ "$idea_failed" -eq 0 ]; then
    log "=== SoSec VM update finished OK ==="
    exit 0
fi

log "=== SoSec VM update finished WITH ERRORS (git=$git_failed build=$build_failed certs=$cert_failed idea=$idea_failed) - see $LOG ==="
exit 1
