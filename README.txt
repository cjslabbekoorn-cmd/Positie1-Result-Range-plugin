Listing Results Range (v1.0.8)
=========================

Elementor Widget
----------------
Name: "Results Range" (General)

WPML Support
-----------
Domain: results-range-wpml
The following widget fields are registered per widget instance (Elementor widget ID):
- Label template
- Compact template
- Result label (singular)
- Result label (plural)
- AJAX template

After placing the widget and visiting the page once, translate via:
WPML → String Translation → domain "results-range-wpml"

Filtering / AJAX
----------------
For JetSmartFilters/JetEngine AJAX updates (e.g. "Apply on: change value"), the widget can update its text on the fly.

Because the filtered total is not always available client-side, you can choose:
- Keep last known total (no override)
- Omit total (recommended): uses AJAX template like "Toont {start}-{end} {results_label}"
- Use visible count as total

Optional "Query ID" helps target the correct listing when multiple Jet listings exist on one page.

No extra CSS is loaded; styling is via Elementor Style controls.

Changelog
---------
New in 1.0.5
- Version bump / release packaging update.

New in 1.0.4
- Added GitHub Releases updater (public repo friendly; optional token for private repos).

Credits / Sources
-----------------
- GitHub Releases update mechanism inspired by a lightweight custom updater pattern (WordPress plugin update API + GitHub Releases).
