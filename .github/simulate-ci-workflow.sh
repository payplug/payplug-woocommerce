#!/usr/bin/env bash
# Simulates the full CI workflow locally using `act`.
#
# What is tested:
#   ① CI on feature branch PR     → github-actions.yml (pull_request)
#   ② wooco-release on develop    → wooco-release.yml  (push, reuse-qa-artifact-develop)
#   ③ CI on release branch PR     → github-actions.yml (pull_request)
#   ④ wooco-release on master     → wooco-release.yml  (pull_request, reuse-qa-artifact)
#   ⑤ wooco-release on tag push   → wooco-release.yml  (push tag, build-prod-zip)
#
# Limitation: dawidd6/action-download-artifact needs real GitHub API.
#   → The download step is replaced by a local pre-seeded artifact directory,
#     so the flatten + re-upload steps run exactly as they would in production.
#
# Usage: bash simulate-ci-workflow.sh [--tag 2.17.3] [--step 1-5]

set -euo pipefail

RELEASE_VERSION="2.17.3"
RUN_STEP=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --tag)  RELEASE_VERSION="$2"; shift 2 ;;
    --step) RUN_STEP="$2";        shift 2 ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EVENTS_DIR="$REPO_ROOT/.github/act-events"
ARTIFACT_DIR="$REPO_ROOT/.act-artifacts"
WORKFLOW_DIR="$REPO_ROOT/.github/workflows"

# ─── Colors ───────────────────────────────────────────────────────────────────
BLUE='\033[0;34m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; BOLD='\033[1m'; NC='\033[0m'
step()  { echo -e "\n${BOLD}${BLUE}▶ Step $*${NC}"; }
ok()    { echo -e "  ${GREEN}✓${NC} $*"; }
warn()  { echo -e "  ${YELLOW}!${NC} $*"; }
die()   { echo -e "\n${RED}✗ $*${NC}" >&2; exit 1; }
hr()    { echo -e "${BLUE}────────────────────────────────────────────────${NC}"; }

# ─── Helpers ──────────────────────────────────────────────────────────────────

# Seed a fake artifact directory that mimics dawidd6's download output:
#   artifact/<artifact-name>/payplug-woocommerce/ (sources)
seed_artifact() {
  local artifact_name="payplug-woocommerce-${RELEASE_VERSION}-QA"
  rm -rf "$ARTIFACT_DIR"
  mkdir -p "$ARTIFACT_DIR/$artifact_name/payplug-woocommerce/src"
  echo "<?php // payplug-woocommerce $RELEASE_VERSION" \
    > "$ARTIFACT_DIR/$artifact_name/payplug-woocommerce/src/payplug.php"
  echo "Stable tag: $RELEASE_VERSION" \
    > "$ARTIFACT_DIR/$artifact_name/payplug-woocommerce/readme.txt"
  ok "Seeded mock artifact: $ARTIFACT_DIR/"
  echo ""
  find "$ARTIFACT_DIR" | sed "s|$ARTIFACT_DIR||" | sort
  echo ""
}

# Run act with a given workflow, event type, event file, and optional job filter
run_act() {
  local event_type="$1" event_file="$2" job_filter="$3"
  local extra_args=()

  # Mount the pre-seeded artifact dir into the container as the workspace artifact path
  if [[ -d "$ARTIFACT_DIR" ]]; then
    extra_args+=(--env "ARTIFACT_PATH=/github/workspace/.act-artifacts")
    extra_args+=(--bind)
  fi

  [[ -n "$job_filter" ]] && extra_args+=(-j "$job_filter")

  act "$event_type" \
    --eventpath "$event_file" \
    --workflows "$WORKFLOW_DIR/wooco-release.yml" \
    --artifact-server-path "$ARTIFACT_DIR" \
    --container-architecture linux/amd64 \
    "${extra_args[@]}" \
    2>&1 || true   # act exits non-zero on skipped jobs too — we handle output manually
}

# ─── Pre-flight ───────────────────────────────────────────────────────────────
hr
echo -e "${BOLD}CI Workflow Simulation — wooco-release.yml${NC}"
echo -e "Release version : ${BOLD}$RELEASE_VERSION${NC}"
[[ -n "$RUN_STEP" ]] && echo -e "Running only    : Step $RUN_STEP"
hr

command -v act >/dev/null 2>&1 || die "'act' not found — brew install act"
command -v docker >/dev/null 2>&1 || die "Docker not running — start Docker Desktop"
docker info >/dev/null 2>&1 || die "Docker daemon not responding — start Docker Desktop"
ok "act $(act --version | grep -o '[0-9.]*') ready"
ok "Docker running"

should_run() { [[ -z "$RUN_STEP" || "$RUN_STEP" == "$1" ]]; }

# ─── Step 1: CI on feature branch (github-actions.yml) ───────────────────────
if should_run 1; then
  hr
  step "1/5 — CI on feature branch PR (github-actions.yml)"
  warn "This calls payplug/template-ci reusable workflows — expect skipped/failed reusable jobs."
  warn "Focus: does the workflow trigger correctly for a PR to develop?"
  echo ""

  act pull_request \
    --eventpath "$EVENTS_DIR/push-develop.json" \
    --workflows "$WORKFLOW_DIR/github-actions.yml" \
    --container-architecture linux/amd64 \
    -j build-qa-zip \
    2>&1 || true

  ok "Step 1 done — check output above for build-qa-zip job"
fi

# ─── Step 2: wooco-release on develop (push trigger) ─────────────────────────
if should_run 2; then
  hr
  step "2/5 — wooco-release.yml on develop push (reuse-qa-artifact-develop)"
  echo ""
  echo -e "  ${YELLOW}Seeding mock artifact to simulate dawidd6 download:${NC}"
  seed_artifact

  warn "The 'Download most recent QA artifact' step will fail (needs real GitHub API)."
  warn "Focus: do the FLATTEN and RE-UPLOAD steps work correctly on the seeded artifact?"
  echo ""

  # Patch: pre-place the artifact at the path act uses
  mkdir -p "$REPO_ROOT/artifact"
  cp -r "$ARTIFACT_DIR"/. "$REPO_ROOT/artifact/"

  act push \
    --eventpath "$EVENTS_DIR/push-develop.json" \
    --workflows "$WORKFLOW_DIR/wooco-release.yml" \
    --container-architecture linux/amd64 \
    --artifact-server-path "$ARTIFACT_DIR" \
    -j reuse-qa-artifact-develop \
    2>&1 || true

  rm -rf "$REPO_ROOT/artifact"
  ok "Step 2 done — check output above for flatten step result"
fi

# ─── Step 3: CI on release branch (github-actions.yml) ───────────────────────
if should_run 3; then
  hr
  step "3/5 — CI on release branch PR (github-actions.yml)"
  warn "Same as step 1 — reusable workflows will be skipped."
  echo ""

  act pull_request \
    --eventpath "$EVENTS_DIR/pr-merged-master.json" \
    --workflows "$WORKFLOW_DIR/github-actions.yml" \
    --container-architecture linux/amd64 \
    -j build-qa-zip \
    2>&1 || true

  ok "Step 3 done"
fi

# ─── Step 4: wooco-release on master (PR merged) ─────────────────────────────
if should_run 4; then
  hr
  step "4/5 — wooco-release.yml on master PR merge (reuse-qa-artifact)"
  echo ""
  echo -e "  ${YELLOW}Seeding mock artifact:${NC}"
  seed_artifact

  mkdir -p "$REPO_ROOT/artifact"
  cp -r "$ARTIFACT_DIR"/. "$REPO_ROOT/artifact/"

  act pull_request \
    --eventpath "$EVENTS_DIR/pr-merged-master.json" \
    --workflows "$WORKFLOW_DIR/wooco-release.yml" \
    --container-architecture linux/amd64 \
    --artifact-server-path "$ARTIFACT_DIR" \
    -j reuse-qa-artifact \
    2>&1 || true

  rm -rf "$REPO_ROOT/artifact"
  ok "Step 4 done"
fi

# ─── Step 5: wooco-release on tag push (build-prod-zip) ──────────────────────
if should_run 5; then
  hr
  step "5/5 — wooco-release.yml on tag push (build-prod-zip)"
  warn "This calls payplug/template-ci reusable workflow — will be skipped by act."
  warn "Focus: does the job condition (startsWith refs/tags/) match correctly?"
  echo ""

  act push \
    --eventpath "$EVENTS_DIR/push-tag.json" \
    --workflows "$WORKFLOW_DIR/wooco-release.yml" \
    --container-architecture linux/amd64 \
    -j build-prod-zip \
    2>&1 || true

  ok "Step 5 done"
fi

# ─── Summary ──────────────────────────────────────────────────────────────────
hr
echo -e "\n${BOLD}${GREEN}Simulation complete.${NC}\n"
echo "What to look for in the output above:"
echo "  ✓ Job conditions matched the right jobs (no wrong jobs triggered)"
echo "  ✓ Flatten step: only one folder level remains after mv"
echo "  ✓ Re-upload step: artifact path is artifact/ with correct contents"
echo "  ✓ build-prod-zip: only fires on refs/tags/, not on develop push"
echo ""
echo "Run a single step with:  bash simulate-ci-workflow.sh --step 2"
hr
