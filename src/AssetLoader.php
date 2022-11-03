<?php
/**
 * Asset management for Webpack delivered scripts and stylesheets
 *  - Seamless switching between production built and webpack-dev-server asset modes
 *  - Blocks development mode on production domains, checks base URL against Production domain list
 *  - NOTE: Use standard enqueuing methods for non-Webpack generated assets
 * =============
 * Usage:
 *  - Register all scripts and stylesheets with dependencies using the appropriate register_* call
 *      - NOTE: If the same entry point is registered multiple times, the final calls dependencies are used
 *  - Call enqueue_assets() inside an 'wp_enqueue_scripts', 'enqueue_block_assets', or similar action hook
 */

namespace Kanopi\Assets;

use Kanopi\Assets\Model;
use Exception;

class AssetLoader {
	/**
	 * @var object
	 */
	protected $_asset_manifest;

	/**
	 * @var string
	 */
	protected string $_base_url;

	/**
	 * @var Model\LoaderConfiguration
	 */
	protected Model\LoaderConfiguration $_configuration;

	/**
	 * @var ?string
	 */
	protected ?string $_development_url_base;

	/**
	 * @var array
	 */
	protected array $runtime_scripts = [];

	/**
	 * @var array
	 */
	protected array $scripts = [];

	/**
	 * @var array
	 */
	protected array $styles = [];

	/**
	 * @var bool
	 */
	protected bool $use_production = true;

	/**
	 * @var array
	 */
	protected array $vendor_scripts = [];

	/**
	 * @var array
	 */
	protected array $vendor_styles = [];

	/**
	 * WebpackAssets constructor.
	 *
	 * @param string                         $_base_url
	 * @param string|null                    $_development_url
	 * @param Model\LoaderConfiguration|null $_configuration
	 *
	 * @throws Exception Missing base asset URL
	 */
	public function __construct(
		string $_base_url,
		?string $_development_url,
		Model\LoaderConfiguration $_configuration = null
	) {
		if ( empty( trim( $_base_url ) ) ) {
			throw new Exception( '', 'Base URL required for webpack asset registration' );
		}

		$this->_base_url             = $this->check_string( $_base_url, '' );
		$this->_development_url_base = $this->check_string( $_development_url, null );
		$this->_configuration        = !empty( $_configuration ) ? $_configuration : new Model\LoaderConfiguration();
		$this->use_production        =
			$this->process_production_state( $this->_base_url, $this->_development_url_base );
		$this->_asset_manifest       = $this->asset_manifest();
	}

	/**
	 * Check if a subject string ends with a test string, pre PHP 8.0 compliant
	 *
	 * @param string $_subject
	 * @param string $_test
	 *
	 * @return bool
	 */
	protected function ends_with( string $_subject, string $_test ): bool {
		return $_test === substr( $_subject, -1 * strlen( $_test ) );
	}

	/**
	 * Check if a subject string starts with a test string, pre PHP 8.0 compliant
	 *
	 * @param string $_subject
	 * @param string $_test
	 *
	 * @return bool
	 */
	protected function starts_with( string $_subject, string $_test ): bool {
		return $_test === substr( $_subject, 0, strlen( $_test ) );
	}

	protected function asset_manifest() {
		$base     = $this->use_production ? $this->_base_url : $this->_development_url_base;
		$filename = $base . $this->_configuration->asset_manifest_path();
		$manifest = null;

		if ( file_exists( $filename ) || $this->starts_with( $filename, 'http' ) ) {
			$context_arguments = [
				'http' => [ 'ignore_errors' => true ]
			];

			if ( $this->starts_with( $filename, 'https' ) ) {
				$context_arguments[ 'ssl' ] = [
					'verify_peer'      => false,
					'verify_peer_name' => false
				];
			}

			// phpcs:ignore -- Intended to run fresh and inside a registry/singleton provide per request cache
			$data = file_get_contents( $filename, false, stream_context_create( $context_arguments ) );

			if ( !empty( $data ) ) {
				$manifest = json_decode( $data );
			}
		}

		return $manifest;
	}

	/**
	 * Reads from the manifest, otherwise falls back to the asset name
	 *
	 * @param string $_base
	 * @param string $_path
	 * @param string $_entry
	 * @param string $_file_type
	 *
	 * @return string
	 */
	protected function build_entry_url(
		string $_base,
		string $_path,
		string $_entry,
		string $_file_type
	): string {
		// Ensures the manifest exists
		$manifest_entry = !empty( $this->_asset_manifest ) && property_exists( $this->_asset_manifest, $_entry )
			? $this->_asset_manifest->$_entry
			: null;

		// Finds any manifest entry path
		$entry_path = !empty( $manifest_entry ) && property_exists( $manifest_entry, $_file_type )
			? $manifest_entry->$_file_type
			: null;
		
		if ( is_array( $entry_path ) ) {
			$entry_path = $this->use_production ? $entry_path[ count( $entry_path ) - 1 ] : $entry_path[ 0 ];
		}

		// Build a manifest URL
		$entry_url = $entry_path ?: null;
		if ( !empty( $entry_url ) ) {
			$entry_slug = $this->ends_with( $_path, $_file_type . '/' )
				? str_replace( $_file_type . '/', '', $_path )
				: $_path;
			$entry_url  = $this->starts_with( $entry_path, $_base ) ? $entry_path : $_base . $entry_slug . $entry_path;
		}

		// Use a manifest URL, fallback to structure URL
		return $entry_url ?: $_base . $_path . $_entry . '.' . $_file_type;
	}

	/**
	 * @param string      $_entry
	 * @param string|null $_default
	 *
	 * @return string
	 */
	protected function check_string( string $_entry, ?string $_default ): string {
		return empty( trim( $_entry ) ) ? $_default : trim( $_entry );
	}

	/**
	 * Run standard WordPress assets enqueues based on environment state, call this when finished registration
	 */
	public function enqueue_assets() {
		$last_script_dependency = '';
		$base                   = $this->use_production ? $this->_base_url : $this->_development_url_base;

		// Add all vendor scripts, sequentially chained off the previous script
		foreach ( $this->vendor_scripts as $entry => $entry_dependencies ) {
			$last_script_dependency =
				$this->enqueue_chained_script( $base, $entry, $entry_dependencies, $last_script_dependency );
		}

		// For production, add the runtime chunk script
		foreach ( $this->runtime_scripts as $entry => $entry_dependencies ) {
			$last_script_dependency =
				$this->enqueue_chained_script( $base, $entry, $entry_dependencies, $last_script_dependency );
		}

		// Add each vendor style, in Development add the last script dependency, then chain each vendor style sequentially
		$last_style_dependency = $this->use_production ? '' : $last_script_dependency;
		foreach ( $this->vendor_styles as $entry => $entry_dependencies ) {
			$last_style_dependency =
				$this->enqueue_chained_style( $base, $entry, $entry_dependencies, $last_style_dependency );
		}

		// Add all application scripts
		foreach ( $this->scripts as $entry => $entry_dependencies ) {
			$this->enqueue_chained_script( $base, $entry, $entry_dependencies, $last_script_dependency );
		}

		// Add all application stylesheets
		foreach ( $this->styles as $entry => $entry_dependencies ) {
			$this->enqueue_chained_style( $base, $entry, $entry_dependencies, $last_style_dependency );
		}
	}

	/**
	 * Enqueue a footer script; returns the script's handle for chaining
	 *
	 * @param string $_base
	 * @param string $_entry
	 * @param array  $_dependencies
	 * @param string $_last_chain
	 *
	 * @return string
	 */
	protected function enqueue_chained_script(
		string $_base,
		string $_entry,
		array $_dependencies,
		string $_last_chain
	): string {
		$dependencies = !empty( $_last_chain )
			? array_merge( $_dependencies, [ $_last_chain ] )
			: $_dependencies;
		$handle       = $this->prefixed_entry_name( $_entry );

		wp_enqueue_script(
			$handle,
			$this->build_entry_url( $_base, $this->_configuration->script_path(), $_entry, 'js' ),
			$dependencies,
			$this->_configuration->version(),
			true
		);

		return $handle;
	}

	/**
	 * Enqueue a stylesheet in Production, or footer script in Development; returns the style's handle for chaining
	 *
	 * @param string $_base
	 * @param string $_entry
	 * @param array  $_dependencies
	 * @param string $_last_chain
	 *
	 * @return string
	 */
	protected function enqueue_chained_style(
		string $_base,
		string $_entry,
		array $_dependencies,
		string $_last_chain
	): string {
		$handle       = $this->prefixed_entry_name( $_entry );
		$dependencies = !empty( $_last_chain )
			? array_merge( $_dependencies, [ $_last_chain ] )
			: $_dependencies;

		if ( $this->use_production ) {
			wp_register_style(
				$handle,
				$this->build_entry_url( $_base, $this->_configuration->style_path(), $_entry, 'css' ),
				$dependencies,
				$this->_configuration->version() );
			wp_enqueue_style( $handle );
		}
		else {
			wp_enqueue_script(
				$handle,
				$this->build_entry_url( $_base, $this->_configuration->script_path(), $_entry, 'js' ),
				$dependencies,
				$this->_configuration->version(),
				true
			);
		}

		return $handle;
	}

	public function in_development_mode(): bool {
		return !$this->use_production;
	}

	/**
	 * @param string $_url
	 *
	 * @return bool
	 */
	protected function is_production_domain( string $_url ): bool {
		preg_match( '@^(?:https?://)?([^/]+)@i', $_url, $base_url_parts );

		return is_array( $base_url_parts )
			&& 1 < count( $base_url_parts )
			&& in_array( strtolower( $base_url_parts[ 1 ] ),
				$this->_configuration->production_domains(),
				true );
	}

	/**
	 * Sets production mode for empty/missing development URLs
	 *
	 * @param string      $_base_url
	 * @param string|null $_development_url
	 *
	 * @return bool
	 */
	protected function process_production_state( string $_base_url, ?string $_development_url ): bool {
		return empty( $_development_url ) || $this->is_production_domain( $_base_url );
	}

	/**
	 * @param string $_entry
	 *
	 * @return string
	 */
	protected function prefixed_entry_name( string $_entry ): string {
		return $this->_configuration->handle_prefix() . $_entry;
	}

	/**
	 * Register Webpack delivered application (contains both scripts and styles in Production, scripts in development)
	 *
	 * @param string $_entry_point
	 * @param array  $_dependencies
	 */
	public function register_applications( string $_entry_point, array $_dependencies = [] ) {
		$this->register_script( $_entry_point, $_dependencies );

		if ( !$this->in_development_mode() ) {
			$this->register_style( $_entry_point, $_dependencies );
		}
	}

	/**
	 * Set/overwrite an asset with dependencies by entry point
	 *
	 * @param array  $_target
	 * @param string $_entry
	 * @param array  $_dependencies
	 */
	protected function register_dependencies( array &$_target, string $_entry, array $_dependencies ) {
		$entry = strtolower( trim( $_entry ) );
		if ( !empty( $entry ) ) {
			$_target[ $entry ] = $_dependencies;
		}
	}

	/**
	 * Register any webpack-dev-server runtime dependencies
	 *
	 * @param string $_entry_point
	 * @param array  $_dependencies
	 */
	public function register_runtime_script( string $_entry_point, array $_dependencies = [] ) {
		$this->register_dependencies( $this->runtime_scripts, $_entry_point, $_dependencies );
	}

	/**
	 * Register Webpack delivered application scripts
	 *
	 * @param string $_entry_point
	 * @param array  $_dependencies
	 */
	public function register_script( string $_entry_point, array $_dependencies = [] ) {
		$this->register_dependencies( $this->scripts, $_entry_point, $_dependencies );
	}

	/**
	 * Register Webpack delivered application stylesheets
	 *
	 * @param string $_entry_point
	 * @param array  $_dependencies
	 */
	public function register_style( string $_entry_point, array $_dependencies = [] ) {
		$this->register_dependencies( $this->styles, $_entry_point, $_dependencies );
	}

	/**
	 * Register Webpack delivered vendor applications (mix of scripts and styles in production, just scripts in development)
	 *
	 * @param string $_entry_point
	 * @param array  $_dependencies
	 */
	public function register_vendor_application( string $_entry_point, array $_dependencies = [] ) {
		$this->register_vendor_script( $_entry_point, $_dependencies );

		if ( !$this->in_development_mode() ) {
			$this->register_vendor_styles( $_entry_point, $_dependencies );
		}
	}

	/**
	 * Register Webpack delivered vendor scripts
	 *
	 * @param string $_entry_point
	 * @param array  $_dependencies
	 */
	public function register_vendor_script( string $_entry_point, array $_dependencies = [] ) {
		$this->register_dependencies( $this->vendor_scripts, $_entry_point, $_dependencies );
	}

	/**
	 * Register Webpack delivered vendor stylesheets
	 *
	 * @param string $_entry_point
	 * @param array  $_dependencies
	 */
	public function register_vendor_styles( string $_entry_point, array $_dependencies = [] ) {
		$this->register_dependencies( $this->vendor_styles, $_entry_point, $_dependencies );
	}

	/**
	 * Static file path for the current development/production environment
	 *
	 * @param string $_file_path
	 *
	 * @return string
	 */
	public function static_assets_url( string $_file_path ): string {
		return ( $this->use_production ? $this->_base_url : $this->_development_url_base )
			. $this->_configuration->static_path()
			. $_file_path;
	}
}