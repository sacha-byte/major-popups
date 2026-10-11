# Migration depuis Popup Maker

**Statut (2026-08-11)** : les 6 popups ci-dessous ont été créés en **brouillon** (inactifs) dans `major-popups`. Les 4 arbitrages (1 sur le popup #1, 3 sur les popups #4/#5/#6) ont été tranchés par l'utilisateur — tous confirment les réglages par défaut déjà posés, sauf le #1 dont le ciblage a été mis à jour en base en conséquence :

- **#1 (Sidebar)** : bascule confirmée vers `taxonomy_terms` (catégorie Newsletter, terme 17540) plutôt que la liste figée de 52 posts — appliqué en base. 59 posts publiés portent actuellement ce terme (contre 52 dans l'ancienne liste figée), écart cohérent avec le fait que la catégorie continue de vivre depuis la création du popup Popup Maker.
- **#4 (Prépa Littéraire)** : Option A confirmée (`taxonomy_terms`, Prépa = "Prépa Littéraire") — déjà en place, rien à changer.
- **#5 (ECT)** : ciblage simplifié confirmé (`taxonomy_terms`, Prépa = "ECT") — déjà en place, rien à changer.
- **#6 (Oraux HEC)** : `post_types = Articles` confirmé, pas d'anomalie de tagging à corriger côté contenu — déjà en place, rien à changer.

**Prochaine étape** : comparaison visuelle de chaque popup `major-popups` face à son équivalent Popup Maker sur `majorprepa.local`, puis publication un par un (`post_status` brouillon → publié) avant de désactiver Popup Maker.

Audit effectué le 2026-08-11 sur la copie DevKinsta (`majorprepa.local`). 6 popups actifs en base. Pour chacun, la configuration `major-popups` recommandée à saisir manuellement dans wp-admin (Popups → Ajouter un popup).

## 1. Popup Sidebar (Popup Maker #170299)

- **Code d'embed** : reprendre le `<script data-slug="...">` déjà présent dans le popup Popup Maker actuel.
- **Déclenchement** : délai, 1s (repris à l'identique — très court, à confirmer que c'est voulu).
- **Cookie** : activé, 30 j (l'ancien avait un `cookie_name` mais pas d'entrée dans le tableau `cookies` — comportement ambigu côté Popup Maker, 30j est un défaut raisonnable).
- **Ciblage d'origine** : `specific_posts` — liste de 52 posts (tous encore publiés, vérifié) + 1 terme de catégorie (17540, "Newsletter"). `major-popups` v1 ne sait pas combiner "posts spécifiques" ET "terme de catégorie" dans la même règle.
- **Décision (2026-08-11)** : `taxonomy_terms`, catégorie "Newsletter" (terme 17540) — plus pérenne que la liste figée, couvrira aussi les futurs articles tagués Newsletter. Appliqué en base.

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
- **Deux options pour la migration** (comportement différent) :
  - **A. Fidèle au ciblage actuel** : `taxonomy_terms`, taxonomie Prépa, terme "Prépa Littéraire" — mais alors les `post` classiques ne seront plus couverts (perte du volet "tous les articles").
  - **B. Fidèle au filet large actuel** : `post_types` = Articles + Académique — mais alors les ~8125 articles Académique seront tous couverts, pas seulement ceux en Prépa Littéraire (élargissement réel du ciblage).
- **Décision (2026-08-11)** : Option A confirmée. Déjà en place en base.

## 5. Guide prépas ECT - 2026 (popup) (#193568)

- **Déclenchement** : délai, 3s.
- **Cookie** : activé, 14 j (2 semaines).
- **Ciblage d'origine** : 5 conditions combinées (OR) — tous les `post`, + académique/matière "Éco-droit ECT", + académique/prépa "ECT", + n'importe quel contenu taxonomie Filière=ECT, + n'importe quel contenu taxonomie Prépa=ECT. Résolu : **1062 contenus distincts** (tous types confondus) matchent au moins une de ces conditions aujourd'hui.
- **Recommandation** : `taxonomy_terms`, taxonomie Prépa, terme "ECT" — capture la majorité de l'intention (filière ECT) en un seul mode. Alternative plus fidèle mais lourde : `specific_posts` avec les 1062 IDs.
- **Décision (2026-08-11)** : ciblage simplifié confirmé. Déjà en place en base.

## 6. Guide oraux HEC 2026 (#197940) — ⚠️ anomalie détectée

- **Déclenchement** : délai, 3s. **Cookie** : activé, 30 j.
- **Ciblage d'origine** : tous les `post` + académique tagué École = "HEC Paris". **Le terme "HEC Paris" (taxonomie École, id 17508) n'est actuellement rattaché à AUCUN contenu du site, quel que soit le type** — vérifié en base. Ce popup ne cible donc concrètement que les `post` classiques ; son volet "HEC Paris" ne s'est probablement jamais déclenché depuis sa création.
- **Décision (2026-08-11)** : `post_types = Articles` confirmé comme reflétant l'intention réelle — pas d'oubli de tagging à corriger côté contenu. Déjà en place en base.

## Avant de désactiver Popup Maker

Comparer le rendu de chaque popup `major-popups` à son équivalent Popup Maker (déclenchement, contenu, ciblage) sur `majorprepa.local`, popup par popup, puis publier (`post_status` brouillon → publié) une fois validé.
