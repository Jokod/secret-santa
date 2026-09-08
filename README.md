# Secret Santa

![Secret Santa Familial](public/images/logo.png)

[![CI](https://github.com/Jokod/secret-santa/actions/workflows/ci.yml/badge.svg)](https://github.com/Jokod/secret-santa/actions/workflows/ci.yml)
[![Release](https://github.com/Jokod/secret-santa/actions/workflows/release.yml/badge.svg)](https://github.com/Jokod/secret-santa/actions/workflows/release.yml)
[![Latest release](https://img.shields.io/github/v/release/Jokod/secret-santa?label=release)](https://github.com/Jokod/secret-santa/releases/latest)
[![License MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
![PHP ≥ 8.2](https://img.shields.io/badge/PHP-%E2%89%A5%208.2-777BB4?logo=php&logoColor=white)
![Symfony 7.4](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white)
![MySQL 8](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
[![GHCR](https://img.shields.io/badge/GHCR-secret--santa-blue?logo=docker&logoColor=white)](https://github.com/Jokod/secret-santa/pkgs/container/secret-santa)

Application web familiale pour organiser un **Secret Santa** : participants, exclusions, listes de souhaits, tirage au sort, emails et messagerie anonyme Santa ↔ destinataire.

Une instance = une famille = une édition à la fois.

## Fonctionnalités

- **Organisateur** (`/admin`) : participants, exclusions, budget & date, tirage / reset / relances, suivi des messages
- **Participants** (lien magique `/participant/{token}`) : souhaits, vue de la cible après tirage, messagerie anonyme
- Budget recommandé par souhait (dépassement possible avec avertissement)
- Tirage respectant les exclusions et l’auto-exclusion
- Image Docker publiée sur GitHub Packages à chaque tag semver

## Stack

| Élément | Choix |
|--------|--------|
| Backend | PHP ≥ 8.2 · Symfony 7.4 |
| Front | Twig · AssetMapper · Stimulus |
| Base | MySQL 8 |
| Conteneur prod | FrankenPHP (`ghcr.io/jokod/secret-santa`) |
| CI/CD | GitHub Actions (lint, tests, release GHCR) |

## Prérequis (développement)

- PHP 8.2+ avec extensions courantes (`pdo_mysql`, `intl`, …)
- [Composer](https://getcomposer.org/)
- [Docker](https://docs.docker.com/) + Docker Compose
- [Symfony CLI](https://symfony.com/download) (serveur local HTTPS recommandé)
- Make

## Installation locale

```bash
git clone https://github.com/Jokod/secret-santa.git
cd secret-santa
cp .env .env.local
# Renseigner au minimum MYSQL_* et DATABASE_URL vers le MySQL Docker (port 6950)
```

Exemple de `DATABASE_URL` pointant sur le MySQL du compose local :

```env
DATABASE_URL="mysql://root:root@127.0.0.1:6950/santa?serverVersion=8.0&charset=utf8mb4"
```

Démarrer la stack :

```bash
make start
# équivalent : docker compose up -d + symfony server + composer install
```

Services locaux :

| Service | URL |
|---------|-----|
| App | https://santa.wip (via Symfony proxy) |
| PhpMyAdmin | http://127.0.0.1:6951 |
| Mailhog | http://127.0.0.1:6953 |

Migrations et compte admin :

```bash
make migrate
make create-admin EMAIL=orga@example.com PASSWORD=secret
```

## Utilisation

### Organisateur

1. Se connecter sur `/admin/login`
2. Ajouter les participants (email de bienvenue avec lien personnel)
3. Configurer les exclusions et les réglages (budget, date, emails)
4. Lancer le tirage depuis `/admin/draw`
5. Relancer / réinitialiser si besoin

### Participant

1. Ouvrir le lien reçu par email (`/participant/{token}`)
2. Remplir sa liste de souhaits
3. Après tirage : voir sa cible, ses souhaits, et échanger anonymement via la messagerie

## Tests

```bash
make test              # unit + functional + integration (MySQL test)
make test-unit
make ci                # validate composer + lints + tests
make test-coverage     # couverture 100 % attendue
```

La base de test est `santa_test` sur le MySQL Docker (`6950`).

## Production (Docker)

Modèle d’environnement : [`.env.prod.dist`](.env.prod.dist).

```bash
cp .env.prod.dist .env.local
# Renseigner APP_SECRET, MYSQL_ROOT_PASSWORD, DEFAULT_URI, MAILER_DSN, MAIL_FROM

docker compose -f compose.prod.yaml --env-file .env.local pull
docker compose -f compose.prod.yaml --env-file .env.local up -d
```

Au démarrage du conteneur `app` :

1. sync metadata migrations  
2. `doctrine:migrations:migrate`  
3. `assets:install` + `asset-map:compile`  
4. `cache:warmup`  

Le conteneur `worker` consomme la file Messenger (`async`) pour envoyer les emails en arrière-plan (`messenger:consume async`).

Image :

```bash
docker pull ghcr.io/jokod/secret-santa:latest
# ou une version : ghcr.io/jokod/secret-santa:2.1.1
```

> Le package GHCR peut être privé : `docker login ghcr.io` avec un token GitHub (`read:packages`) si besoin.

## Releases

Les tags semver `X.Y.Z` déclenchent le workflow **Release** (checks + push GHCR).

```bash
git tag -a 2.1.2 -m "2.1.2"
git push origin 2.1.2
gh release create 2.1.2 --title "2.1.2" --notes "…"
```

## Structure utile

```
assets/               # CSS / contrôleurs Stimulus
config/               # Symfony
docker/entrypoint.sh  # Boot prod (migrations, assets, cache)
migrations/
src/                  # Domaine, contrôleurs, services
templates/
tests/
compose.yaml          # Dev (MySQL, PhpMyAdmin, Mailhog)
compose.prod.yaml     # Prod (app + worker Messenger + MySQL)
```

## Documentation produit

Voir [VISION.md](VISION.md) pour l’intention produit, le périmètre et la feuille de route.

## Contribution

Voir [CONTRIBUTING.md](CONTRIBUTING.md).

## Licence

Distribué sous licence [MIT](LICENSE).
