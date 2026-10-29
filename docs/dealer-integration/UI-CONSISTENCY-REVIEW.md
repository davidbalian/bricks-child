# Dashboard consistency review · 29 October 2026

Scope: the private dealer integration dashboard, its dialogs, reviewer sign-in and one-time credential receipt. Main-site styling and global enqueues are unchanged. All review and regression tests used the disposable loopback WordPress fixture; visual-only receipt/reviewer previews used synthetic values and did not create accounts.

## Common rules

- Inter variable font is declared consistently on dashboard and sign-in/receipt documents.
- Type scale: body/navigation/labels 14px; secondary text 12px; controls 16px; section headings 18px; page headings 28px desktop/24px mobile. Headings and emphasized text use weight 600.
- Spacing follows 4/8/12/16/24/32px steps. Cards use 24px desktop padding and 16px phone padding. Tables, labels and dialog sections follow the same spacing scale.
- Text inputs, selects and labeled buttons remain 48px high with 16px text. Textareas retain their row-based height. The reusable kit supplies these rules.
- Icon-only controls and pagination have a minimum 44px target. Photo controls align under 96px thumbnails. Button strokes track text weight; active navigation icons use the matching thicker stroke.
- Dashboard cards and controls use 8px corners, while circular avatars retain their intended shape.

## Responsive fixes

The header stays on one row on narrow phones, secondary header information yields to the logo/account controls, and wrapped metric labels keep their numbers aligned. Phone filters use a full-width search field, and card headings may wrap instead of clipping counts.

Dialog actions stack at phone widths. Dialogs remain within the viewport, scroll internally in landscape, and avoid doubled spacing before the first field. Escape closes a dialog and restores focus to its trigger. Tables and navigation scroll inside their own containers instead of widening the page.

## Local coverage

| Surface | Widths checked | Result |
| --- | --- | --- |
| Connections, stock, activity, reviewer access | 320, 390, 768, 1024, 1280px | No page overflow; control dimensions/fonts consistent |
| Empty views, reviewer header, sign-in error, credential receipt | 320, 390, 768, 1024, 1280px | No page overflow; controls consistent; header fits |
| Add dealer, connect, manage connection, edit stock, disconnected stock dialogs | 320, 390, 768, 1280px | Dialog bounds, internal width, control sizes and utility targets pass |
| Search/clear and empty filtered results | 320, 390, 768, 1024, 1280px | Controls fit their panels without clipping |
| Landscape stock dialog | 844 × 390px | Fits viewport and scrolls internally |

The 79 existing workspace checks and 70 reviewer checks passed, including public-page asset isolation and permission boundaries. The changed PHP template passed syntax validation. The reusable kit ZIP is regenerated from the committed source. No production tests were run.
