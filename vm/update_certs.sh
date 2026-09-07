#!/usr/bin/env bash
#
# SoSec training VM boot script.
#
# Runs on every VM boot as:
#   curl -s https://cqrity.de/vm/update_certs.sh | bash -s
#
# That command is baked into the VM images and cannot be changed, so this file
# is the only lever for changing what happens at boot. The name is historical —
# it now does two jobs:
#
#   1. sync the exercise repos from GitHub  (see update_repo)
#   2. install the current TLS certificates (see install_certs)
#
# Regenerate the certificates first at https://cqrity.de/vm/certgen.php, which
# rebuilds the certs.tar this script downloads.

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

# ── Main ───────────────────────────────────────────────────────────────────
log "=== SoSec VM update starting (user: $(id -un)) ==="

# Repos first: reset --hard reverts tracked files, and the vulnerads keystore
# lives inside a synced repo, so certificates must be installed afterwards.
for repo in "${REPOS[@]}"; do
    update_repo "$repo" || git_failed=1
done

install_certs || cert_failed=1

if [ "$git_failed" -eq 0 ] && [ "$cert_failed" -eq 0 ]; then
    log "=== SoSec VM update finished OK ==="
    exit 0
fi

log "=== SoSec VM update finished WITH ERRORS (git=$git_failed certs=$cert_failed) - see $LOG ==="
exit 1
