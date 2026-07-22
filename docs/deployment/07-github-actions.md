# 7. GitHub Actions — CI/CD

## Workflow

| Plik | Trigger | Cel |
|------|---------|-----|
| `verify.yml` | PR, push main/develop/master | Testy + build |
| `verify-reusable.yml` | workflow_call | Wspólny job verify |
| `deploy-staging.yml` | push develop | Deploy VPS TEST |
| `deploy-production.yml` | push main, workflow_dispatch | Deploy VPS PROD |

## Secrets — Staging

W GitHub: **Settings → Secrets and variables → Actions**

| Secret | Opis | Przykład |
|--------|------|----------|
| `STAGING_SSH_HOST` | IP lub hostname VPS TEST | `203.0.113.10` |
| `STAGING_SSH_USER` | Użytkownik deploy | `deploy` |
| `STAGING_SSH_KEY` | Klucz prywatny SSH (PEM) | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `STAGING_SSH_PORT` | Port SSH | `22` |
| `STAGING_SSH_KNOWN_HOSTS` | Fingerprint serwera | wpis z `ssh-keyscan` |
| `STAGING_APP_PATH` | Ścieżka APP_BASE | `/var/www/travel-office` |

## Secrets — Production

| Secret | Opis |
|--------|------|
| `PROD_SSH_HOST` | IP VPS PROD |
| `PROD_SSH_USER` | `deploy` |
| `PROD_SSH_KEY` | Osobny klucz (nie ten sam co staging!) |
| `PROD_SSH_PORT` | `22` |
| `PROD_SSH_KNOWN_HOSTS` | Fingerprint prod |
| `PROD_APP_PATH` | `/var/www/travel-office` |

## Known Hosts

Na maszynie lokalnej (jednorazowo):

```bash
ssh-keyscan -p 22 -H staging.example.com
ssh-keyscan -p 22 -H app.example.com
```

Cały output wklej do secret `STAGING_SSH_KNOWN_HOSTS` / `PROD_SSH_KNOWN_HOSTS`.

**Nigdy** nie używaj `StrictHostKeyChecking=no` w produkcji.

## Deploy Key (read-only)

1. Na serwerze (jako deploy): `ssh-keygen -t ed25519 -C "github-deploy-staging" -f ~/.ssh/github_deploy -N ""`
2. Publiczny klucz → GitHub repo → **Settings → Deploy keys → Add deploy key** (read-only)
3. Prywatny klucz → secret `STAGING_SSH_KEY`

Osobna para kluczy dla PROD.

Alternatywa: klucz w GitHub Actions secrets (bez deploy key na repo) — Actions łączy się tylko przez SSH do VPS, kod idzie przez rsync z runnera (obecna implementacja).

## Environment protection

### staging

- Auto deploy po push `develop`
- Bez required reviewers

### production

- **Required reviewers:** 1–2 osoby
- Deploy po merge do `main`
- Migracje: tylko przez `workflow_dispatch` z `with_migrate=true`

## Przebieg deploy-staging.yml

1. `verify` (reusable) — testy
2. `composer install --no-dev`
3. `npm ci && npm run build`
4. rsync kodu → `VPS:incoming/<SHA>/`
5. rsync `deploy/` → `VPS:deploy/`
6. SSH: `deploy.sh staging --ref SHA --source-dir incoming/SHA --with-migrate`
7. Usunięcie `incoming/SHA`

## Przebieg deploy-production.yml

Jak staging, ale:
- Environment `production` (approval)
- Domyślnie **bez** `--with-migrate`
- `concurrency: cancel-in-progress: false` — nie przerywa trwającego prod deploy

## Ręczny deploy prod

GitHub → Actions → Deploy Production → Run workflow:
- `ref`: opcjonalny SHA/tag
- `with_migrate`: true/false

## Bezpieczeństwo

| Zasada | Implementacja |
|--------|---------------|
| Osobne klucze SSH | STAGING_* vs PROD_* |
| Klucze tylko w Secrets | Nigdy w repo |
| Known hosts | Weryfikacja fingerprint |
| Deploy user | Bez hasła, tylko key |
| Minimalne sudo | Tylko reload FPM + supervisor |
| Branch protection | main + develop |
| Brak sekretów w logach | Actions maskuje secrets |

## Concurrency

```yaml
concurrency:
  group: deploy-staging
  cancel-in-progress: true   # staging: anuluj poprzedni
```

Prod: `cancel-in-progress: false` — dokończ bieżący deploy.

## Wymagane uprawnienia repo

Actions muszą mieć włączone: **Settings → Actions → General → Workflow permissions → Read and write**.
