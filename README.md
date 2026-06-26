# Personal Dashboard

## 📖 Présentation

Personal Dashboard est une application web modulaire permettant de centraliser, analyser et exploiter les données personnelles provenant de différents services externes (Strava, Outlook, Google, etc.) au sein d'une plateforme unique.

L'objectif du projet est de fournir à chaque utilisateur un tableau de bord personnel regroupant ses activités sportives, ses investissements, son planning, ses tâches, ses objectifs et ses données d'exploration géographique afin de faciliter la prise de décision quotidienne.

Le projet est conçu dès le départ comme une application SaaS pouvant être commercialisée et utilisée par plusieurs utilisateurs indépendants.

> **Statut : socle + 8 modules fonctionnels livrés.** Architecture OSDD, autorisation par policies active, suite de tests verte (Larastan niveau 7). Voir [État du projet](#-état-du-projet).

---

# 🎯 Objectifs du projet

* Centraliser les données provenant de plusieurs services externes.
* Offrir une expérience utilisateur unifiée.
* Permettre une exploitation avancée des données grâce à des statistiques et des indicateurs personnalisés.
* Réduire la dépendance aux services tiers grâce à une synchronisation locale des données.
* Fournir une architecture modulaire permettant l'ajout de nouveaux modules et intégrations dans le futur.

---

# 🏗️ Architecture

## Backend

API REST développée avec **Laravel 12**, organisée selon l'architecture **OSDD** (`xefi/laravel-osdd`) : des couches autonomes et composables, chacune packagée comme un package Composer local.

* **Couches techniques** (`technical/*`) : `osdd` (socle REST + contrôles d'accès), `application` (configuration), `authentication` (JWT + tables de permissions), `web-authentication` (auth session + Google OAuth), `integrations` (connexions externes génériques).
* **Couches fonctionnelles** (`functional/*`) : `users`, `sport`, `todo`, `finance`, `goals`, `recurring-expenses`, `exploration`, `moto`, `planning`.

### Technologies

* Laravel 12 · PHP 8.4
* Lomkit Laravel REST API (CRUD déclaratif via Resources)
* Lomkit Access Control (perimeters + policies par modèle)
* Spatie Permission (rôles & permissions, clés ULID)
* Authentification JWT (`tymon/jwt-auth`) pour l'API ; auth session (guard `web`) pour le navigateur
* Jobs & Queues (synchronisations idempotentes)
* Larastan niveau 7 · PHPUnit

### Principes

* Chaque module possède sa propre logique métier dans une couche isolée.
* Les données externes sont synchronisées via un SDK dédié puis stockées dans une base locale.
* Les fournisseurs externes sont encapsulés dans des SDK distincts (repos GitHub indépendants).
* Identifiants en **ULID**, suppression en cascade par **listeners** (pas de cascade SQL), tokens d'intégration **chiffrés** au repos.
* Isolation par utilisateur garantie par les `Control` Lomkit + policies (`Gate`).

---

## Frontend

Interface **server-driven** intégrée au monolithe Laravel : **Blade + Livewire 3 + Alpine.js + Tailwind CSS v4**.

### Principes

* Design system futuriste « command deck » : glassmorphism, accents néon (cyan / violet / lime), fond aurora animé.
* Interactions modernes via **Motion One** et graphiques via **ApexCharts** ; cartographie via **Leaflet**.
* Composants UI réutilisables (`glass-card`, `neon-button`, `stat-tile`, `module-card`, sidebar, …).
* Le navigateur s'authentifie via le guard `web` (session) ; l'API JWT reste disponible pour les SDK et un éventuel client mobile.

---

# 🔗 Gestion des connexions

L'application distingue deux notions :

## Authentification

Permet de se connecter à la plateforme.

### Fournisseurs

* ✅ Email / Mot de passe
* ✅ Google (OAuth via Socialite)

### Évolutions possibles

* Apple
* GitHub
* Microsoft

---

## Intégrations externes

Permettent de synchroniser des données avec l'application. Chaque intégration dispose de son SDK dédié, de son authentification (OAuth), de ses jobs de synchronisation et de son stockage local. Les connexions sont gérées par la couche générique `integrations` (tokens chiffrés, rafraîchissement automatique, une connexion par fournisseur et par utilisateur).

### Intégrations

* ✅ **Strava** — `foutraz/strava` ([SDK_Strava](https://github.com/Foutraz/SDK_Strava))
* ✅ **Google Calendar** — `foutraz/google-calendar` ([SDK_GoogleCalendar](https://github.com/Foutraz/SDK_GoogleCalendar))
* ✅ **Outlook / Microsoft Graph** — `foutraz/outlook` ([SDK_Outlook](https://github.com/Foutraz/SDK_Outlook))
* ✅ **Météo** — `foutraz/weather` (OpenWeatherMap, [SDK_Weather](https://github.com/Foutraz/SDK_Weather))
* ⚠️ **Liberty Rider** — `foutraz/liberty-rider` ([SDK_LibertyRider](https://github.com/Foutraz/SDK_LibertyRider)) : aucune API publique n'existe à ce jour, le SDK est un squelette prêt à brancher ; les sorties moto sont saisies manuellement.

---

# 📦 Modules fonctionnels

## 🏃 Sport ✅

Synchronisation des activités sportives depuis Strava.

* Connexion Strava (OAuth) · synchronisation par jobs idempotents
* Historique des activités · statistiques (distance, dénivelé, temps)
* Graphiques d'évolution (hebdo / mensuel / annuel) · analyse des performances

---

## 🏍️ Météo & Moto ✅

Aide à la planification des sorties moto.

* Prévisions météo (OpenWeatherMap) · conditions de roulage
* Score **Moto Friendly** (pluie, vent, température, visibilité, pondéré et plafonné par les facteurs de sécurité)
* Recommandations de créneaux favorables · journal de sorties (saisie manuelle)

---

## 💰 Finance ✅

Suivi des investissements personnels.

* Gestion des positions · historique des transactions
* Calcul du capital investi · performance globale
* Simulation DCA interactive · projections futures

---

## 📅 Échéances financières ✅

Suivi des dépenses récurrentes (loyer, assurances, abonnements, crédits).

* Rappels automatiques (notifications) · calendrier des échéances
* Totaux mensuels · répartition par catégorie

---

## 📆 Planning ✅

Centralisation des événements personnels.

* Synchronisation Outlook (Microsoft Graph) · synchronisation Google Calendar
* Vue calendrier (mois / semaine) agrégeant les deux fournisseurs **et** les échéances financières
* Gestion des événements (lecture synchronisée)

---

## ✅ To-Do ✅

Gestion des tâches personnelles.

* Création / priorisation / échéances · CRUD complet via l'API
* Rappels et notifications · statistiques de complétion

---

## 🎯 Objectifs & Suivi ✅

Suivi de la progression personnelle.

* Objectifs sportifs, financiers et personnels
* **Suivi automatique** : progression calculée depuis les données Sport et Finance
* Indicateurs de progression (anneaux animés)

---

## 🗺️ Cartes & Exploration ✅

Analyse des zones explorées à partir des trajets synchronisés.

* Affichage cartographique (Leaflet) · historique des trajets
* Couverture géographique par grille · pourcentage d'exploration d'une zone
* Analyse des régions parcourues (décodage des polylignes Strava)

---

# 🔄 Philosophie de synchronisation

L'application utilise un modèle de synchronisation locale.

## Principe

1. Connexion à un fournisseur externe (OAuth, connexion chiffrée).
2. Synchronisation des données via un SDK dédié (jobs en file).
3. Stockage idempotent dans la base de données interne (ULID, clé unique par identifiant externe).
4. Exploitation locale des données.

### Avantages

* Rapidité d'affichage.
* Réduction des appels API externes.
* Résilience en cas d'indisponibilité d'un fournisseur.
* Historisation complète des données.

---

# 📡 SDK externes

Chaque fournisseur est encapsulé dans un SDK PHP framework-agnostic, publié comme repo GitHub indépendant sous l'organisation [`Foutraz`](https://github.com/Foutraz) et branché au dashboard via un dépôt Composer `path` :

| SDK | Package | Repo |
|-----|---------|------|
| Strava | `foutraz/strava` | [SDK_Strava](https://github.com/Foutraz/SDK_Strava) |
| OpenWeatherMap | `foutraz/weather` | [SDK_Weather](https://github.com/Foutraz/SDK_Weather) |
| Google Calendar | `foutraz/google-calendar` | [SDK_GoogleCalendar](https://github.com/Foutraz/SDK_GoogleCalendar) |
| Outlook / Microsoft Graph | `foutraz/outlook` | [SDK_Outlook](https://github.com/Foutraz/SDK_Outlook) |
| Liberty Rider (exploratoire) | `foutraz/liberty-rider` | [SDK_LibertyRider](https://github.com/Foutraz/SDK_LibertyRider) |

---

# ✅ État du projet

| Domaine | Statut |
|---------|--------|
| Socle OSDD, Larastan niveau 7, design system | ✅ |
| Authentification (email/mot de passe, Google) + JWT API | ✅ |
| Autorisation par policies (Lomkit + Spatie, isolation par utilisateur) | ✅ |
| Modules Sport · Météo & Moto · Finance · Échéances · Planning · To-Do · Objectifs · Cartes | ✅ |
| SDK Strava · Weather · Google Calendar · Outlook | ✅ |
| SDK Liberty Rider | ⚠️ squelette (pas d'API publique) |

### Configuration requise pour l'usage réel

Les intégrations externes nécessitent des clés/credentials à renseigner dans `.env` (voir `.env.example`) :
`STRAVA_*`, `OPENWEATHER_API_KEY`, `GOOGLE_CALENDAR_*`, `OUTLOOK_*` / `AZURE_*`, ainsi que `GOOGLE_*` pour la connexion Google. La suite de tests fonctionne sans ces clés (appels externes mockés).

---

# 🚀 Vision long terme

Personal Dashboard a vocation à devenir une plateforme personnelle centralisant l'ensemble des données utiles du quotidien :

* Activités sportives
* Mobilité
* Finances
* Organisation personnelle
* Objectifs de vie

L'architecture modulaire OSDD permet l'ajout futur de nouvelles intégrations et fonctionnalités sans remettre en cause le socle existant.
