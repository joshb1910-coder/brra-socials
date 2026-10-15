# buryrefs.org.uk on WordPress (test copy)

The WordPress version of the Bury & Rochdale RA website, built to replace the Wix site before it renews in January 2027. The full plan is in the project files at `web/wordpress-redevelopment-plan.md`.

## Try it

Open the test site in your browser (nothing to install; it runs in the browser and resets when you close the tab):

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/joshb1910-coder/brra-socials/claude/project-thread-kira97/website/playground/blueprint.json

Admin: add `/wp-admin/` to the address and log in as `admin` / `password`.

## What's here

- `theme/brra/`: the block theme (navy and gold brand, Montserrat and Open Sans, header, footer, page, news and event templates).
- `plugins/brra-site/`: Events (dates, times, RSVP, add-to-calendar), Committee, and the membership rules for Paid Memberships Pro: season pricing, the automatic 25% first-time discount, and expiry on 31 August.
- `setup/build.php`: builds the whole site on a fresh WordPress with Paid Memberships Pro active (`wp eval-file setup/build.php`). Safe to re-run.
- `setup/pages/`, `setup/media/`, `setup/news/`: page content, images and sample news copied from the current site.
- `playground/`: the blueprint and zips for the test link. After changing the theme, plugin or setup, run `python3 playground/build-zips.py` from this folder and commit the zips.

## Membership rules

- Full Member is £20 a season. Anyone whose email has never been a member pays £15, worked out on the server at checkout.
- Past members are anyone with a membership record on the site, plus emails in the "Past members" list under Memberships > New member price (paste in the Wix member list at go-live).
- A season runs 1 June to 31 May; memberships expire on 31 August so renewals have a grace period.
