# Secret Santa Familial · Vision produit

Document de référence pour l’objectif, le périmètre et la feuille de route.  
Il s’aligne sur l’état réel du code (instance mono-famille, Symfony 6.4, domaine `santa.lepetitnas.fr`) et priorise des évolutions **atteignables** et **cohérentes**.

---

## 1. Intention

Offrir à **une seule famille** un outil web simple pour organiser son Secret Santa annuel : inscription des membres, exclusions réalistes (couples, etc.), listes de souhaits, tirage fiable, emails automatiques, et un canal discret entre Santa et destinataire · le tout sans friction technique pour les proches.

**Ce que ce produit n’est pas :** un SaaS multi-familles, une plateforme sociale, ni une app mobile. Une instance = une famille = une édition de tirage à la fois.

---

## 2. Principes directeurs

| Principe | Traduction concrète |
|----------|---------------------|
| **Simplicité familiale** | L’organisateur gère tout ; les participants n’ont qu’un lien magique. |
| **Confidentialité du tirage** | Personne ne voit « qui a tiré qui », sauf l’organisateur si besoin d’arbitrage. |
| **Convivialité guidée** | Souhaits, budget et messages aident à offrir sans spoiler l’identité du Santa. |
| **Fiabilité du tirage** | L’algo respecte exclusions et auto-exclusion ; échec explicite si impossible. |
| **Une édition claire** | Avant / après tirage : règles de verrouillage nettes, reset volontaire uniquement. |
| **Évolutions sobres** | Prioriser ce qui sert la famille cette année, pas une roadmap SaaS. |

---

## 3. Acteurs et parcours

### Organisateur (`ROLE_ADMIN`)
- Connexion email / mot de passe (espace `/admin`).
- Crée et maintient les participants, exclusions, configuration.
- Lance ou réinitialise le tirage ; suit les conversations ; relance si besoin.

### Participant (lien magique `tokenSecret`)
- Accède via `/participant/{token}` (pas de compte).
- Gère ses souhaits ; après tirage : voit sa cible, ses envies, et la messagerie Santa ↔ cible.
- Ne découvre jamais l’identité de *son* Santa via l’UI.

### Visiteur
- Page d’accueil marketing : présentation du service familial.

---

## 4. Objectifs produit

1. **Réduire le stress d’organisation** : un seul tableau de bord pour préparer, tirer, communiquer.
2. **Garantir un tirage valide** : ≥ 3 participants, pas soi-même, exclusions respectées.
3. **Aider à offrir utilement** : listes de souhaits structurées dans un budget partagé.
4. **Préserver la magie** : anonymat du Santa côté participants ; orgas en filet de sécurité.
5. **Rester maintenable** : stack Symfony classique, déploiement connu, pas de dette « multi-tenant ».

---

## 5. Fonctionnalités (cœur métier)

### 5.1 Participants
- Ajout / liste / suppression (blocage si déjà assigné après tirage).
- Nom et email uniques ; token secret généré à la création.
- Email de bienvenue avec lien personnel.

### 5.2 Exclusions
- Uni-directionnelles ou bidirectionnelles (couple, etc.).
- Prises en compte au tirage (contrainte source → cible).
- Interface admin dédiée, contrainte d’unicité en base.

### 5.3 Configuration d’édition *(cible produit · à consolider)*
- **Budget max** par souhait / édition (aujourd’hui 50 € effectif côté emails / règles).
- **Date de l’événement** (affichage + rappels).
- Templates d’email avec variables `{SANTA}`, `{TARGET}`, `{BUDGET}`.
- Verrouillage des réglages critiques une fois le tirage lancé (sauf reset).

### 5.4 Listes de souhaits
- Titre, description, lien externe, prix estimé, ordre de préférence.
- Respect du budget (validation métier).
- Visibles par le Santa assigné après tirage.
- Image (champ prévu) : URL ou upload simple · pas de CDN complexe.

### 5.5 Tirage au sort
- Préconditions : ≥ 3 participants, aucun tirage déjà actif.
- Algorithme : mélange + retries (plafond de sécurité) ; refus clair si insoluble.
- Persistance des assignations ; envoi des emails de résultat.
- Reset admin : efface assignations **et** messages de l’édition (action consciente).

### 5.6 Messagerie post-tirage
- Fil bidirectionnel **Santa ↔ cible** (types `to_santa` / `to_target`), sans révéler l’identité du Santa.
- Statut lu / non-lu ; plafond de longueur raisonnable.
- L’organisateur peut consulter les conversations (médiation, abus, oublis).
- *Choix produit assumé :* plus riche qu’un « mot unique non modifiable » · utile pour clarifier tailles, disponibilités, etc. tout en gardant l’anonymat.

### 5.7 Emails
- Bienvenue, résultat du tirage, rappel souhaits, confirmation de message.
- Envoi groupé au tirage ; rappels et ré-envois exposés depuis l’admin.

### 5.8 Administration
- Dashboard : stats participants / souhaits / messages / état du tirage.
- CRUD participants & exclusions, écran tirage, vue messages, configuration.

---

## 6. Spécificités et règles métier

| Règle | Détail |
|-------|--------|
| Mono-instance | Une famille, une base, une édition active. |
| Minimum de tirage | 3 participants. |
| Auto-exclusion | Impossible de s’offrir à soi-même. |
| Exclusions | Directionnelles ; le mode « mutuel » crée les deux sens. |
| Unicité | Nom et email uniques. |
| Budget | Plafond partagé ; validation à l’ajout / édition de souhait. |
| Accès participant | Secret d’URL long (64 hex) ; à ne pas partager. |
| Accès admin | Compte User Symfony + `ROLE_ADMIN`, zone `/admin`. |
| Post-tirage | Pas de nouveau tirage sans reset ; suppression participant bloquée si assigné. |
| Anonymat | Le participant voit sa *cible*, jamais son Santa ; l’orgas voit tout si besoin. |

---

## 7. Expérience utilisateur (cible)

- Ton **festif et clair**, sans jargon technique.
- Parcours organisateur en **checklist** : participants → exclusions → config → souhaits remplis → tirage → suivi.
- Parcours participant en **3 actions max** après le mail : ouvrir le lien, compléter les souhaits, (après tirage) voir la cible / écrire.
- Mobile-first pour les proches qui ne passent que sur téléphone.
- Messages d’erreur humains (« tirage impossible avec ces exclusions ») plutôt que techniques.

### Design

L’interface doit être **sobre et soignée**, jamais « tchip » ni bas de gamme : ambiance Noël élégante, pas kit de foire.

- **Couleurs** : palette de Noël maîtrisée (rouge profond, vert sapin, or discret, neutres chauds) · contrastes lisibles, pas de néons ni de dégradés criards.
- **Cohérence** : une seule direction visuelle (typo, espacements, boutons, états) sur admin, participant et emails.
- **Harmonie** : peu d’éléments, hiérarchie claire, respiration ; le festif vient de la palette et du détail, pas du bruit.
- **Ton** : un peu **rigolo / chaleureux**, jamais enfantin ni kitsch.
- **Pas d’emojis** dans l’UI ni les mails produit ; à la place, de **belles icônes** (jeu unique, traits nets, poids cohérent · ex. Font Awesome ou équivalent soigné).
- **Interdit** : stickers flottants, confettis CSS excessifs, empilement de badges, emoji-as-icon, effets « glow » cheap.

---

## 8. Stack et contraintes techniques (cadre)

- **Backend** : Symfony 6.4, Doctrine ORM, MySQL 8.
- **UI** : Twig + Bootstrap 5, Asset Mapper.
- **Emails** : Symfony Mailer (Mailhog en local).
- **Déploiement** : Docker local, Deployer en prod.
- **Hors scope volontaire** : API publique, notifications push, multi-tenant, OAuth social.

Ces choix restent le cadre : toute nouvelle idée doit s’y loger sans pivoter l’architecture.

### Choix de langage (durable)

La stack **PHP 8 + Symfony + Twig** (avec un peu de JS/Stimulus si besoin) est le choix assumé pour la fiabilité et la durée : adaptée aux parcours formulaires / emails / admin, LTS Symfony, sans SPA inutile pour une instance mono-famille. Une réécriture TypeScript full-stack n’est pas un objectif · le levier produit reste config, verrouillage, emails et tests.

### Tests (non négociable)

Les tests sont **obligatoires** et doivent rester **robustes** · pas de couverture cosmétique.

| Type | Rôle | Exigences |
|------|------|-----------|
| **Unitaires** | Services purs (tirage, validation budget, exclusions) | Cas nominaux + cas limites (graphe impossible, < 3 participants, exclusions denses) ; déterministes, sans I/O réseau. |
| **Fonctionnels** | Contrôleurs / parcours HTTP (admin, participant par token, tirage, messagerie) | Assertions sur statut, flash, redirections, données visibles ; auth et tokens couverts. |
| **Intégration** | Scénario d’édition complet (participants → exclusions → souhaits → tirage → emails → messages) | Base réelle (test) ; Mailer mocké ou Mailhog ; un reset/rejeu propre. |

Règles :
- Toute évolution du **cœur métier** (tirage, exclusions, verrouillage, budget) exige des tests qui échouent si la règle casse.
- Pas de merge / déploiement d’une vague A–C sans tests verts sur ces zones.
- Préférer des tests **lisibles et stables** (peu de mocks fragiles) à un volume de tests creux.

### Déploiement

L’exploitation reste alignée sur l’existant :

- **Local** : Docker (MySQL, PhpMyAdmin, Mailhog) + Makefile / Symfony proxy · environnement reproductible.
- **Production** : **Deployer** (branche `main`, shared `.env.local` / logs, assets AssetMapper) · déploiement scripté, pas manuel « au feeling ».
- Objectif : une édition peut être poussée et ramenée à un état sain sans improvisation serveur ; secrets hors dépôt ; pas de dépendances d’infra hors ce duo Docker (dev) / Deployer (prod).

---

## 9. État d’avancement (honnête)

### Solide aujourd’hui
- Participants, exclusions, tirage, emails de base, souhaits, messagerie bidirectionnelle, admin authentifié, landing, infra locale / déploiement.

### À consolider (priorité haute · renforce le produit sans le changer)
1. **Configuration persistée** : budget, date d’événement, paramètres d’email · brancher l’UI existante à une vraie entité / table.
2. **Verrouillage post-tirage** : souhaits et exclusions figés (ou clairement « édition clôturée ») jusqu’au reset.
3. **Rappels & ré-envoi** : exposer dans l’admin les envois déjà prévus dans les services.
4. **URLs d’emails** : base URL depuis la config Symfony, plus d’hôte figé.
5. **Suite de tests robuste** : unitaires (algo), fonctionnels (parcours), intégration (édition bout-en-bout) · non négociable.
6. **Image des souhaits** : activer URL simple *ou* retirer le champ mort pour éviter la confusion.

### Améliorations atteignables (priorité moyenne)
- Mode **dry-run** : simuler le tirage sans emails ni persistance (ou avec rollback).
- Import CSV des participants (nom + email) pour gagner du temps chaque année.
- Export récap organisateur (appariements + messages) après l’événement, pour archives privées.
- Guide court « comment ça marche » lié depuis la landing et le mail de bienvenue.
- Confirmation explicite avant reset du tirage ( irreversible pour l’édition ).

### Hors priorité (volontairement différé)
- Multi-familles / multi-éditions historiques complexes.
- API REST, push, PWA.
- Stats avancées type analytics.
- Stimulus « tout dynamique » : utile en polish, pas bloquant métier.

---

## 10. Feuille de route proposée

### Vague A · « Édition solide »
Configuration réelle + verrouillage post-tirage + rappels / ré-envois + URLs correctes + **tests unitaires / fonctionnels / intégration robustes**.

### Vague B · « Confort famille »
Dry-run, import CSV, image souhait (URL), guide utilisateur, polish mobile messagerie / souhaits.

### Vague C · « Archives & sérénité »
Export orgas, historique léger d’une édition précédente (optionnel), durcissement tokens / rotation si fuite de lien.

Chaque vague doit pouvoir se livrer **avant Noël** sans réécrire le modèle mental mono-famille.

---

## 11. Critères de succès

Une édition est réussie si :
1. L’organisateur prépare participants + exclusions + budget en moins d’une demi-heure.
2. Chaque participant reçoit un email clair et un lien qui fonctionne.
3. Le tirage aboutit (ou échoue avec une explication actionnable sur les exclusions).
4. Les Santas trouvent assez d’info dans les souhaits / messages pour offrir dans le budget.
5. Aucun participant ne découvre son Santa via l’application.
6. Un reset + nouveau tirage reste possible si l’orgas l’assume explicitement.
7. La suite de tests (unitaires, fonctionnels, intégration) est verte avant tout déploiement.

---

## 12. Positionnement final

**Secret Santa Familial** est un outil **privé, annuel, mono-instance** : la magie du tirage, la praticité des souhaits, et un filet d’administration · sans ambition de plateforme.  
Renforcer le produit, c’est **fermer les écarts config / verrouillage / emails / tests**, pas élargir le périmètre.
