# Gamification G1 — Consolidation du socle XP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebaser, vérifier, relire et merger la branche existante `feature/gamification-phase-1` (socle XP/niveaux, 12 commits) dans `develop`.

**Architecture:** Aucun nouveau code n'est conçu dans cette phase : on intègre le module `functional/gamification` existant (ledger XP idempotent, LevelCurve, règles taguées, événements de sync, page profil, API REST). Le travail = rebase sur `develop`, vérification complète (Pint, Larastan L7, suite PHPUnit), revue de code adversariale du diff entier, correctifs TDD si findings, PR.

**Tech Stack:** Laravel 13 / PHP 8.4, module layer OSDD `functional/gamification`, PHPUnit 12, Pint, Larastan L7, worktree git existant `.claude/worktrees/gamification-phase-1`.

## Global Constraints

- Commits : message d'une phrase max, en anglais, préfixé gitmoji ; un commit par changement logique ; push après chaque commit.
- Jamais de commit/push sur `main`. La PR cible `develop`.
- Pas de commentaires dans le code, docstrings d'une phrase en anglais uniquement ; pas de try-catch ; ids en ULID.
- `vendor/bin/pint --dirty --format agent` avant toute finalisation ; Larastan niveau 7 sans erreur.
- Tests : PHPUnit uniquement (pas de Pest), `php artisan test --compact`, factories pour les modèles.
- Worktree : les quatre SDK path-repos doivent être symlinés dans `.claude/worktrees/` et `.env` + `public/build` copiés depuis le repo principal avant que la suite passe (voir Task 1).

---

### Task 1: Rebaser la branche et préparer l'environnement du worktree

**Files:**
- Modify: aucun fichier source — opérations git et environnement dans `/home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/gamification-phase-1`

**Interfaces:**
- Consumes: branche `feature/gamification-phase-1` (12 commits au-dessus de `4c9d8fb`), `develop` (contient `3434ce2`).
- Produces: worktree rebasé sur `origin/develop`, environnement de test fonctionnel (vendor, .env, public/build), pour les Tasks 2-5.

- [ ] **Step 1: Rebaser sur develop dans le worktree**

```bash
cd /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/gamification-phase-1
git fetch origin
git rebase origin/develop
```

Expected: `Successfully rebased and updated refs/heads/feature/gamification-phase-1.` (le seul commit de develop ajoute des docs et supprime deux fichiers racine — aucun chevauchement avec les 72 fichiers de la branche). En cas de conflit inattendu : le résoudre en conservant les deux intentions, jamais `--skip`.

- [ ] **Step 2: Pousser la branche rebasée**

```bash
git push --force-with-lease origin feature/gamification-phase-1
```

Expected: push accepté. Si le garde-fou de session refuse le force-push, s'arrêter et demander à l'utilisateur de lancer `! git -C .claude/worktrees/gamification-phase-1 push --force-with-lease origin feature/gamification-phase-1`.

- [ ] **Step 3: Préparer l'environnement de test du worktree**

```bash
for d in SDK_Strava SDK_Weather SDK_GoogleCalendar SDK_Outlook; do ln -sfn /home/quentin/LaravelProjects/$d /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/$d; done
cd /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/gamification-phase-1
composer install --no-interaction
cp /home/quentin/LaravelProjects/Personnal_Dashboard/.env .env
cp -r /home/quentin/LaravelProjects/Personnal_Dashboard/public/build public/build
```

Expected: `composer install` termine sans erreur (les path-repos `../SDK_*` se résolvent via les symlinks) ; `.env` et `public/build/manifest.json` présents.

### Task 2: Vérification complète de la branche rebasée

**Files:**
- Test: toute la suite — `tests/Feature/Gamification/*` (16 fichiers) plus les tests modifiés `tests/Feature/{Sport,Health,Finance,Exploration,Dashboard}/*`

**Interfaces:**
- Consumes: worktree prêt (Task 1).
- Produces: état vérifié « vert » (ou liste d'échecs à corriger en Task 4) servant de baseline à la revue (Task 3).

- [ ] **Step 1: Lancer Pint sur le worktree**

```bash
cd /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/gamification-phase-1
vendor/bin/pint --format agent
```

Expected: aucun fichier modifié. Si Pint corrige des fichiers : committer `🎨 apply pint formatting` et pousser.

- [ ] **Step 2: Lancer Larastan**

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

Expected: `[OK] No errors`. Toute erreur = finding à traiter en Task 4.

- [ ] **Step 3: Lancer la suite de tests complète**

```bash
php artisan test --compact
```

Expected: 0 échec (la branche était verte avant rebase ; le rebase n'apporte que des docs). Tout échec = finding à traiter en Task 4.

### Task 3: Revue de code complète du diff de la branche

**Files:**
- Modify: aucun — revue en lecture seule du diff `origin/develop...feature/gamification-phase-1` (72 fichiers, ~3200 lignes)

**Interfaces:**
- Consumes: branche rebasée et vérifiée (Tasks 1-2).
- Produces: liste de findings vérifiés, classés bloquant / à corriger / cosmétique, consommée par la Task 4.

- [ ] **Step 1: Lancer la revue**

Depuis le worktree, invoquer le skill `code-review` (effort high) sur le diff `origin/develop...HEAD`, avec une attention particulière aux axes suivants :
- idempotence réelle du ledger (`XpEntry` : rejouer `ProcessUserGamificationJob` ne double aucun award, y compris les awards par période et les fenêtres glissantes) ;
- concurrence (locks du recalculate, `WithoutOverlapping`, doubles dispatches d'événements de sync) ;
- scoping utilisateur de l'API REST (`GamificationApiScopeTest` couvre-t-il index/show/filtres ?) ;
- cascade `UserDeleting` (purge complète `xp_entries` + `player_profiles`) ;
- conventions repo (ULID, pas de commentaires, pas de try-catch, docstrings une phrase, enums TitleCase) ;
- cohérence de la config `gamification.php` (barèmes, caps anti-farming) avec ce que les règles consomment.

- [ ] **Step 2: Trier les findings**

Classer chaque finding : bloquant (bug, faille de scoping, non-idempotence), à corriger (convention violée, test manquant), cosmétique (reporter en G6). Consigner la liste dans le rapport de tâche pour la Task 4.

### Task 4: Correctifs issus de la revue (boucle TDD par finding)

**Files:**
- Modify: fichiers pointés par les findings dans `functional/gamification/src/**` et `tests/Feature/Gamification/**`
- Test: un test par correctif comportemental

**Interfaces:**
- Consumes: liste de findings triée (Task 3), plus tout échec résiduel des Tasks 2.
- Produces: branche verte, findings bloquants et « à corriger » tous résolus, prête pour la PR (Task 5).

- [ ] **Step 1: Pour chaque finding bloquant ou à corriger, dérouler la boucle suivante**

Boucle par finding (un commit par finding) :
1. Écrire un test PHPUnit qui reproduit le problème (dans le fichier de test existant du composant concerné ; factories obligatoires) ; `php artisan test --compact --filter=<nouveauTest>` doit échouer.
2. Corriger le code minimal dans `functional/gamification/src/`.
3. `php artisan test --compact --filter=<nouveauTest>` doit passer, puis relancer le fichier de test entier.
4. `vendor/bin/pint --dirty --format agent`.
5. `git add <fichiers> && git commit -m "🐛 <one sentence in English>" && git push`.

Pour un finding purement conventionnel (pas de comportement) : pas de nouveau test, correctif + Pint + commit `🎨`/`♻️`.

- [ ] **Step 2: Si aucun finding bloquant ou à corriger**

Marquer la tâche complétée sans commit et passer à la Task 5.

### Task 5: Vérification finale et ouverture de la PR

**Files:**
- Create: PR GitHub `feature/gamification-phase-1` → `develop`

**Interfaces:**
- Consumes: branche verte et relue (Tasks 2-4).
- Produces: PR ouverte, prête pour le merge utilisateur (Task 6).

- [ ] **Step 1: Vérification finale complète**

```bash
cd /home/quentin/LaravelProjects/Personnal_Dashboard/.claude/worktrees/gamification-phase-1
vendor/bin/pint --format agent && vendor/bin/phpstan analyse --memory-limit=1G && php artisan test --compact
```

Expected: Pint sans modification, Larastan 0 erreur, suite 0 échec. Copier la sortie réelle dans le rapport (verification-before-completion).

- [ ] **Step 2: Ouvrir la PR**

```bash
gh pr create --base develop --head feature/gamification-phase-1 --title "✨ Gamification phase 1: XP ledger, levels and player profile" --body "$(cat <<'EOF'
## Summary
- functional/gamification module: idempotent XP ledger, level curve, player profile
- six tagged XP rules (sport, health, finance, moto, todo, exploration) with anti-farming caps
- sync completion events + listener pipeline + daily sweep and weekly backfill
- player profile page, dashboard tile, read-only REST API

## Test plan
- [ ] pint clean, larastan L7 clean, full suite green (see task report)
- [ ] review findings addressed

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Expected: URL de PR retournée ; la reporter à l'utilisateur.

### Task 6: Merge (gate utilisateur)

**Files:**
- Modify: aucun — action GitHub

**Interfaces:**
- Consumes: PR ouverte (Task 5).
- Produces: socle XP mergé dans `develop`, point de départ des phases G2+.

- [ ] **Step 1: Demander le merge à l'utilisateur**

La protection de `develop` exige une review humaine et des commits signés : présenter la PR à l'utilisateur et l'inviter à merger (squash-merge, format `(#N)` du repo). Ne pas tenter `gh pr merge --admin` sans son accord explicite.

- [ ] **Step 2: Après merge, nettoyer**

```bash
cd /home/quentin/LaravelProjects/Personnal_Dashboard
git fetch origin && git checkout develop && git pull
git worktree remove .claude/worktrees/gamification-phase-1
git branch -d feature/gamification-phase-1
```

Expected: `develop` contient le module gamification ; worktree et branche locale supprimés (la G2 repartira d'un worktree neuf).
