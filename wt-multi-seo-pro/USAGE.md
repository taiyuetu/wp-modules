# WT Multi SEO Pro - Usage Guide

## Overview

WT Multi SEO Pro is a WordPress SEO plugin with multilingual support via Polylang.

It provides:
- Per-post SEO fields (title, description, canonical, OG, Twitter, robots)
- Per-taxonomy SEO fields
- Custom post type archive SEO settings
- XML sitemap generation
- Hreflang output
- Canonical URL handling
- Open Graph and Twitter cards
- Structured data (JSON-LD)
- Robots and indexing controls

---

## Requirements

- WordPress `6.0+`
- PHP `8.0+`
- Polylang plugin (must be active)

If Polylang is not active, the plugin shows an admin notice and does not fully initialize.

---

## Installation

1. Copy the plugin folder to:
   - `wp-content/plugins/wt-multi-seo-pro`
2. Activate **WT multi SEO Pro** from WordPress Admin > Plugins.
3. Go to **Polylang SEO** in the WP admin sidebar.

---

## Admin Pages

Main menu: `Polylang SEO`

Tabs/subpages:
- General
- Sitemap
- Social / OG
- Structured Data
- Robots & Index
- Performance
- Tools

Use each tab to set global behavior, then override on posts/terms/archives where needed.

---

## General Settings

### 1) Title Templates
Configure reusable templates with tokens such as:
- `%%title%%`
- `%%sitename%%`
- `%%tagline%%`
- `%%sep%%`
- `%%page%%`
- `%%currentyear%%`
- `%%searchterm%%`

You can set templates for:
- Homepage
- Single posts/pages
- Archives
- Search results
- 404 pages

### 2) SEO Metabox Coverage
Choose which post types and taxonomies should show plugin SEO fields in the editor screens.

### 3) Custom Post Type Archive SEO
Use this section for CPT archives that do not have a real editable page.

For each public CPT archive, you can set:
- SEO Title
- Meta Description
- Canonical URL
- Open Graph Title
- Open Graph Description
- Open Graph Image URL
- Twitter Image URL
- Inherit Defaults toggle

#### Inherit Defaults (per CPT archive)
When enabled:
- Empty OG title falls back to SEO title
- Empty OG description falls back to meta description
- Empty Twitter title/description follows OG/SEO fallback chain
- Empty Twitter image falls back to OG image

This gives behavior similar to major SEO plugins while still allowing manual override.

---

## Post/Page SEO Metabox

Open any supported post/page editor and scroll to **SEO Settings**.

Fields available:
- SEO Title
- Meta Description
- Canonical URL
- Open Graph Title / Description / Image
- Twitter Image
- Noindex / Nofollow
- Disable plugin SEO output for this item

### Default values (auto-filled when empty)
- SEO Title -> post title
- Meta Description -> trimmed words from post content
- Canonical URL -> post permalink
- OG Title -> SEO Title
- OG Description -> Meta Description
- OG Image -> featured image URL
- Twitter Image -> featured image URL

These defaults make setup faster and keep metadata complete even if editors skip manual entry.

---

## Taxonomy SEO

For enabled taxonomies, term-level SEO settings are available from taxonomy edit screens.
Use these to override archive title/description/social metadata per term/language.

---

## Social Metadata

The plugin outputs:
- Open Graph tags (`og:title`, `og:description`, `og:image`, etc.)
- Twitter card tags (`twitter:title`, `twitter:description`, `twitter:image`, etc.)

Priority is generally:
1. Item-specific custom fields (post/term/CPT archive)
2. Smart fallbacks (title/description/image inheritance)
3. Global defaults (for example default OG image)

---

## Canonical URLs

Canonical tags are output automatically.

Controls include:
- Force HTTPS
- Trailing slash normalization
- Strip query parameters
- Custom canonical per post
- Custom canonical for CPT archives

---

## Robots and Indexing

Global controls are available for:
- Search pages
- 404 pages
- Author/date archives
- Attachments
- Empty taxonomies

Per-post controls:
- Noindex
- Nofollow
- Disable plugin SEO output

---

## Sitemap

The plugin can generate XML sitemaps with options for:
- Posts/pages/CPTs
- Taxonomies
- Images
- Hreflang entries
- Search engine ping

Tools page also includes:
- Sitemap quick link
- Ping search engines button
- Flush rewrite rules action

---

## Multilingual (Polylang)

The plugin integrates with Polylang:
- Language-aware output for hreflang/meta
- Translation helpers in SEO metabox UI
- Per-language metadata support through translated content entities

Best practice:
- Fill SEO fields for each language translation for maximum control.

---

## Recommended Workflow

1. Configure global templates in **General**.
2. Enable relevant post types/taxonomies.
3. Configure **Social / OG** global defaults (especially default image).
4. Configure **CPT Archive SEO** for archive-only content types.
5. Add/verify per-post SEO metadata in editor metaboxes.
6. Save settings and test frontend source (`View Page Source`).
7. Validate social previews using external tools (Facebook/Twitter debuggers).

---

## Troubleshooting

### Polylang dependency warning
- Ensure Polylang is installed and activated.

### Changes not reflected on frontend
- Clear site/page cache and CDN cache.
- Re-save plugin settings.
- Use **Tools > Flush Rewrite Rules** if archive/sitemap URLs behave unexpectedly.

### Missing archive metadata
- Confirm the post type is public and has archive enabled.
- Check `General > Custom Post Type Archive SEO` values for that post type.

### Empty social image
- Set featured image on posts, or set default OG image globally.
- For CPT archive pages, set OG/Twitter image in CPT Archive SEO.

---

## Notes for Developers

- Option keys are stored with `plseo_` prefix.
- Post meta keys use `_plseo_*`.
- CPT archive settings are stored under option:
  - `plseo_cpt_archive_seo`

Structure example:

```php
[
  'product' => [
    'inherit_defaults' => '1',
    'title' => 'Products %%sep%% %%sitename%%',
    'description' => 'Browse our product catalog',
    'canonical' => 'https://example.com/products/',
    'og_title' => '',
    'og_description' => '',
    'og_image' => '',
    'twitter_image' => ''
  ]
]
```

