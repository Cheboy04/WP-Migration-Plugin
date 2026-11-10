<?php
/**
 * Copyright (C) 2014-2025 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Attribution: This code is part of the All-in-One WP Migration plugin, developed by
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

if ( defined( 'WP_CLI' ) ) {
	/**
	 * Mindsoft: WP-CLI commands. They drive the same export/import pipeline as the
	 * browser; with WP_CLI defined the controllers run every stage in this process.
	 */
	class Ai1wm_WP_CLI_Command extends WP_CLI_Command {

		/**
		 * Creates a .wpress backup in the backups folder.
		 *
		 * ## OPTIONS
		 *
		 * [--exclude=<items>]
		 * : Comma-separated list of content to leave out: media, themes, plugins, muplugins, database, spam-comments, post-revisions, email-replace
		 *
		 * [--password=<password>]
		 * : Encrypt the backup with this password.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp ai1wm export
		 *     $ wp ai1wm export --exclude=spam-comments,post-revisions
		 *
		 * @subcommand export
		 */
		public function export( $args = array(), $assoc_args = array() ) {
			$this->require_single_site();

			$options = array();
			if ( ! empty( $assoc_args['exclude'] ) ) {
				$allowed = array( 'media', 'themes', 'plugins', 'muplugins', 'database', 'spam-comments', 'post-revisions', 'email-replace' );
				foreach ( array_map( 'trim', explode( ',', $assoc_args['exclude'] ) ) as $item ) {
					if ( ! in_array( $item, $allowed, true ) ) {
						WP_CLI::error( sprintf( 'Unknown --exclude item "%s". Allowed: %s', $item, implode( ', ', $allowed ) ) );
					}

					$options[ 'no_' . str_replace( '-', '_', $item ) ] = 1;
				}
			}

			if ( ! empty( $assoc_args['password'] ) ) {
				$options['encrypt_backups']  = 1;
				$options['encrypt_password'] = $assoc_args['password'];
			}

			$params = Ai1wm_Export_Controller::export(
				array(
					'secret_key' => get_option( AI1WM_SECRET_KEY ),
					'options'    => $options,
				)
			);

			WP_CLI::success( sprintf( 'Backup created: %s', ai1wm_backup_path( $params ) ) );
		}

		/**
		 * Alias of export.
		 *
		 * ## OPTIONS
		 *
		 * [--exclude=<items>]
		 * : Comma-separated list of content to leave out: media, themes, plugins, muplugins, database, spam-comments, post-revisions, email-replace
		 *
		 * [--password=<password>]
		 * : Encrypt the backup with this password.
		 *
		 * @subcommand backup
		 */
		public function backup( $args = array(), $assoc_args = array() ) {
			$this->export( $args, $assoc_args );
		}

		/**
		 * Imports a .wpress file from any path on the server. Replaces the current site.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Path to the .wpress file.
		 *
		 * [--yes]
		 * : Answer yes to the confirmation prompt.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp ai1wm import /home/user/site.wpress --yes
		 *
		 * @subcommand import
		 */
		public function import( $args = array(), $assoc_args = array() ) {
			$this->require_single_site();

			$file = $args[0];
			if ( ! is_file( $file ) || ! ai1wm_is_filename_supported( $file ) || ! ai1wm_is_filedata_supported( $file ) ) {
				WP_CLI::error( sprintf( 'Not a valid .wpress file: %s', $file ) );
			}

			$params = array(
				'storage' => ai1wm_storage_folder(),
				'archive' => basename( $file ),
			);

			// The pipeline reads the archive from its storage folder; the clean step deletes this copy
			WP_CLI::log( 'Copying archive to the storage folder...' );
			try {
				ai1wm_copy( $file, ai1wm_archive_path( $params ) );
			} catch ( Exception $e ) {
				WP_CLI::error( wp_strip_all_tags( $e->getMessage() ) );
			}

			$this->run_import( $params, $assoc_args );
		}

		/**
		 * Restores a backup from the backups folder. Replaces the current site.
		 *
		 * ## OPTIONS
		 *
		 * [<file>]
		 * : Backup file name. Omit it to list the available backups.
		 *
		 * [--yes]
		 * : Answer yes to the confirmation prompt.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp ai1wm restore
		 *     $ wp ai1wm restore example-com-20261110-120000-abc123.wpress --yes
		 *
		 * @subcommand restore
		 */
		public function restore( $args = array(), $assoc_args = array() ) {
			$this->require_single_site();

			if ( empty( $args[0] ) ) {
				$backups = array();
				foreach ( Ai1wm_Backups::get_files() as $backup ) {
					$backups[] = array(
						'file' => $backup['filename'],
						'size' => $backup['size'] === null ? '' : size_format( $backup['size'] ),
						'date' => wp_date( 'Y-m-d H:i', $backup['mtime'] ),
					);
				}

				if ( empty( $backups ) ) {
					WP_CLI::log( sprintf( 'No backups found in %s', AI1WM_BACKUPS_PATH ) );
					return;
				}

				WP_CLI\Utils\format_items( 'table', $backups, array( 'file', 'size', 'date' ) );
				return;
			}

			// With ai1wm_manual_restore the pipeline reads the archive straight from the backups folder
			$params = array(
				'storage'              => ai1wm_storage_folder(),
				'archive'              => $args[0],
				'ai1wm_manual_restore' => 1,
			);

			try {
				$path = ai1wm_backup_path( $params );
			} catch ( Exception $e ) {
				WP_CLI::error( wp_strip_all_tags( $e->getMessage() ) );
			}

			if ( ! is_file( $path ) ) {
				WP_CLI::error( sprintf( 'Backup not found: %s. Run "wp ai1wm restore" to list backups.', $path ) );
			}

			$this->run_import( $params, $assoc_args );
		}

		private function run_import( $params, $assoc_args ) {
			$params['secret_key'] = get_option( AI1WM_SECRET_KEY );

			// Import_Confirm passes these to WP_CLI::confirm(), so --yes skips the prompt
			$params['cli_args'] = $assoc_args;

			Ai1wm_Import_Controller::import( $params );

			WP_CLI::success( 'Import complete.' );
		}

		private function require_single_site() {
			if ( is_multisite() ) {
				WP_CLI::error( 'Multisite networks are not supported.' );
			}
		}
	}
}
