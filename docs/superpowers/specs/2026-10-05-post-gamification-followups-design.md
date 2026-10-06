# Correctifs post-gamification — design

> Date : 2026-10-05. Branche : `fix/post-gamification-followups` (depuis `develop`).
> Hors périmètre, détenu par `feature/anti-cheat-hardening` : `functional/gamification`, `functional/goals` et les
> règles de validation des ressources REST moto / sport / finance / todo / exploration. Rien n'y est modifié ici.
> Pile installée à la rédaction (lock) : Laravel 12.62, PHPUnit 11.5, Livewire 3.8.1, lomkit/laravel-rest-api 2.21.0 ; la montée de
> dépendances du §6 (décision de Quentin) porte lomkit/laravel-rest-api à 2.23.2.

## 1. Purge des notifications à la suppression d'un utilisateur

**Cause.** `UserDeleting` (déclenché par `User::deleting`, donc aussi sur la suppression douce) est écouté par un
listener par layer, mais aucun ne vide la table `notifications` : seul `DeleteUserGamificationData` supprime trois
types gamification. Les rappels de tâche (`TaskReminderNotification`) et de dépense
(`ExpenseDueReminderNotification`) d'un utilisateur supprimé restent en base.

**Décision.** Listener `Technical\Notifications\Listeners\DeleteUserNotifications` dans le layer qui lit les
notifications, branché par `$listen` + `loadListenEvent()` comme `technical/integrations`. Il parcourt
`$event->user->notifications()` (filtre `notifiable_type` + `notifiable_id`) en `cursor()` et supprime chaque ligne
par Eloquent (`no-cascade-delete`). Le bloc notifications de `DeleteUserGamificationData` devient redondant mais
reste en place (idempotent) : sa suppression est un suivi pour la branche qui détient la gamification.

**Tests.** Trois types (tâche, dépense, badge) supprimés pour l'utilisateur, ceux d'un autre utilisateur intacts ;
une ligne d'un autre `notifiable_type` portant le même id survit ; chaque suppression passe par Eloquent (compteur
sur `DatabaseNotification::deleted`) ; listener enregistré sur `UserDeleting`. Les tests de purge gamification
existants (`ChallengeModelTest`) restent verts.

## 2. Centre de notifications borné au notifiable connecté

**Cause.** `NotificationCenter::userNotifications()` filtre `where('notifiable_id', auth('web')->id())` sans
`notifiable_type` : un autre notifiable portant le même id verrait ses notifications comptées, listées et marquées
comme lues.

**Décision.** Base de requête `DatabaseNotification::query()->whereMorphedTo('notifiable', Auth::guard('web')->user())`
pour le compteur, la liste, `markAsRead` et `markAllAsRead`. Rejeté : la relation `notifications()` (couple le layer
technique au type `User` et renvoie un `MorphMany` au lieu d'un `Builder`).

**Tests.** Une notification d'un autre type de notifiable au même id n'est ni comptée, ni affichée, ni marquée par
`markAsRead` ou `markAllAsRead`.

## 3. Lomkit : chemin de relation non déclaré → 500

**Cause.** `Relationable::relation()` (lomkit 2.21.0, toujours présent en 2.23.2 et sur `main` le 2026-10-05) déréférence
`$relation->resource()` sans test de nullité quand le premier segment d'un chemin pointé n'est pas déclaré. Sonde
sur `/api/moto-rides/search` et `/api/badge-awards/search` :

| Clause | Charge utile | Statut |
|---|---|---|
| includes | `user.badge`, et `x.y` imbriqué sous `badge` | **500** `Call to a member function resource() on null` |
| aggregates | `user.badge` | **500** (même cause) |
| includes / aggregates | `badge.foo`, `user` | 422 |
| filters, sorts, selects | `user.badge.name`, `user.name` | 422 (`isNestedField` et `Rule::in` sont sûrs) |
| mutate `relations` | `user.badge` | 422 |
| includes / aggregates | élément chaîne, clé `relation` absente ou non chaîne | 500 (lecture de `$value['relation']`) |
| enveloppe | `search: "x"` | 500 (`TypeError` du builder) |

Avec `APP_DEBUG=true`, la réponse expose `exception`, `file`, `line` et `trace`.

**Sonde sur lomkit 2.23.2 (version installée après la montée du §6).** Le pont reste nécessaire : un chemin pointé dont
le premier segment n'est pas déclaré renvoie toujours 500, ainsi que les éléments `includes` / `aggregates` chaînes ou
sans `relation` exploitable. Seuls `search` non tableau, `includes` / `aggregates` non tableaux et `relation` numérique
sont corrigés en amont (422, épinglés par des tests de non-régression sans code ajouté).

**Décision.** Le défaut est dans le paquet : le correctif va d'abord **en amont** (`contribute-upstream`). Brouillon
d'issue + PR pour Lomkit/laravel-rest-api, à déposer par Quentin : « 500 quand le premier segment d'une relation
pointée n'est pas déclaré », reproduction ci-dessus, correctif `return $relation?->resource()->relation($nestedRelation);`
dans `Relationable`, plus `relation` `required` + `string` lus avant `$value['relation']` dans `SearchInclude` /
`SearchAggregate` (le `'array'` sur `search` est corrigé depuis 2.23.2), avec test dans la suite du paquet.

**Pont applicatif** (demandé, à confirmer par Quentin une fois l'issue ouverte, référencée dans le commit et la PR) :
trait `ResolvesUndeclaredRelationPathsToNull` utilisé par la `Resource` OSDD de base (`technical/osdd`), dont toutes
les ressources héritent. Il surcharge `relation(string $name): ?Relation` : `null` si le premier segment n'est pas
déclaré, sinon `parent::relation($name)`. La propre règle lomkit `ResourceRelationOrNested` rejette alors le chemin
en 422 (`search.includes.0.relation`, `search.aggregates.0.relation`, `search.includes.0.includes.0.relation`) avec le
message `The relation is not valid or allowed for this resource.`, à toute profondeur et sur les 17 ressources. Validé
par une sonde jetable sur une sous-classe de `BadgeAwardResource`. Pas de mapping d'exception par message, pas de
try-catch, aucun fichier `vendor/`. Le trait est supprimé dans la PR qui monte vers la première version lomkit corrigée de ce défaut (2.23.2 ne l'est pas).

**Résiduel accepté.** Les éléments malformés (chaîne, `relation` absente ou non chaîne) et `search` non tableau
restent en 500 jusqu'à la version amont : un pont de plus imposerait de rebinder `SearchRequest`, plus large que le
besoin. En production (`APP_DEBUG=false`, §5) la réponse ne contient pas de trace.

**Tests.** Data provider sur les 17 endpoints `search` : include `user.badge` → 422 sur `search.includes.0.relation` ;
aggregate `user.badge` → 422 ; chemin imbriqué sous `badge` → 422 ; `badge.foo` toujours 422 ; `badge` toujours
inclus et agrégé (200) ; aucune clé `trace` / `exception` avec `app.debug` vrai ; `relation()` renvoie `null` pour
`user.badge` et `badge.foo`, un `BelongsTo` pour `badge`.

## 4. Sorties moto saisies en heure locale

**Cause.** `MotoDashboard::logRide()` fait `Carbon::parse($this->rideStartedAt)` dans le fuseau applicatif (UTC) et
`mount()` pré-remplit avec `Carbon::now()` UTC : « dimanche 23:30 Paris » est stocké `23:30` UTC (lundi 01:30 Paris),
donc compté le lundi par la gamification. La liste affiche `started_at` en UTC.

**Décision.** Aucun fuseau d'affichage applicatif n'existe ; `gamification.timezone` est un réglage de jeu. Nouvelle
clé `app.display_timezone` (`env('APP_DISPLAY_TIMEZONE', 'Europe/Paris')`) dans `technical/application/config/app.php`,
le layer qui détient `app.*` ; le fichier ne fait que lire et défaut. Service `Technical\Application\Time\DisplayTimezone`
qui valide l'identifiant à la consommation (`InvalidDisplayTimezoneException` nommant `APP_DISPLAY_TIMEZONE`, pas de
repli silencieux) et convertit : saisie `datetime-local` locale → instant UTC, instant → heure locale, instant →
valeur `Y-m-d\TH:i` du champ. `MotoDashboard` l'utilise dans `mount`, `logRide` et `render` ; la vue affiche
`started_at` converti. La validation REST des sorties (autre branche) n'est pas touchée.

**Tests.** `2026-10-04T23:30` → stocké `2026-10-04 21:30:00` (CEST) ; `2026-12-06T23:30` → `2026-12-06 22:30:00` (CET) ;
champ pré-rempli `2026-10-04T23:30` à `2026-10-04 21:30:00 UTC`, et après enregistrement ; liste `04/10/2026 23h` ;
fuseau `America/New_York` respecté ; fuseau invalide → exception nommée.

## 5. `APP_DEBUG` en production

`.env.example` garde `APP_DEBUG=true` pour le développement local. Le dépôt ne contient aucun fichier de
configuration de production (`compose.yaml` est Sail, `bruno/.../prod.bru` un environnement de client API) et
`config('app.debug')` vaut `false` quand la variable est absente. **La production doit tourner avec
`APP_ENV=production` et `APP_DEBUG=false`** dans le `.env` du serveur : avec le mode debug, toute 500 (dont le
résiduel du §3) renvoie la trace, et l'avis `laravel/framework` « XSS in Debug Page Information » ne concerne que ce
mode. Aucun changement de code ; vérification par Quentin sur le serveur (`php artisan about --only=environment`).

## 6. Avis de sécurité des dépendances

`composer audit --locked` (2026-10-05) : guzzlehttp/guzzle 7.12.3 (1 high, 5 medium), league/commonmark 2.8.2
(9 high, 3 medium), livewire/livewire 3.8.1 (DOM XSS medium), phpseclib/phpseclib 3.0.55 (medium),
league/flysystem 3.35.1, symfony/yaml 7.4.11 (3) et laravel/framework 12.62.0 (low). Toutes les versions corrigées
tiennent dans les contraintes actuelles de `composer.json` (patch ou mineure). lomkit/laravel-rest-api, montée à 2.23.2
sur décision de Quentin (avis GHSA-w87g-c4fx-87gg, que `composer audit` ne signalait pas), fait partie du lot :

| Paquet | Installé | Corrigé à partir de | Cible (Packagist, 2026-10-05) |
|---|---|---|---|
| laravel/framework | 12.62.0 | 12.69.0 | 12.69.3 |
| livewire/livewire | 3.8.1 | 3.8.3 | 3.8.10 |
| league/commonmark | 2.8.2 | 2.10.2 | 2.10.3 |
| league/flysystem | 3.35.1 | 3.35.3 | 3.36.0 |
| guzzlehttp/guzzle | 7.12.3 | 7.15.2 | 7.15.5 |
| phpseclib/phpseclib | 3.0.55 | 3.0.57 | 3.0.57 |
| symfony/yaml | 7.4.11 | 7.4.12 | 7.4.20 |
| lomkit/laravel-rest-api | 2.21.0 | 2.23.2 (GHSA-w87g-c4fx-87gg) | 2.23.2 |

Les SDK Foutraz sont des dépôts VCS GitHub, injoignables depuis ce poste : Quentin lance la mise à jour, puis
l'implémenteur vérifie (suite complète, PHPStan, Pint, `composer audit`) et commite le lock.

## 7. Suivis hors périmètre

- Retirer le bloc notifications de `DeleteUserGamificationData` (branche gamification) une fois le §1 mergé.
- Même défaut de fuseau : `TodoBoard::newDueAt` (saisie `datetime-local` stockée en UTC, rappels décalés) et les
  libellés météo de l'écran moto (bandeau horaire, créneaux favorables) affichés en UTC ; `DisplayTimezone` les couvre.
- `gamification.timezone` pourrait prendre `app.display_timezone` pour défaut (branche gamification).
- lomkit 2.23.2 (avis GHSA-w87g-c4fx-87gg) est installée dans cette branche ; le défaut du §3 y subsiste, le pont aussi.

## 8. Décisions prises

1. **Purge générique dans `technical/notifications`**, par Eloquent en `cursor()`, sur `UserDeleting` comme
   `technical/integrations` (dépendance vers `functional/users` non déclarée dans `composer.json`, même précédent).
   Rejeté : un listener par layer fonctionnel (oubli garanti au prochain type) ; une suppression en masse (contourne
   les événements Eloquent) ; éditer la gamification ici (autre branche).
2. **`whereMorphedTo` plutôt que la relation `notifications()`** dans le centre de notifications.
3. **Lomkit : amont d'abord, pont minimal ensuite.** Rejeté : mapper `Error` par son message dans `bootstrap/app.php`
   (fragile, masque d'autres bugs) ; un middleware ou un `SearchRequest` rebindé couvrant aussi les éléments
   malformés (pont plus large que le défaut demandé) ; patch Composer ou édition de `vendor/`.
4. **Fuseau d'affichage applicatif `app.display_timezone`, défaut `Europe/Paris`, validé à la consommation avec
   exception nommée.** Rejeté : réutiliser `gamification.timezone` (concept distinct, autre branche) ; changer
   `app.timezone` (stockage et requêtes UTC partout) ; replier silencieusement sur UTC.
5. **`APP_DEBUG` documenté, pas de code** : aucun fichier de production dans le dépôt.
6. **Dépendances : patch / mineure dans les contraintes, lancées par Quentin**, lock seul commité, lomkit incluse
   (2.23.2, avis GHSA-w87g-c4fx-87gg, décision de Quentin) ; le pont du §3 reste nécessaire sur cette version.
