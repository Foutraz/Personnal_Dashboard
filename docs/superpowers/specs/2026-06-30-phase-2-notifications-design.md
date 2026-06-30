# Phase 2 — Centre de notifications unifié

> Design auto-validé le 2026-06-30 (mode autonome). Troisième chantier de `ROADMAP_INTERCONNEXIONS.md`.
> Branche `feature/phase-2-notifications`, **empilée sur** `feature/phase-1-interconnections`.

## 1. Contexte & principe

Chaque module notifie déjà indépendamment via le canal `database` (+ `mail`) : `TaskReminderNotification` (todo, `everyFifteenMinutes`) et `ExpenseDueReminderNotification` (recurring-expenses, `dailyAt('08:00')`), envoyées par `$user->notify(...)` dans des commandes planifiées. Il n'y a aucune surface d'agrégation : la « cloche » du command deck.

**Principe directeur (roadmap) : on n'ajoute qu'un agrégateur de LECTURE.** Les modules continuent d'émettre exactement comme aujourd'hui ; on ne touche ni les `Notification`, ni les commandes, ni le scheduler. On lit la table framework `notifications` (canal database déjà utilisé, `Notifiable` déjà sur `User`).

## 2. Décisions clés (mode autonome)

| # | Décision | Raison |
|---|----------|--------|
| P2-D1 | Nouvelle couche technique `technical/notifications` | Concern transverse, pas un module functional ; pattern layer existant. |
| P2-D2 | Composant Livewire `NotificationCenter` (cloche + panneau) lisant `auth('web')->user()` | Agrégateur de lecture ; `Notifiable` fournit `notifications`/`unreadNotifications`/`markAsRead`. |
| P2-D3 | Rendu **générique** depuis le `data` de chaque notification, sans contrat par module | Read aggregator ; lookups souples (`data['title'] ?? data['label'] ?? class_basename(type)`), aucun import cross-module. |
| P2-D4 | **Pas** de préférences par module, **pas** de changement d'émission, **pas** de nouvelle table | « Les modules continuent d'émettre » ; les préférences toucheraient l'émission → hors principe + YAGNI. Reporté. |
| P2-D5 | Cloche intégrée au header `layouts/app.blade.php` (zone droite) | Surface naturelle du command deck. |
| P2-D6 | Pas de tuile dashboard notifications | La cloche EST la surface ; on garde le périmètre serré. |

## 3. Périmètre

**Dans le périmètre :**
- Couche `technical/notifications` (composer.json + `NotificationsServiceProvider` + ajout au `require` racine + `composer update`).
- Composant Livewire `NotificationCenter` : nombre de non-lues, liste des N dernières (10), `markAsRead(string $id)`, `markAllAsRead()`, scopé à l'utilisateur connecté ; `wire:poll.30s` pour le compteur.
- Vue cloche + panneau déroulant (Alpine), intégrée au header.
- Tests : compteur non-lues scopé user, `markAsRead` pose `read_at`, `markAllAsRead` vide les non-lues, isolation entre users.

**Hors périmètre (reporté / non fait) :**
- Préférences de notification par module (toucherait l'émission).
- Modification des `Notification`/commandes/scheduler existants.
- Nouvelle table (la table framework `notifications` suffit ; son `id` uuid est framework-owned — la convention ULID s'applique à NOS tables, pas à celle du framework).
- Temps réel via websockets (un `wire:poll` léger suffit).
- Tuile dashboard, intégrations externes (Phase 3).

## 4. Architecture

```
technical/notifications/
  composer.json                 (name technical/notifications, PSR-4 Technical\Notifications\, provider)
  src/Providers/NotificationsServiceProvider.php   (extends OsddServiceProvider ; loadViewsFrom ; Livewire::component('notification-center', NotificationCenter::class))
  src/Livewire/NotificationCenter.php
  resources/views/livewire/notification-center.blade.php
```
+ root `composer.json` require gains `"technical/notifications": "*"` ; `composer update technical/notifications` symlinke le package et auto-découvre le provider.

`NotificationCenter` (Livewire) :
- `mount()` / computed : `unreadCount` = `auth('web')->user()->unreadNotifications()->count()` ; `recent` = `auth('web')->user()->notifications()->latest()->limit(10)->get()`.
- `markAsRead(string $id): void` — marque la notification (scopée user) lue.
- `markAllAsRead(): void` — `auth('web')->user()->unreadNotifications->markAsRead()`.
- `render()` renvoie la vue ; pas de logique métier (modèle fin, pas de try/catch).

La vue mappe chaque `DatabaseNotification` génériquement : titre = `data['title'] ?? data['label'] ?? class_basename($notification->type)` ; ligne secondaire = montant/échéance si présents (`data['amount']`/`data['next_due_at']`/`data['due_at']`) ; horodatage relatif (`created_at->diffForHumans()`) ; état lu/non-lu ; clic = `markAsRead`. Bouton « Tout marquer comme lu ». Badge compteur sur l'icône cloche.

## 5. Intégration header

Dans `resources/views/layouts/app.blade.php`, zone droite (avant le badge « Online » ou entre lui et l'avatar), insérer `<livewire:notification-center />`. La cloche porte son propre dropdown Alpine, cohérent avec le menu utilisateur existant.

## 6. Tests (PHPUnit, factories + faker)

- `NotificationCenter` via `Livewire::actingAs($user,'web')->test(...)` :
  - compteur non-lues = nombre de notifications non-lues de l'utilisateur (et ignore celles d'un autre user) ;
  - `markAsRead($id)` pose `read_at` (la notification passe en lue) ;
  - `markAllAsRead()` met le compteur à 0 ;
  - la liste rendue affiche le titre d'une notification seedée.
- Seed des notifications : utiliser `$user->notify(new TaskReminderNotification($task))` (réutilise une vraie notification module) OU insérer directement via `DatabaseNotification`/`$user->notifications()->create([...])`. Préférer `notify()` avec une vraie notification pour rester proche du réel.
- Page authentifiée : `->get('/dashboard')->assertOk()` rend la cloche (proxy d'intégration header).
- Transverse : `pint --dirty`, Larastan niveau 7, `php artisan test --compact`.

## 7. Forme d'exécution

1. **Task 1** : couche `technical/notifications` complète — package + wiring composer + `NotificationCenter` + vue + tests (TDD). La résolution du composant via `Livewire::test('notification-center')` prouve que le package est correctement enregistré.
2. **Task 2** : intégration de la cloche dans le header (`layouts/app.blade.php`) + test de rendu page authentifiée. Passe `frontend-design`.
3. **Task 3** : vérification intégration (pint, Larastan niv. 7, suite complète).

## 8. Critères d'acceptation

- La cloche apparaît dans le header authentifié avec le compteur de non-lues de l'utilisateur.
- Marquer une notification (ou toutes) comme lue persiste `read_at` et met à jour le compteur, scopé à l'utilisateur.
- Aucune `Notification`/commande/scheduler existante modifiée ; aucune nouvelle table.
- `pint` propre, Larastan niveau 7 sans nouvelle erreur, suite verte.
