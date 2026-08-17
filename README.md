# OMEGA Network Admin

Private WordPress network-management plugin repo.

- `plugin/` is the conventional runtime. Release archives extract as
  `wp-content/plugins/omega-network-admin/` and can be network-activated normally.
- MU deployment is optional and manual: copy `mu-loader/ona-loader.php` once to
  `wp-content/mu-plugins/`, then extract the same runtime archive as
  `wp-content/mu-plugins/omega-network-admin/`. The loader makes the runtime load
  before network-activated and regular plugins.
- `release/build-zip.command` builds `release/dist/omega-network-admin-<version>.zip`.
  The archive is independent of the MU loader and is used for normal plugin installs,
  manual MU deployment, and the self-hosted update server's metadata checks.
- Copy `release/deploy.env.example` to `release/deploy.env` to enable upload.
