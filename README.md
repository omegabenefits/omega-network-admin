# OMEGA Network Admin

Private WordPress multisite network-management plugin.

## Requirements

- WordPress multisite 6.0+ (tested through 7.0)
- PHP 8.0+

## Installation

### Conventional network plugin

The `plugin/` directory is the installable runtime. Extract a release archive to
`wp-content/plugins/omega-network-admin/`, then Network Activate it. The
`Network: true` header prevents per-site activation.

This mode supports the conventional self-hosted update package and WordPress's
normal plugin update flow.

### Optional must-use deployment

MU deployment is manual and uses the same runtime archive:

1. Copy `mu-loader/ona-loader.php` once to `wp-content/mu-plugins/`.
2. Extract the release archive so its runtime is at
   `wp-content/mu-plugins/omega-network-admin/`.

The loader includes the same main plugin file before network-activated and regular
plugins. It stays in place across routine releases; replace only the runtime
directory. The updater can report available MU updates, but deployment remains a
manual file replacement.

Do not also install or network-activate a copy from `wp-content/plugins/`.

### Redirect public signup and unknown subdomains

WordPress performs Multisite site lookup before it loads either regular,
network-activated, or must-use plugins. As a result, its default redirect for an
unknown subdomain must be configured in `wp-config.php`.

Add this before the `wp-settings.php` include:

```php
define( 'NOBLOGREDIRECT', '%siteurl%' );
```

`%siteurl%` is WordPress's built-in placeholder for the network main site, so it
avoids hard-coding an environment-specific hostname. This configuration redirects
unknown sites to the network main site instead of `wp-signup.php`. ONA redirects
direct `/wp-signup.php` requests there too, before the signup screen can render.

## WP-CLI network runner

When WP-CLI loads ONA, `wp site all <command> [args...]` runs one fresh
WP-CLI process for every URL from `wp site list --field=url`, in the same order.
The runner forces each process to its matching `--url`, even when the caller
supplies a different URL. It emits each child command's output unchanged,
preserving terminal formatting unless `--no-color` was requested. It keeps
running after a site failure and exits non-zero after the final site has been
processed.

```sh
wp site all plugin status
wp site all option update my_option value
wp site all my-plugin reindex --dry-run
```

`wp site all site all ...` is rejected to prevent recursive process creation.

### Manual verification

Run this only on a disposable multisite test network with at least three sites.
First note the expected URL order, then verify argument forwarding, per-site
targeting, and cleanup:

```sh
wp site list --field=url
wp site all option update ona_site_all_verification ok
wp site all option get ona_site_all_verification --format=json
wp site all option delete ona_site_all_verification
```

Then verify that a failure does not stop later sites. Site ID `2` fails, all
other sites complete (including the third site), and the final command exits
non-zero:

```sh
wp site all eval 'exit( 2 === (int) get_current_blog_id() ? 1 : 0 );'
```

## MU-only plugin suppression

When ONA runs through `ona-loader.php`, the per-site `omega_suppress_plugins`
option maps plugin basenames to a flag, for example
`array( 'directory/plugin.php' => true )`. Basenames with a truthy value are
excluded from active-plugin lists on non-admin, non-WP-CLI requests, so WordPress
does not load their files for that site. Writers add and remove entries with
`$opt[ $file ] = true` and `unset( $opt[ $file ] )`. This feature is intentionally
inactive in conventional network-plugin mode.

## Releasing

`release/build-zip.command` creates
`release/dist/omega-network-admin-<version>.zip` from the committed `plugin/`
directory. The archive excludes `mu-loader/` and works for both conventional and
manual MU deployment.

Commit the intended version before building. Copy
`release/deploy.env.example` to `release/deploy.env` to enable the script's SFTP
upload to the self-hosted update server; without that file it only builds the ZIP.
