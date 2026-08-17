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

## MU-only plugin suppression

When ONA runs through `ona-loader.php`, the per-site `omega_suppress_plugins`
option can list plugin basenames (for example,
`directory/plugin.php`). Listed plugins are excluded from active-plugin lists on
non-admin, non-WP-CLI requests, so WordPress does not load their files for that
site. This feature is intentionally inactive in conventional network-plugin mode.

## Releasing

`release/build-zip.command` creates
`release/dist/omega-network-admin-<version>.zip` from the committed `plugin/`
directory. The archive excludes `mu-loader/` and works for both conventional and
manual MU deployment.

Commit the intended version before building. Copy
`release/deploy.env.example` to `release/deploy.env` to enable the script's SFTP
upload to the self-hosted update server; without that file it only builds the ZIP.
