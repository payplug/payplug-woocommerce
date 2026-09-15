#!/usr/bin/env bash
# Retry helper for svn commands against plugins.svn.wordpress.org, which
# frequently drops connections (timeouts, "Connection reset by peer").
set -euo pipefail

svn_retry() {
  local max_attempts="${SVN_RETRY_MAX_ATTEMPTS:-5}"
  local delay="${SVN_RETRY_DELAY:-10}"
  local attempt=1

  until "$@"; do
    if (( attempt >= max_attempts )); then
      echo "::error::svn command failed after ${attempt} attempts: $*"
      return 1
    fi
    echo "::warning::svn command failed (attempt ${attempt}/${max_attempts}), retrying in ${delay}s..."
    sleep "$delay"
    attempt=$((attempt + 1))
    delay=$((delay * 2))
  done
}

# Same as svn_retry, but for commands that operate on a working copy (e.g.
# `svn ci`) and can be interrupted mid-transaction (e.g. "Connection reset by
# peer" during "Committing transaction..."). A dead connection at that point
# can leave the working copy locked, so a bare retry fails immediately with
# "working copy locked" instead of ever touching the network again. Run
# `svn cleanup` on the working copy before every attempt to guard against that.
svn_retry_wc() {
  local wc_path="$1"
  shift
  local max_attempts="${SVN_RETRY_MAX_ATTEMPTS:-5}"
  local delay="${SVN_RETRY_DELAY:-10}"
  local attempt=1

  until svn cleanup "$wc_path" && "$@"; do
    if (( attempt >= max_attempts )); then
      echo "::error::svn command failed after ${attempt} attempts: $*"
      return 1
    fi
    echo "::warning::svn command failed (attempt ${attempt}/${max_attempts}), retrying in ${delay}s..."
    sleep "$delay"
    attempt=$((attempt + 1))
    delay=$((delay * 2))
  done
}
