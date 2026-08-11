# Major Popups

Remplace **Popup Maker** — Bloc 2 de la refonte de [major-prepa.com](https://major-prepa.com). Le contenu de chaque popup est un formulaire **Lead Manager** (`app.2empower.com`, code d'embed `embed-formulaire.js` + slug se terminant en `-popup`) ; ce plugin ne fait que décider **quand et où** l'afficher.

Contexte complet du projet (pourquoi ce plugin existe, audit de l'existant, décisions d'architecture) : voir `major-prepa-wp/ROADMAP.md` et `CONTEXT.md` dans [2empower-projects](https://github.com/sacha-byte/2empower-projects) (pilotage, pas le code).

## Décision d'architecture

Le pilotage (actif/inactif, ciblage, déclenchement, cookie) vit **dans ce plugin**, pas dans Lead Manager — Lead Manager reste un simple fournisseur de formulaire embarquable (code + lien), sans connaissance du modèle de contenu WordPress (CPTs/taxonomies/ACF) qui pilote le ciblage réel des popups. Voir `CONTEXT.md` du projet pour le détail de cet arbitrage.

## Structure

```
major-popups.php              Bootstrap du plugin
includes/
  class-cpt.php                CPT `mp_popup` (non public, admin uniquement)
  class-acf-fields.php         Champs ACF : embed_code, lien_direct, déclenchement, cookie, ciblage
  class-targeting.php          Évalue si un popup matche la requête courante
  class-render.php             Sélectionne les popups à afficher, injecte markup + config JSON en footer
  class-assets.php             Enqueue CSS/JS (versionnés par filemtime, pas une constante figée)
  class-cache-compat.php       Exclut major-popups.js du "Delay JS" WP Rocket
assets/
  css/major-popups.css         Overlay, carte, croix, bottom sheet mobile (≤640px)
  js/major-popups.js           Timers délai/scroll, cookie, ouverture/fermeture, focus trap, Échap
```

## Modèle de données (CPT `mp_popup`)

- **Actif/inactif** : statut natif WP (`publish` = actif, `draft` = inactif).
- `embed_code` : code d'embed Lead Manager (HTML/`<script>` brut, non échappé au rendu — contenu admin de confiance).
- `trigger_type` (`delay` | `scroll`) + `trigger_delay_seconds` / `trigger_scroll_percent`.
- `cookie_enabled` + `cookie_days`.
- `targeting_mode` (`all` | `post_types` | `specific_posts` | `taxonomy_terms`) + champs associés.

Post types/taxonomies de ciblage confirmés par audit direct de la base (2026-08-11) : `post`, `page`, `academique` (8125 articles, le plus gros contenu du site), `master`, `prepa` ; taxonomies `category`, `filiere`, `prepa`, `matiere`, `ecole`.

## WP Rocket

Le code d'embed est injecté dynamiquement en JS au moment de l'affichage (pas de `<script>` statique dans le HTML) — WP Rocket "Delay JS" ne le voit jamais. En revanche `major-popups.js` lui-même est explicitement exclu de ce délai (`class-cache-compat.php`), sinon son minuteur ne démarrerait qu'après une interaction utilisateur (scroll/clic/mousemove), ratant les visiteurs qui n'interagissent jamais.

## Migration depuis Popup Maker

Voir `MIGRATION.md` pour la correspondance avec les 6 popups actuellement actifs (Popup Maker) et les listes de posts résolues pour les ciblages basés sur des conditions custom (`academique_w_prepa`/`academique_w_ecole`/`academique_w_matiere`).
