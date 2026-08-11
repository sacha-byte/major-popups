# Migration depuis Popup Maker

**Statut (2026-08-11)** : les 6 popups ci-dessous ont été créés en **brouillon** (inactifs) dans `major-popups` avec les réglages recommandés par défaut — y compris sur les 3 points qui restaient à trancher : #4 en Option A (taxonomie), #5 en ciblage taxonomie Prépa=ECT, #6 restreint à "Articles" (reflète l'anomalie constatée, pas de tagging inventé). À relire dans wp-admin (Popups) et publier un par un après vérification.

Audit effectué le 2026-08-11 sur la copie DevKinsta (`majorprepa.local`). 6 popups actifs en base. Pour chacun, la configuration `major-popups` recommandée à saisir manuellement dans wp-admin (Popups → Ajouter un popup).

## 1. Popup Sidebar (Popup Maker #170299)

- **Code d'embed** : reprendre le `<script data-slug="...">` déjà présent dans le popup Popup Maker actuel.
- **Déclenchement** : délai, 1s (repris à l'identique — très court, à confirmer que c'est voulu).
- **Cookie** : activé, 30 j (l'ancien avait un `cookie_name` mais pas d'entrée dans le tableau `cookies` — comportement ambigu côté Popup Maker, 30j est un défaut raisonnable).
- **Ciblage** : `specific_posts` — liste de 52 posts (tous encore publiés, vérifié) + 1 terme de catégorie (17540, "Newsletter"). **Note** : `major-popups` v1 ne sait pas combiner "posts spécifiques" ET "terme de catégorie" dans la même règle — soit garder les 52 posts (`specific_posts`), soit basculer sur `taxonomy_terms` (catégorie Newsletter) si c'est le critère qui compte vraiment. À trancher à la saisie.

## 2. Guide Ouverture sociale 2025 (popup) (#171215)

- **Déclenchement** : délai, 3s.
- **Cookie** : activé, 30 j (1 mois).
- **Ciblage** : `specific_posts` — 3 posts (170487, 164263, 97064, tous publiés).

## 3. Guide Rentrée en prépa 2025 (#172253)

- **Déclenchement** : délai, 3s.
- **Cookie** : activé, 21 j (3 semaines).
- **Ciblage** : condition d'origine = page d'accueil + tous les `post` + tous les `academique`. Recommandé : `post_types` = Articles + Académique. **Écart connu** : si la page d'accueil n'est pas la liste des derniers articles, elle ne sera plus ciblée spécifiquement (`major-popups` v1 n'a pas de mode "page d'accueil" dédié) — impact mineur, à vérifier visuellement.

## 4. Guide prépas littéraires 2026 (#192104)

- **Déclenchement** : délai, 3s.
- **Cookie** : activé, 30 j (1 mois).
- **Ciblage d'origine** : tous les `post` + articles `academique` **tagués prépa = "Prépa Littéraire"** (terme 17487 → 2256 articles académiques concernés).
- **Deux options pour la migration** (à choisir par vous, comportement différent) :
  - **A. Fidèle au ciblage actuel** : `taxonomy_terms`, taxonomie Prépa, terme "Prépa Littéraire" — mais alors les `post` classiques ne seront plus couverts (perte du volet "tous les articles").
  - **B. Fidèle au filet large actuel** : `post_types` = Articles + Académique — mais alors les ~8125 articles Académique seront tous couverts, pas seulement ceux en Prépa Littéraire (élargissement réel du ciblage).

## 5. Guide prépas ECT - 2026 (popup) (#193568)

- **Déclenchement** : délai, 3s.
- **Cookie** : activé, 14 j (2 semaines).
- **Ciblage d'origine** : 5 conditions combinées (OR) — tous les `post`, + académique/matière "Éco-droit ECT", + académique/prépa "ECT", + n'importe quel contenu taxonomie Filière=ECT, + n'importe quel contenu taxonomie Prépa=ECT. Résolu : **1062 contenus distincts** (tous types confondus) matchent au moins une de ces conditions aujourd'hui.
- **Recommandation** : `taxonomy_terms`, taxonomie Prépa, terme "ECT" — capture la majorité de l'intention (filière ECT) en un seul mode. Alternative plus fidèle mais lourde : `specific_posts` avec les 1062 IDs (je peux générer la liste complète si vous préférez cette voie).

## 6. Guide oraux HEC 2026 (#197940) — ⚠️ anomalie détectée

- **Déclenchement** : délai, 3s. **Cookie** : activé, 30 j.
- **Ciblage d'origine** : tous les `post` + académique tagué École = "HEC Paris". **Le terme "HEC Paris" (taxonomie École, id 17508) n'est actuellement rattaché à AUCUN contenu du site, quel que soit le type** — vérifié en base. Ce popup ne cible donc concrètement que les `post` classiques ; son volet "HEC Paris" ne s'est probablement jamais déclenché depuis sa création.
- **À décider avant la migration** : soit c'est un oubli de tagging (des articles académiques sur les oraux HEC existent mais n'ont jamais reçu le terme "HEC Paris" — à corriger côté contenu), soit le ciblage `post_types = Articles` seul reflète déjà ce qui se passe réellement en prod. Pas une décision technique — à vérifier avec vous avant de configurer ce popup.

## Après la saisie manuelle des 6

Comparer le rendu de chaque popup `major-popups` à son équivalent Popup Maker (déclenchement, contenu, ciblage) sur `majorprepa.local` avant de désactiver Popup Maker sur les pages concernées.
