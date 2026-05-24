#!/usr/bin/env bash
# deploy.sh - Hostinger-friendly deployment helper (idempotent, dry-run capable)

set -euo pipefail
DRYRUN=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRYRUN=1; shift ;;
    *) echo "Unknown arg: $1"; exit 2;;
  esac
done

cmd() {
  if [[ $DRYRUN -eq 1 ]]; then
    echo "DRYRUN: $*"
  else
    echo "+ $*"
    eval "$@"
  fi
}

# Ensure required tools exist (best-effort)
if ! command -v php >/dev/null 2>&1; then
  echo "php is required but not found in PATH" >&2
  exit 1
fi

if [[ -d middleware && -f middleware/package.json ]]; then
  NEED_NPM=1
else
  NEED_NPM=0
fi

# 1) Ensure a hosting env file exists without overwriting local development config
if [[ ! -f .env.hosting && -f .env.hosting.example ]]; then
  cmd "cp .env.hosting.example .env.hosting"
fi

# 2) Ensure runtime dirs are writable
RUNTIME_DIRS=(uploads qr_codes backups)
for d in "${RUNTIME_DIRS[@]}"; do
  if [[ -d "$d" ]]; then
    cmd "chmod u+w -R $d || true"
  fi
done

# 3) Run migrations headlessly
cmd "php run_migrations.php"

# 4) Install middleware dependencies if present
if [[ $NEED_NPM -eq 1 ]]; then
  cmd "cd middleware && npm install && cd -"
fi

# 5) Run hardening checks if available
if [[ -f tests/run_hardening_checks.php ]]; then
  cmd "php tests/run_hardening_checks.php"
fi

echo "Deployment script completed"
