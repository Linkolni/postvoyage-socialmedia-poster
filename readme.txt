=== PostVoyage - Free Social Media Post Distributor ===
Contributors: alexandervolland
Tags: social media, auto post, mastodon, bluesky, telegram
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Share your posts automatically on Mastodon, Bluesky, Telegram, Discord, Facebook, Threads, LinkedIn, X and webhooks – free, with rules per category.

== Description ==

PostVoyage shares new posts automatically on your social media channels. All features are free – no pro version, no limits, no external service in between. Your site talks directly to the networks.

**Networks**

* Mastodon (and compatible Fediverse servers)
* Bluesky – with clickable links, hashtags and link card with image
* Telegram channels and groups
* Discord (webhook) – as card with image
* Facebook pages
* Threads
* LinkedIn profiles and company pages
* X (Twitter) – posting quota depends on your X API plan
* Webhook – JSON to n8n, Make, Zapier & co. (e.g. for Instagram or Pinterest)

**Rules per category**

Decide per rule which posts go to which accounts: by post type, category (optionally with subcategories) and language. Each rule has its own text template, optionally with a separate template per network.

**Templates with variables**

`{title}`, `{excerpt}`, `{content}`, `{url}`, `{permalink}`, `{shortlink}`, `{hashtags}`, `{category_hashtags}`, `{tags}`, `{categories}`, `{author}`, `{date}`, `{site_name}`, `{site_url}`, `{language}`, `{network}` and `{meta:your_field}`. Texts are shortened automatically to the limit of each network – links are never cut.

**Made for WordPress**

* Works in the block editor and the classic editor
* Multilingual: Polylang and WPML (rules per language)
* Runs in the background (WP-Cron or Action Scheduler), retries on temporary errors
* Never posts twice; "Share now" for manual re-sharing
* Credentials are stored encrypted
* Log with links to every published post
* Translation ready (German included)
* Extensible with filters and actions

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New.
2. Activate the plugin.
3. Go to PostVoyage → Accounts and connect your first account. The help text next to each network explains where to get the credentials.
4. Adjust the rule "All posts" under PostVoyage → Rules or add your own rules.

== Frequently Asked Questions ==

= Does it cost anything? =

No. All features are free. Some networks limit their APIs themselves (for example X, depending on your API plan).

= When is a post shared? =

When it is published for the first time (also scheduled posts), after a short delay in the background. Updates of published posts are not shared again automatically – use "Share now" in the editor box.

= Why do I need a delay? =

The block editor saves the box settings shortly after publishing. The delay makes sure your choice ("Share on publish", custom text) is respected.

= Can I use different texts per language? =

Yes. With Polylang or WPML create one rule per language, each with its own template and accounts.

== Changelog ==

= 0.1.0 =
* First release: Mastodon, Bluesky, Telegram, Discord, Facebook, Threads, LinkedIn, X and webhook; rules per post type, category and language; templates with variables; log; German translation.
