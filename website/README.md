# buryrefs.org.uk on WordPress (test copy)

The WordPress version of the Bury & Rochdale RA website, built to replace the Wix site before it renews in January 2027. The full plan is in the project files at `web/wordpress-redevelopment-plan.md`.

## Try it

Open the test site in your browser (nothing to install; it runs in the browser and resets when you close the tab):

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/joshb1910-coder/brra-socials/claude/project-thread-kira97/website/playground/blueprint.json

Admin: add `/wp-admin/` to the address and log in as `admin` / `password`.

## What's here

- `theme/brra/`: the block theme (navy and gold brand, Montserrat and Open Sans, header, footer, page, news and event templates).
- `plugins/brra-site/`: Events (dates, times, RSVP, add-to-calendar), Committee, and the membership rules for Paid Memberships Pro: season pricing, the automatic 25% first-time discount, and expiry on 31 August.
- `setup/build.php`: builds the whole site on a fresh WordPress with Paid Memberships Pro active. On a live site it runs from Tools > BRRA site setup (the plugin zip bundles `setup/`); locally, `wp eval-file setup/build.php`. Safe to re-run, but it resets the four main pages.
- `setup/pages/`, `setup/media/`: page content and images.
- `setup/news/wix-posts.json`: all 124 posts from the Wix blog, made by `setup/wix/export.py` from Wix API responses. Covers and in-post pictures still point at Wix until Tools > Copy Wix images is run on the live site.
- `playground/`: the blueprint and zips for the test link, which are also the launch-day upload pack. After changing the theme, plugin or setup, run `python3 playground/build-zips.py` from this folder and commit the zips.

## Old Wix links

`/post/<slug>`, `/blog` and `/blog/categories/<slug>` redirect to the matching WordPress page, so links shared on social media keep working.

## Membership rules

- Full Member is £20 a season. Anyone whose email has never been a member pays £15, worked out on the server at checkout.
- Past members are anyone with a membership record on the site, plus emails in the "Past members" list under Memberships > New member price. Members who have already paid this season are added on the same screen ("Add current members"), which gives them an account and membership without charging them.
- A season runs 1 June to 31 May; memberships expire on 31 August so renewals have a grace period.
