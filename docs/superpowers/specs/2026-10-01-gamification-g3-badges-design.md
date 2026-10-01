# Gamification G3 — Badges (jalons) — design

> Date : 2026-10-01. Phase G3 de la roadmap `2026-07-03-gamification-roadmap-design.md` (section 5).
> Base : `feature/gamification-phase-2` (streaks, PR #33, non mergée). Branche : `feature/gamification-phase-3`.

## 1. Objectif

Donner au joueur une **collection de badges** qui récompense des jalons durables de sa vie réelle : distance cumulée,
nombre de sorties, régularité, patrimoine investi, zones explorées, tâches accomplies, assiduité des pesées.
Chaque badge existe en trois paliers (Bronze, Argent, Or), rapporte de l'XP via le ledger, déclenche une notification
dans la cloche existante et s'affiche dans une vitrine sur le hub Joueur.

## 2. Périmètre

- Contrat `BadgeRule` (une famille de badges = une règle) tagué `gamification.badge_rules`, comme `XpRule`.
- Catalogue persistant `badges` (une ligne par famille × palier), seedé et resynchronisé de façon idempotente depuis
  les règles + la config `gamification.badges`.
- Attributions `badge_awards` uniques par (user, badge), idempotentes et définitives.
- XP de badge dans le ledger (`rule_key = badge_award`), reconvergée à chaque passage de l'orchestrateur.
- Notification database `BadgeAwardedNotification` (affichée par `NotificationCenter`).
- Vitrine Livewire sur `PlayerProfilePage` + ligne « Badges : x / y » sur la tuile dashboard.
- API REST Lomkit lecture seule : `badges` (catalogue) et `badge-awards` (scopé au user).
- Purge des attributions sur `UserDeleting` via le listener existant.

Hors périmètre : badges secrets, badges sociaux, digest de notifications (G6), révocation de badges.

## 3. Catalogue

| Famille (`rule_key`) | Domaine | Mesure | Bronze | Argent | Or |
|---|---|---|---|---|---|
| `sport_distance` | Sport | km cumulés (somme `distance` / 1000) | 100 | 1 000 | 5 000 |
| `sport_activity_count` | Sport | nombre d'activités | 10 | 100 | 500 |
| `sport_streak` | Sport | meilleure série (jours) | 7 | 30 | 100 |
| `health_measurement_days` | Santé | jours avec au moins une mesure | 7 | 60 | 365 |
| `health_streak` | Santé | meilleure série (jours) | 7 | 30 | 100 |
| `finance_invested_capital` | Finance | capital net investi (€) | 1 000 | 10 000 | 50 000 |
| `moto_distance` | Moto | km cumulés | 500 | 5 000 | 20 000 |
| `todo_tasks_completed` | Tâches | tâches terminées | 25 | 250 | 1 000 |
| `todo_streak` | Tâches | meilleure série (jours) | 7 | 30 | 100 |
| `exploration_cells` | Exploration | cellules explorées | 100 | 1 000 | 5 000 |

XP par palier (config `gamification.badges.tier_xp`) : Bronze 50, Argent 150, Or 500.

## 4. Modèle de données

- `badges` : `id` ULID, `key` unique (`<rule_key>_<tier>`), `rule_key`, `domain`, `tier` (`BadgeTier`), `threshold`
  decimal(14,2), `xp_reward`, timestamps (aucun texte : nom et description sont traduits à l'affichage). Pas de `user_id` : catalogue global.
- `badge_awards` : `id` ULID, `user_id` (FK sans cascade), `badge_id` (FK sans cascade), `awarded_at`, timestamps,
  unique (`user_id`, `badge_id`).
- Pas de SoftDeletes : convention des modèles gamification existants (`XpEntry`, `PlayerProfile`, `Streak`).

## 5. Flux

`RunUserGamification` : `AwardXp` → `UpdateStreaks` → **`EvaluateBadges`**.

`EvaluateBadges::handle(User)` :
1. `SyncBadgeCatalogue` upserte le catalogue (idempotent, propage les changements de config).
2. Pour chaque règle taguée : `measure(User)` (une agrégation SQL), sélection des badges dont `threshold <= mesure`
   et non encore possédés, insertion (`insertOrIgnore` sur la clé unique).
3. Reconvergence du ledger : une entrée `badge_award` par attribution (`source_type` = identifiant dédié `badge`,
   `source_id` = clé du badge — donc une seule fois par user et par badge —, `occurred_at` = `awarded_at`), suppression des entrées `badge_award` orphelines, upsert, puis `RefreshPlayerProfile`.
4. Après commit : une `BadgeAwardedNotification` par badge nouvellement obtenu.

`UpdateStreaks` exclut désormais toutes les clés bonus (`XpEntry::BONUS_RULE_KEYS` = `streak_milestone`,
`badge_award`) du calcul des jours actifs, pour qu'un bonus ne prolonge jamais une série.

## 6. UI

- Hub Joueur : section « Badges » (compteur `x / 30`), une carte par famille : nom, domaine, trois médailles
  (allumées si obtenues), valeur actuelle et progression vers le prochain palier.
- Tuile dashboard : ligne « Badges : x / 30 » ajoutée aux `secondaryLines`.

## 7. Décisions prises

0. **Alignement G2** : les badges s'insèrent dans le flux atomique de l'orchestrateur (même transaction, profil
   rafraîchi une fois en fin de passage, notifications après commit), réutilisent le fuseau d'affichage configuré
   pour le découpage en jours et un identifiant de source non-modèle (`badge`).
1. **Catalogue en table, alimenté par les règles + la config** (et non codé en dur dans un seeder). L'action
   `SyncBadgeCatalogue` est appelée par le seeder `GamificationSeeder` *et* au début de chaque `EvaluateBadges`.
   Rejeté : catalogue purement en code sans table (contredit le modèle `Badge` de la roadmap et empêche l'API
   catalogue) ; migration de données (les changements de seuils ne se propageraient pas) ; seeder seul (rien
   n'assure que la prod est seedée).
2. **Badges définitifs** : une baisse de la mesure (activité supprimée) ne retire pas le badge.
   Rejeté : attribution convergente façon paliers de streak (notifications à répétition si une donnée oscille,
   expérience anxiogène contraire à l'hypothèse « non intrusive »).
3. **XP de badge reconvergée depuis `badge_awards` à chaque passage**, comme les paliers de streak, car
   `AwardXp::purgeWindow` supprime toutes les entrées de la fenêtre. Rejeté : exclure `badge_award` de la purge
   (modifierait le contrat G1 de purge des règles retirées).
4. **Régularité par domaine sur Sport, Santé et Tâches uniquement** (trois classes concrètes d'un `StreakBadgeRule`
   abstrait). Rejeté : une famille par domaine (18 badges de série dont Finance/Moto/Exploration, où une série
   quotidienne n'a pas de sens) ; une famille transverse « meilleure série tous domaines » (l'entrée ledger exige
   un domaine).
5. **Trois paliers Bronze/Argent/Or** (`BadgeTier` avec `label()`, `rank()`, `xpReward()`, `accent()`), conformes
   à la roadmap. Rejeté : un palier Platine (prématuré avant l'équilibrage G6).
6. **Une notification par badge obtenu**, envoyée après commit, canal `database` seul. Rejeté : notification groupée
   (relève du digest G6) ; mail (bruit pour un jalon ludique).
7. **Textes via fichiers de traduction du layer** (`functional/gamification/lang/{fr,en}/badges.php`, namespace
   `gamification`) : noms de familles, descriptions, libellés de paliers, vitrine, notification. La table `badges`
   ne stocke pas de texte, seulement la clé de règle et le palier ; le nom affiché est résolu à l'exécution, ce qui
   suit la locale du lecteur. Rejeté : français en dur (convention en cours de remplacement sur G2) ; colonnes
   `name`/`description` figées en base (ne suivraient pas la locale).
8. **API lecture seule** (`RejectsApiCreation` + policies `update`/`delete` à `false`) ; le catalogue est visible de
   tout utilisateur authentifié, les attributions sont scopées au propriétaire. Rejeté : endpoint d'attribution
   manuelle (contournerait les règles).
