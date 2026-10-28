# Private dashboard UI

The dealer dashboard uses [Tabler UI](https://docs.tabler.io/ui/getting-started) 1.7.1 and [Tabler Icons](https://docs.tabler.io/icons) 3.50.0, both MIT licensed. Tabler fits the existing server-rendered PHP forms; shadcn/ui would introduce React/Tailwind dependencies, and daisyUI would introduce Tailwind. No frontend build step or new JavaScript runtime is required.

## Isolation and branding

Only `workspace-template.php` and `reviewer-template.php` load `assets/tabler/tabler-1.7.1.min.css`, followed by `assets/workspace.css` and `assets/button-kit/buttons.css`. These are standalone integration documents. There is no global enqueue, `wp_head` import, or change to the marketplace's `style.css`. The public marketplace does not load Tabler. The reviewer sign-in and one-time credential receipt use the same dashboard components.

Change dashboard colors, font and radii in the `html:has(body.agw)` token block at the top of `assets/workspace.css`. AutoAgora blue is `--tblr-primary: #0d86e3`. Buttons retain white text and the homepage action sizing through Tabler's button variables. Component appearance comes from Tabler; the remaining CSS handles branding, layout, responsive tables and native dialogs.

Cards, headers, nav pills, avatars, alerts, tables, badges, buttons, labels, inputs, selects and permission lists use Tabler classes. Native `<dialog>` and the existing event handlers retain keyboard/focus behavior, form nonces and server-side permissions. Tests, dealer connections, account access and moderation rules are unchanged.

## Vendored assets

`assets/tabler/sources.json` records exact npm tarballs and SHA-512 integrity values. Only the core stylesheet, MIT licenses and a small icon subset are included; no optional plugins or Tabler JavaScript are shipped. SVGs are bundled and allowlisted by `Workspace::icon()`; they inherit component colors. Add an icon only by explicitly extending that map and adding its upstream SVG.

The npm stylesheet's optional external font import and source-map reference were removed. It contains no external asset URLs; Inter is served from the existing local Bricks font. The Tabler, Bootstrap and icon licenses are retained beside the assets. Updating the library is an explicit vendor update, followed by local visual and integration checks; deployment never fetches `latest` from a CDN.

## Local verification

Sync with `python3 tests/dealer-integration/sync-local.py`. Run the existing `review_checks.py` and `reviewer_checks.py` against the disposable loopback WordPress fixture only. The review suite also checks that the dashboard uses Tabler and the public homepage omits its styles. Inspect connections, stock, activity, reviewer access, dialogs and sign-in at desktop and phone widths. Never run these fixture tests on production.

29 October 2026 local results: all 79 workspace checks and 70 reviewer checks passed. PHP syntax passed for the four changed PHP files. Connections, stock, activity, reviewer access, sign-in and dialogs were inspected locally; the 390px viewport stays within the page width while tables and navigation scroll in their own containers. Production tests were not run.

## Reusable buttons

The approved button appearance lives in `assets/button-kit/buttons.css`, shared by the dashboard and reviewer templates. The `ag-buttons` wrapper opts in. See `assets/button-kit/README.md` and `preview.html` for reuse, colors, weights, states and icon placement. The numeric `--ag-button-weight` token controls both text and SVG stroke, so changing text weight keeps them paired. Navigation icons are unaffected.
