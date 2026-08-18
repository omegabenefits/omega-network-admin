<?php
/**
 * WP-CLI command for running one command against every site in a network.
 */

/**
 * Implements the `wp site all` WP-CLI subcommand.
 */
class OMEGA_Network_Admin_Site_All_Command {
	/**
	 * Runs a WP-CLI command sequentially on every site in the current network.
	 *
	 * Site URLs are collected from `wp site list --field=url`, so this command
	 * uses the same sites and ordering as WP-CLI core. Each invocation runs in a
	 * fresh child WP-CLI process with that URL forced as its `--url` value.
	 *
	 * ## OPTIONS
	 *
	 * <command>...
	 * : The WP-CLI command and arguments to run for every site.
	 *
	 * [--<field>=<value>]
	 * : An option to forward to the command, including boolean and negated flags.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show plugin status on every network site.
	 *     $ wp site all plugin status
	 *
	 *     # Update an option on every network site.
	 *     $ wp site all option update my_option value
	 *
	 *     # Run a custom command safely on every network site.
	 *     $ wp site all my-plugin reindex --dry-run
	 *
	 * @param string[]       $args       The WP-CLI command and its positional arguments.
	 * @param array<string,mixed> $assoc_args The command options to forward.
	 * @return void
	 */
	public static function run( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This command requires a multisite installation.' );
		}

		if ( empty( $args ) ) {
			WP_CLI::error( 'Specify a WP-CLI command to run on every site.' );
		}

		if ( isset( $args[1] ) && 'site' === $args[0] && 'all' === $args[1] ) {
			WP_CLI::error( 'Refusing recursive invocation of `wp site all`.' );
		}

		$site_list = self::run_child_command( array( 'site', 'list' ), array( 'field' => 'url' ) );

		if ( 0 !== $site_list->return_code ) {
			$message = trim( $site_list->stderr );
			WP_CLI::error( 'Unable to list network sites.' . ( '' === $message ? '' : "\n" . $message ) );
		}

		$site_urls = preg_split( '/\r\n|\r|\n/', trim( $site_list->stdout ) );
		$site_urls = array_values( array_filter( $site_urls, 'strlen' ) );
		$command_args = $args;
		$failed       = false;

		foreach ( $site_urls as $site_url ) {
			try {
				$result = self::run_child_command( $command_args, $assoc_args, $site_url );
			} catch ( Throwable $exception ) {
				$failed = true;
				WP_CLI::error( sprintf( 'Command failed for %s: %s', $site_url, $exception->getMessage() ), false );
				continue;
			}

			if ( '' !== $result->stdout ) {
				WP_CLI::line( rtrim( $result->stdout ) );
			}

			if ( '' !== $result->stderr ) {
				WP_CLI::error( rtrim( $result->stderr ), false );
			}

			if ( 0 !== $result->return_code ) {
				$failed = true;
				WP_CLI::error( sprintf( 'Command failed for %s (exit code %d).', $site_url, $result->return_code ), false );
			}
		}

		if ( $failed ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Launches a fresh child WP-CLI process while preserving the PHP environment.
	 *
	 * `WP_CLI::launch_self()` does not pass environment variables to child
	 * processes. `runcommand()` does, which is required by WordPress installs
	 * that load database or bootstrap settings from their environment.
	 *
	 * @param string[]           $args       The command and its positional arguments.
	 * @param array<string,mixed> $assoc_args The command options.
	 * @param string|null        $site_url   The URL to force for a target site.
	 * @return object{stdout:string,stderr:string,return_code:int} Child process results.
	 */
	private static function run_child_command( $args, $assoc_args, $site_url = null ) {
		$command = implode( ' ', array_map( 'escapeshellarg', $args ) );

		if ( null !== $site_url ) {
			unset( $assoc_args['url'] );
		}

		$options = \WP_CLI\Utils\assoc_args_to_str( $assoc_args );
		if ( '' !== $options ) {
			$command .= ' ' . $options;
		}

		if ( null !== $site_url ) {
			// This final global argument takes precedence over a caller-supplied --url.
			$command .= ' --url=' . escapeshellarg( $site_url );
		}

		if ( self::should_force_color( $assoc_args ) ) {
			// Captured child output is piped, so WP-CLI would otherwise disable color.
			$command .= ' --color';
		}

		return WP_CLI::runcommand(
			$command,
			array(
				'launch'     => true,
				'exit_error' => false,
				'return'     => 'all',
			)
		);
	}

	/**
	 * Determines whether child commands should retain terminal color formatting.
	 *
	 * @param array<string,mixed> $assoc_args The command options.
	 * @return bool Whether to force color in the child process.
	 */
	private static function should_force_color( $assoc_args ) {
		if ( isset( $assoc_args['color'] ) && false === $assoc_args['color'] ) {
			return false;
		}

		return false !== WP_CLI::get_config( 'color' );
	}
}
