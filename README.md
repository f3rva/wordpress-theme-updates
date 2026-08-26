# WordPress Theme Updates & Snippets (`f3rva`)

This repository contains the custom WordPress child theme, block editor scripts, and functional code snippets for the **F3 RVA** website (`f3rva.org` / `dev.f3rva.org`). 

It manages the backblast authoring pipeline, custom post validation, enhanced RSS feeds, and bidirectional data synchronization with the centralized backend API (**`f3rva-api`**).

---

## Table of Contents
- [Architecture & Synchronization Strategy](#architecture--synchronization-strategy)
- [Repository Structure](#repository-structure)
- [Snippet Catalog](#snippet-catalog)
- [Theme Components](#theme-components)
- [Configuration & Local Development](#configuration--local-development)
- [Deployment Best Practices](#deployment-best-practices)

---

## Architecture & Synchronization Strategy

The WordPress backblast workflow integrates with `f3rva-api` through a dedicated hook architecture designed to solve Gutenberg's asynchronous multi-request lifecycle and race conditions.

```text
                     [ Gutenberg Block Editor / Quick Edit ]
                                        │
                                        ▼
                   ┌────────────────────────────────────────┐
                   │  1. ACF Commits Form Data to MySQL     │
                   │     (workout_date_new, qic, the_pax)   │
                   └───────────────────┬────────────────────┘
                                       │
                         acf/save_post (Priority 20)
                                       │
                                       ▼
                   ┌────────────────────────────────────────┐
                   │  2. update_bigdata($post_id)           │
                   │     Checks $big_data_id in MySQL       │
                   └───────┬────────────────────────┬───────┘
                           │                        │
               empty($big_data_id)          !empty($big_data_id)
                           │                        │
                           ▼                        ▼
                   ┌──────────────┐         ┌──────────────┐
                   │ POST /v2/    │         │ PUT /v2/     │
                   │ workouts     │         │ workouts/:id │
                   └──────┬───────┘         └──────┬───────┘
                          │                        │
                   HTTP 201 Created         HTTP 200 OK
                          │                        │
                          ▼                        ▼
                   ┌────────────────────────────────────────┐
                   │  3. Persist $big_data_id in wp_postmeta│
                   │     (update_field & update_post_meta)  │
                   └────────────────────────────────────────┘
```

### Key Architectural Patterns

1. **`acf/save_post` (Priority 20) Execution**:
   - Generic `save_post` fires too early in Gutenberg, before Advanced Custom Fields (ACF) processes user input.
   - Hooking to `acf/save_post` at priority 20 guarantees that ACF form fields are fully written to MySQL, making our API call and subsequent `update_field('big_data_id', ...)` the **final write**.

2. **State Preservation Filter (`acf/update_value/name=big_data_id`)**:
   - In Gutenberg, clicking "Update" without refreshing the browser sends the stale DOM state (`big_data_id = ""`).
   - The preservation filter checks MySQL: if `big_data_id` already exists, it intercepts the empty submit and preserves the ID.
   - **Testing Reset**: Typing `0` into the field explicitly bypasses preservation and clears the ID for re-testing.

3. **Quick Edit & Bulk Edit Support (`update_bigdata_quick_edit`)**:
   - Standard Quick Edit (`/wp-admin/edit.php`) updates title, author, or tags via AJAX (`inline-save`) without triggering ACF hooks.
   - A dedicated `save_post` listener catches `inline-save` and bulk edit requests to immediately issue `PUT /v2/workouts/{id}`.

4. **Self-Healing on 409 Conflict**:
   - If `big_data_id` is ever desynchronized while date and slug match an existing record, `f3rva-api` returns **HTTP 409 Conflict** (`errorCode: 1007`).
   - The snippet catches the 409, queries `/v2/workouts/by-date-slug`, retrieves the existing ID, and re-links it automatically.

---

## Repository Structure

```text
wordpress-theme-updates/
├── snippet/                            # PHP code snippets (Code Snippets / WPCode)
│   ├── big-data-save-action.php        # Core workout sync (POST / PUT / self-healing)
│   ├── validate-acf.php                # Enqueues editor validation script (child theme safe)
│   ├── sanitize-slug.php               # Slug character cleaner
│   ├── api-workout-date-slug.php       # Custom REST route for date+slug post lookup
│   └── initialize-enhanced-rss-feed.php# Custom /feed/enhanced feed registration
└── themes/
    └── f3-rva/                         # Active child theme (Parent: Twenty Twenty-Five)
        ├── style.css                   # Theme metadata & child template link
        ├── theme.json                  # Block editor styling definitions
        ├── rss-enhanced.php            # Custom RSS feed XML template
        ├── screenshot.png              # Theme preview thumbnail
        ├── readme.txt                  # GPLv2 licensing & metadata
        └── js/
            └── validate-acf.js         # Block editor ACF validation lock
```

---

## Snippet Catalog

| File | Hook / Context | Description |
| :--- | :--- | :--- |
| [`snippet/big-data-save-action.php`](file:///Users/bbischoff/dev/f3/wordpress-theme-updates/snippet/big-data-save-action.php) | `acf/save_post` (20)<br>`save_post` (Quick Edit) | Synchronizes workout backblasts to `f3rva-api`. Handles payload construction, `POST` (create), `PUT` (update), conflict recovery, and ID state preservation. |
| [`snippet/validate-acf.php`](file:///Users/bbischoff/dev/f3/wordpress-theme-updates/snippet/validate-acf.php) | `enqueue_block_editor_assets` | Loads `validate-acf.js` in Gutenberg using `get_stylesheet_directory_uri()` with `filemtime()` cache-busting. |
| [`snippet/sanitize-slug.php`](file:///Users/bbischoff/dev/f3/wordpress-theme-updates/snippet/sanitize-slug.php) | `sanitize_title` | Strips special characters from post slugs, enforcing lowercase alphanumeric characters and single hyphens. |
| [`snippet/api-workout-date-slug.php`](file:///Users/bbischoff/dev/f3/wordpress-theme-updates/snippet/api-workout-date-slug.php) | `rest_api_init` | Registers `GET /wp-json/f3-data/v1/workout-slug-date/{slug}/{date}` for querying posts by slug and ACF `workout_date_new`. |
| [`snippet/initialize-enhanced-rss-feed.php`](file:///Users/bbischoff/dev/f3/wordpress-theme-updates/snippet/initialize-enhanced-rss-feed.php) | `init` | Registers the custom `enhanced` RSS feed endpoint (`/feed/enhanced`). |

---

## Theme Components

### Child Theme: `F3 RVA`
* **Parent Theme**: `twentytwentyfive` (declared via `Template: twentytwentyfive` in `style.css`).
* **Asset Resolution Rule**: Always use `get_stylesheet_directory_uri()` for child theme assets (scripts, styles, templates) rather than `get_template_directory_uri()` (which targets the parent).

### Client-Side Validation: `validate-acf.js`
* **Path**: [`themes/f3-rva/js/validate-acf.js`](file:///Users/bbischoff/dev/f3/wordpress-theme-updates/themes/f3-rva/js/validate-acf.js)
* **Purpose**: Inspects ACF form validity (`acf.validate` / `acf.validation`) and locks the Gutenberg "Publish" button (`dispatch('core/editor').lockPostSaving('acf-required-lock')`) until all mandatory fields are satisfied.

### Custom RSS Feed: `rss-enhanced.php`
* **Path**: [`themes/f3-rva/rss-enhanced.php`](file:///Users/bbischoff/dev/f3/wordpress-theme-updates/themes/f3-rva/rss-enhanced.php)
* **Purpose**: Renders the custom XML feed with tag prefixes (`[Gridiron] Backblast Title`) and formatted permalinks (`https://f3rva.org/YYYY/MM/DD/slug/`).

---

## Configuration & Local Development

### Overriding API Endpoint
By default, `big-data-save-action.php` points to production `https://api.f3rva.org`. To point to a local instance of `f3rva-api`:

In `wp-config.php` (or at the top of the snippet):
```php
define('F3_API_HOST', 'http://localhost:8000');
```

---

## Deployment Best Practices

### Deploying Theme Updates (`themes/f3-rva`)
1. **Packaging**:
   ```bash
   cd themes
   zip -r f3-rva.zip f3-rva
   ```
2. **Uploading via Admin**:
   * Navigate to **Appearance $\rightarrow$ Themes $\rightarrow$ Add New Theme $\rightarrow$ Upload Theme**.
   * Upload `f3-rva.zip` and select **"Replace current with uploaded"**.
3. **Cache Busting**:
   * All enqueued assets use `filemtime()`, ensuring browsers immediately fetch the updated files without manual cache clears.

### Deploying Snippets (`snippet/`)
* Snippets can be imported directly into the **Code Snippets** or **WPCode** WordPress plugin.
* Always verify PHP syntax prior to updating production:
  ```bash
  php -l snippet/big-data-save-action.php
  ```
