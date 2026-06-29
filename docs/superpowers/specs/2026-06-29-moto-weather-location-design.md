# Choix de ville manuel + géolocalisation pour Météo & Moto

**Date :** 2026-06-29
**Statut :** Approuvé

## Objectif

Permettre à l'utilisateur du tableau de bord « Météo & Moto » (`/moto`) de choisir
sa ville manuellement, avec une demande de géolocalisation du navigateur par défaut,
et recalculer toutes les informations météo (et le score moto) en fonction de la
position retenue.

## Décisions

- **Choix manuel** : recherche libre par nom de ville (géocodage), pas de liste figée.
- **Persistance** : aucune. La géolocalisation est redemandée à chaque visite ; le
  choix manuel est éphémère (pas de table ni de colonne de préférences).
- **Géocodage** : porté par le SDK `foutraz/weather` (`../SDK_Weather`), cohérent avec
  OpenWeatherMap déjà propriétaire de l'API.
- **Repli géoloc** : si l'utilisateur refuse la géolocalisation ou en cas d'erreur
  navigateur, on conserve la ville par défaut de la config (`moto.location`, Paris).

## Flux

À l'ouverture de `/moto`, la position initiale est celle de la config (Paris). Une
racine Alpine déclenche `navigator.geolocation.getCurrentPosition` au chargement :

- **Succès** → la position réelle est reverse-géocodée pour obtenir un label, puis la
  météo et le score moto se recalculent automatiquement.
- **Refus / erreur** → on reste sur la ville par défaut.

À tout moment, l'utilisateur saisit un nom de ville ; les résultats de géocodage
s'affichent en liste ; un clic bascule la localisation et recalcule la météo.

## Composants

### 1. SDK_Weather (`../SDK_Weather`)

- **DTO `Place`** (`src/Dto/Place.php`) : `name`, `state`, `country`, `lat`, `lon`,
  avec `fromArray()` et `label()` (ex. « Lyon, Auvergne-Rhône-Alpes, FR »).
- **Action `ManagesGeocoding`** (`src/Actions/ManagesGeocoding.php`, étend
  `WeatherManager`) :
  - `search(string $query, int $limit = 5): array` → `GET geo/1.0/direct` → liste de `Place`.
  - `reverse(float $lat, float $lon): ?Place` → `GET geo/1.0/reverse` → premier `Place` ou `null`.
- **`WeatherManager::geocoding(): ManagesGeocoding`**.

### 2. functional/moto

- **`WeatherForecastService`** : `searchCity(string $query): array` et
  `reverseGeocode(float $lat, float $lon): ?Place` (cache court), réutilisant
  `guardConfigured()`.
- **`MotoDashboard` (Livewire)** :
  - Propriétés : `string $citySearch`, `array $cityResults`.
  - `searchCity()` : remplit `$cityResults` (vide si requête trop courte).
  - `chooseCity(float $lat, float $lon, string $label)` : applique la localisation et
    vide la recherche.
  - `applyDeviceLocation(float $lat, float $lon)` : reverse-géocode pour le label
    (repli « Ma position »), puis applique la localisation.
  - Recalcul météo automatique : changer `$lat`/`$lon` re-render `render()`.

### 3. Vue `moto.blade.php`

- Bloc localisation : champ de recherche (`wire:model.live.debounce`) + liste de
  résultats (`wire:click="chooseCity(...)"`).
- Racine Alpine `x-init` : appelle `navigator.geolocation.getCurrentPosition` au
  chargement → succès `$wire.applyDeviceLocation(lat, lon)` ; échec : aucune action.

## Conventions & contraintes

- Pas de try-catch : clé API absente gérée par `guardConfigured()` ; géocodage sans
  résultat renvoie liste vide / `null` ; échec géoloc géré par le callback navigateur.
- Pas de migration (aucune persistance), donc pas d'ULID concerné.
- Docstrings une phrase en anglais, pas de commentaires inline, Pint en fin de tâche.

## Tests

- **SDK_Weather** : `ManagesGeocodingTest` (Guzzle mocké), `PlaceTest`.
- **moto** : `WeatherForecastServiceTest` (recherche + reverse mockés) ;
  `MotoDashboardComponentTest` (searchCity remplit les résultats, chooseCity change la
  localisation, applyDeviceLocation pose le label).

## Livraison

Deux repos impactés : `SDK_Weather` puis le dashboard. Le dashboard consomme le SDK en
*path repo* sans symlink ⇒ `composer update foutraz/weather` après les changements SDK
pour que le dashboard les voie.
