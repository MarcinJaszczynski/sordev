# 3. Git workflow

## Gałęzie

| Gałąź | Cel | Kiedy używać | Deploy |
|-------|-----|--------------|--------|
| `main` | Produkcja | Kod gotowy do wdrożenia | VPS PROD (z approval) |
| `develop` | Integracja / staging | Merge feature po review | VPS TEST (auto) |
| `feature/*` | Nowa funkcja | `feature/settlement-export` od `develop` | brak |
| `release/*` | Stabilizacja | `release/4.2.0` od `develop`, tylko bugfixy | opcjonalnie TEST |
| `hotfix/*` | Pilna poprawka prod | `hotfix/invoice-ksef` od `main` | PROD po merge |

## Przykładowy flow

### Nowa funkcja

```bash
git checkout develop
git pull origin develop
git checkout -b feature/nowa-funkcja
# ... praca ...
git push -u origin feature/nowa-funkcja
# → Pull Request do develop
# → CI Verify musi przejść
# → merge → auto deploy staging
```

### Release do produkcji

```bash
git checkout develop
git pull
git checkout -b release/4.2.0
# tylko poprawki stabilizacyjne, wersja w composer.json jeśli dotyczy
git push -u origin release/4.2.0
# → PR release/4.2.0 → main
# → review + approval production environment
# → merge → deploy prod
# → merge release/4.2.0 → develop (back-merge)
```

### Hotfix produkcyjny

```bash
git checkout main
git pull
git checkout -b hotfix/krytyczny-blad
# ... fix ...
git push -u origin hotfix/krytyczny-blad
# → PR do main (pilny review)
# → merge → deploy prod
# → merge/cherry-pick do develop
```

## Migracja master → main

Repozytorium standaryzuje się na **`main`** jako gałęzi produkcyjnej.

### Kroki (jednorazowo, maintainer)

```bash
# Upewnij się, że main i master są zsynchronizowane
git checkout main
git pull origin main
git merge origin/master   # jeśli master miał unikalne commity

# Utwórz develop z main
git checkout -b develop
git push -u origin develop

# Ustaw main jako domyślną gałąź w GitHub:
# Settings → General → Default branch → main

# Opcjonalnie: usuń master po migracji zespołu
# git push origin --delete master
```

### Branch protection (GitHub Settings → Branches)

**main:**
- Require pull request before merging
- Require approvals: 1+
- Require status checks: `verify`
- Require conversation resolution
- Do not allow bypassing
- Restrict force pushes

**develop:**
- Require pull request before merging
- Require status checks: `verify`
- Restrict force pushes

### GitHub Environments

| Environment | Branch | Protection |
|-------------|--------|------------|
| `staging` | develop | brak (auto deploy) |
| `production` | main | Required reviewers (1–2) |

## Konwencje commitów

Zgodnie z istniejącym stylem repo — opisowe commity po polsku lub angielsku, np.:

```
fix(settlement): poprawka kalkulacji marży
feat(pilot): eksport PDF programu
```

## Tagi wersji (opcjonalnie)

```bash
git tag -a v4.2.0 -m "Release 4.2.0"
git push origin v4.2.0
```

Deploy prod z tagu: GitHub Actions → Deploy Production → workflow_dispatch → `ref: v4.2.0`.
