# Story — Intégration du calendrier Apple (iCloud)

## 1. Titre

Permettre la connexion du calendrier Apple (iCloud) pour agréger ses événements dans le dashboard

## 2. User Story

> En tant qu'**utilisateur du dashboard**,
> Je veux **connecter mon calendrier Apple (iCloud)**,
> Afin de **retrouver mes événements Apple dans mon agenda agrégé, aux côtés de Google Calendar et Outlook, sans changer d'outil.**

## 3. Contexte

Le dashboard agrège déjà les calendriers Google et Outlook dans une vue planning unifiée. Les utilisateurs de l'écosystème Apple n'ont aujourd'hui aucun moyen d'y faire remonter leur agenda iCloud. Cette story ajoute Apple comme troisième source de calendrier, pour que l'agenda agrégé reflète l'ensemble des rendez-vous de l'utilisateur.

Contrairement à Google et Outlook, Apple ne propose pas de connexion par autorisation déléguée : l'accès à iCloud passe par les identifiants Apple de l'utilisateur assortis d'un mot de passe dédié généré depuis son compte Apple.

## 4. Périmètre fonctionnel

- **Inclus**
  - Connexion d'un compte calendrier Apple à partir des identifiants Apple de l'utilisateur.
  - Récupération des événements du calendrier Apple et intégration à l'agenda agrégé du dashboard.
  - Déclenchement d'une synchronisation à la connexion, puis à la demande de l'utilisateur.
  - Déconnexion du compte Apple et suppression des événements Apple associés.
- **Exclu**
  - La création, la modification ou la suppression d'événements Apple depuis le dashboard (lecture seule, comme Google et Outlook aujourd'hui).
  - L'intégration d'Apple Santé / HealthKit — sans objet ici, ces données restent couvertes par les intégrations santé existantes (Withings).
  - Les rappels, notes et autres données iCloud hors calendrier.
- **Dépendances**
  - Le socle d'agrégation de calendriers (planning) et le socle de connexions d'intégrations existants.
  - Une brique d'accès au calendrier Apple, livrée comme composant réutilisable dédié, en cohérence avec les connecteurs Google et Outlook existants.

### Projection visuelle

L'utilisateur doit pouvoir, depuis l'écran de gestion des intégrations : ajouter une connexion Apple en saisissant ses identifiants Apple, déclencher une synchronisation, et déconnecter le compte. Une fois connecté, il doit retrouver ses événements Apple dans l'agenda agrégé du planning. La disposition et l'habillage de ces éléments relèvent du design.

## 5. Règles métier

- La connexion Apple se fait par **saisie d'identifiants** (identifiant Apple + mot de passe d'application), et non par redirection d'autorisation comme Google et Outlook.
- Les identifiants saisis sont **validés au moment de la connexion** : une connexion n'est enregistrée que si l'accès au calendrier Apple est effectivement établi.
- Le mot de passe d'application est une **donnée sensible** : il est stocké de manière chiffrée et n'est jamais réaffiché à l'utilisateur.
- La synchronisation est **en lecture seule** : aucune donnée n'est écrite vers iCloud.
- Un utilisateur ne peut avoir qu'**une seule connexion Apple active** à la fois.
- À la **déconnexion**, les événements Apple précédemment importés pour cet utilisateur sont supprimés de l'agenda agrégé.
- Les événements Apple apparaissent dans l'agenda agrégé **identifiés comme provenant d'Apple**, au même titre que les sources Google et Outlook.
- Seul le **calendrier principal** du compte Apple est synchronisé, en cohérence avec la synchronisation Google (le multi-calendrier reste une évolution possible).
- Seuls les **événements à venir sur une fenêtre de 90 jours** sont synchronisés, en réutilisant le paramètre commun aux synchronisations Google et Outlook — un même réglage pilote les trois sources.

## 6. Critères d'acceptation

```gherkin
Étant donné un utilisateur connecté sans calendrier Apple relié
Lorsqu'il saisit un identifiant Apple et un mot de passe d'application valides
Alors une connexion Apple est enregistrée pour cet utilisateur
Et une première synchronisation de ses événements Apple est déclenchée
```

```gherkin
Étant donné un utilisateur connecté sans calendrier Apple relié
Lorsqu'il saisit des identifiants Apple invalides
Alors aucune connexion n'est enregistrée
Et un message d'erreur lui indique que les identifiants n'ont pas permis d'accéder au calendrier
```

```gherkin
Étant donné un utilisateur ayant un calendrier Apple relié et synchronisé
Lorsqu'il consulte l'agenda agrégé du planning
Alors ses événements Apple y figurent aux côtés de ses événements Google et Outlook
Et chaque événement Apple est identifié comme provenant d'Apple
```

```gherkin
Étant donné un utilisateur ayant un calendrier Apple relié
Lorsqu'il déclenche une synchronisation manuelle
Alors ses événements Apple sont mis à jour dans l'agenda agrégé
```

```gherkin
Étant donné un utilisateur ayant un calendrier Apple relié
Lorsqu'il déconnecte son compte Apple
Alors la connexion Apple est supprimée
Et ses événements Apple n'apparaissent plus dans l'agenda agrégé
```

## 7. Cas limites et erreurs

- **Identifiants refusés** (identifiant Apple ou mot de passe d'application incorrect, mot de passe révoqué côté Apple) : la connexion échoue proprement et l'utilisateur est informé ; aucune connexion partielle n'est laissée en base.
- **Service iCloud indisponible ou synchronisation en échec** : la connexion existante et les événements déjà importés sont conservés ; l'échec n'efface pas les données présentes.
- **Aucun événement sur la fenêtre synchronisée** : l'agenda agrégé s'affiche sans événement Apple, sans erreur.
- **Utilisateur sans connexion Apple** demandant une synchronisation manuelle : action sans effet, signalée comme telle.
- **Mot de passe d'application révoqué après une connexion réussie** : les synchronisations suivantes échouent ; l'utilisateur doit pouvoir reconnecter son compte avec un nouveau mot de passe.

## 8. Impacts techniques à anticiper

- **Nouvelle source de calendrier** ajoutée au dispositif d'agrégation existant : l'agenda du planning devra désormais combiner trois origines (Google, Outlook, Apple).
- **Donnée sensible** : le mot de passe d'application Apple est un secret d'accès au compte de l'utilisateur ; son stockage chiffré et sa non-exposition sont des points de vigilance (sécurité, RGPD).
- **Mode de connexion différent** des intégrations existantes : Apple repose sur une saisie d'identifiants et non sur une autorisation déléguée, ce qui introduit un parcours de connexion distinct de celui de Google et Outlook.
- **Dépendance externe** : la remontée des événements dépend de la disponibilité du service iCloud ; les échecs de synchronisation doivent rester sans conséquence sur les données déjà présentes.
- **Suppression en cascade** des événements Apple à la déconnexion du compte, en cohérence avec le comportement déjà en place pour les autres calendriers.

---

### Partition des sources

- **Explicite** (issu du besoin exprimé et du cadrage validé) : intégration du calendrier Apple ; Apple Santé hors périmètre ; lecture seule ; connexion par identifiants Apple + mot de passe d'application ; brique d'accès iCloud livrée comme composant réutilisable dédié.
- **Implicite** (inféré du fonctionnement des intégrations Google / Outlook existantes, à confirmer) : synchronisation à la connexion + à la demande ; suppression des événements à la déconnexion ; identification de la source dans l'agenda agrégé.
- **Arbitrages retenus** (valeurs calées sur l'existant) : calendrier principal uniquement ; fenêtre de 90 jours à venir pilotée par le réglage commun aux trois sources.
