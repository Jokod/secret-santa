# Contribuer

Merci de contribuer à Secret Santa. Ce document décrit le flux attendu.

## Avant de commencer

1. Ouvre une issue pour discuter d’un bug ou d’une évolution non triviale.
2. Forke le dépôt puis crée une branche depuis `main` :

```bash
git checkout -b feature/ma-feature
# ou
git checkout -b fix/mon-correctif
```

## Environnement

Suis l’installation décrite dans le [README](README.md).

```bash
make start
make migrate
make create-admin EMAIL=dev@example.com PASSWORD=dev
```

## Règles de code

- PHP / Symfony 7.4 : style et conventions du projet existant
- Twig / CSS / Stimulus : rester cohérent avec les templates et `assets/`
- Pas de secrets dans le dépôt (`.env.local`, tokens, mots de passe)
- Ne pas committer `vendor/`, `var/`, `public/assets/`

## Checks locaux (obligatoires avant PR)

```bash
make ci
```

Cela enchaîne :

- `composer validate`
- lint YAML / Twig / container
- PHPUnit (MySQL de test)

Couverture (attendue à 100 % sur `src/`) :

```bash
make test-coverage
```

## Pull requests

- Une PR = un sujet clair (feature, fix, docs, chore)
- Titre explicite ; description du **pourquoi** et comment tester
- La CI GitHub Actions (`CI`) doit passer sur `main` / PR
- Pas de force-push sur `main`

### Checklist PR

- [ ] `make ci` OK en local
- [ ] Migrations ajoutées si le schéma change
- [ ] Textes FR cohérents (UI / emails)
- [ ] Pas de régression sur le parcours participant (lien magique) ni admin

## Releases

Réservées aux mainteneurs :

1. Merger sur `main`
2. Tag semver `X.Y.Z`
3. Release GitHub (titre = numéro de tag) + image `ghcr.io/jokod/secret-santa`

## Signalement de bugs

Dans l’issue, indiquer :

- version / tag ou commit
- étapes de reproduction
- comportement attendu vs observé
- logs utiles (conteneur `app` / `database`, sans secrets)

## Licence

En contribuant, tu acceptes que tes contributions soient publiées sous licence [MIT](LICENSE).
