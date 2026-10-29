# AutoAgora Buttons · v1.2

The approved AutoAgora dashboard button styling, built on Tabler 1.7.1. Includes blue/white colors, subtle Tabler gradient and shadow, 8px corners, 16px text, 12px/24px padding, hover/focus/disabled states, and SVG strokes paired with text weight. Text inputs and selects share the buttons’ 48px height and 16px text. Textareas keep their row-based height and use the same text size.

## Reuse

Copy the `button-kit/` and `tabler/` folders together. Load these styles in order:

```html
<link rel="stylesheet" href="tabler/tabler-1.7.1.min.css">
<link rel="stylesheet" href="button-kit/buttons.css">
<div class="ag-buttons">
  <button type="button" class="btn btn-primary">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M12 5v14M5 12h14" />
    </svg>
    Add dealer
  </button>
</div>
```

Open `button-kit/preview.html` for the visual reference. No JavaScript, network requests or build step are required. In AutoAgora, both dashboard templates load this same CSS; the main site does not.

Tabler's base stylesheet has global rules: load it only in an appropriate application/page, an existing Tabler project or an isolated iframe. The custom button layer is opt-in under `.ag-buttons`. For an existing Tabler 1.7.1 project, copy only `buttons.css` and use the wrapper.

## Customize

Set tokens on the wrapper or an individual button:

```css
.my-button-group {
  --ag-button-color: #0d86e3;
  --ag-button-hover: #0b75c9;
  --ag-button-text: #fff;
  --ag-button-radius: 8px;
  --ag-button-weight: 600;
  --ag-control-height: 3rem;
  --ag-control-font-size: 1rem;
  --ag-control-line-height: 1.5rem;
}
```

Use numeric weights `400` (regular), `500` (medium), `600` (semibold, default) or `700` (bold). Text and SVG stroke derive from the same token. Change `--ag-button-weight`, rather than overriding `font-weight` independently. The icon must be an inline stroke SVG with `.icon`; an `<img>` cannot inherit its stroke. Keep `aria-hidden="true"` on decorative icons and a text label; icon-only controls need an `aria-label`.

For an icon at the end, add `.icon-end` (Tabler handles its spacing); for an icon-only utility use `.btn-icon` (44px minimum touch target). Add `disabled` to a disabled button. Existing form, link and permission behavior stays with the application.

Use Tabler’s `.form-control` on text, search, number, password and readonly inputs; use `.form-select` on selects and `.form-control` on textareas. The wrapper applies the shared control sizing everywhere, including dialogs and sign-in forms. Checkbox/radio controls and icon-only utility buttons retain their appropriate sizes.

## Files and licenses

`buttons.css` is the reusable appearance layer. The adjacent `tabler/` folder contains the pinned upstream CSS, selected Tabler SVGs, source integrity metadata and MIT licenses for Tabler, Bootstrap and Tabler Icons. Preserve those licenses when copying or distributing the kit. No AutoAgora data, credentials, logo or fonts are required for reuse; the demo uses system fonts.
