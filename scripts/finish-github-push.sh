#!/usr/bin/env bash
# Dokończenie push na GitHub po oczyszczeniu historii z dużych plików.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

SSH_KEY="${HOME}/.ssh/id_ed25519_sor41"
SSH_PUB="${SSH_KEY}.pub"
REPO="MarcinJaszczynski/sor41"

log() { echo "[push] $*"; }

ensure_ssh_key() {
  if [[ ! -f "${SSH_KEY}" ]]; then
    log "Generuję klucz SSH..."
    ssh-keygen -t ed25519 -C "dev1@sor41-push" -f "${SSH_KEY}" -N ""
  fi

  mkdir -p "${HOME}/.ssh"
  chmod 700 "${HOME}/.ssh"
  chmod 600 "${SSH_KEY}" "${SSH_PUB}"

  if [[ ! -f "${HOME}/.ssh/config" ]] || ! grep -q "id_ed25519_sor41" "${HOME}/.ssh/config" 2>/dev/null; then
    cat >> "${HOME}/.ssh/config" <<EOF

Host github.com
  HostName github.com
  User git
  IdentityFile ${SSH_KEY}
  IdentitiesOnly yes
EOF
    chmod 600 "${HOME}/.ssh/config"
  fi
}

add_deploy_key_via_api() {
  local token="$1"
  local title="dev1-sor41-push-$(hostname -s 2>/dev/null || echo vps)"
  local key
  key="$(cat "${SSH_PUB}")"

  log "Dodaję deploy key przez GitHub API..."
  curl -sf -X POST \
    -H "Authorization: Bearer ${token}" \
    -H "Accept: application/vnd.github+json" \
    "https://api.github.com/repos/${REPO}/keys" \
    -d "$(jq -n --arg title "${title}" --arg key "${key}" '{title: $title, key: $key, read_only: false}')" \
    >/dev/null
  log "Deploy key dodany."
}

wait_for_github_ssh() {
  local tries=0
  while [[ "${tries}" -lt 3 ]]; do
    if ssh -o BatchMode=yes -T git@github.com 2>&1 | grep -qi "successfully authenticated"; then
      return 0
    fi
    tries=$((tries + 1))
    sleep 1
  done
  return 1
}

main() {
  ensure_ssh_key

  if [[ "${GITHUB_TOKEN:-}" != "" ]] || [[ "${GH_TOKEN:-}" != "" ]]; then
    TOKEN="${GITHUB_TOKEN:-${GH_TOKEN:-}}"
    if ! wait_for_github_ssh; then
      add_deploy_key_via_api "${TOKEN}" || log "Deploy key może już istnieć — kontynuuję."
      sleep 2
    fi
  fi

  git remote set-url origin "git@github.com:${REPO}.git"

  if ! wait_for_github_ssh; then
    echo ""
    echo "Brak autoryzacji SSH do GitHub. Dodaj klucz publiczny:"
    echo ""
    cat "${SSH_PUB}"
    echo ""
    echo "Opcja A — Deploy key (zapis):"
    echo "  https://github.com/${REPO}/settings/keys"
    echo "  Allow write access: TAK"
    echo ""
    echo "Opcja B — automatycznie (PAT z uprawnieniem repo):"
    echo "  GITHUB_TOKEN=ghp_... ./scripts/finish-github-push.sh"
    echo ""
    exit 1
  fi

  git branch -f develop main

  log "Push develop..."
  git push -u origin develop

  log "Push main (--force-with-lease)..."
  git push --force-with-lease origin main

  log "Gotowe."
  git log -1 --oneline main
  git log -1 --oneline develop
}

main "$@"
