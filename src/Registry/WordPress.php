<?php
/**
 * Theme and external script registration helper
 *
 * @author Kanopi Studios.
 */

namespace Kanopi\Assets\Registry;

use Kanopi\Assets\AssetLoader;
use Kanopi\Assets\Model\LoaderConfiguration;
use Exception;

class WordPress {
    const DEFAULT_INSTANCE_NAME = 'theme';

    /**
     * Set of Asset Loader instances
     */
    protected static array $_instances = [];

    /**
     * Asset loader configuration for this instance
     */
    protected LoaderConfiguration $_configuration;

    /**
     * Development base path URL
     */
    protected string $_development_url;

    /**
     * Production base path URL
     */
    protected string $_production_url;

    /**
     * Asset Loader instance for this instance
     */
    protected ?AssetLoader $_loader = null;

    /**
     * Build an asset loader instance with the provided configuration
     *
     * @param LoaderConfiguration   $_configuration     Configuration for the AssetLoader instance
     * @param ?string               $_production_url    (Optional) Production URL base path, defaults to theme directory
     * @param ?string               $_development_url   (Optional) Development URL base path, defaults to KANOPI_DEVELOPMENT_ASSET_URL or empty
     */

    public function __construct(
        LoaderConfiguration $_configuration,
        ?string $_production_url = null,
        ?string $_development_url = null
    ) {
        $this->_configuration = $_configuration;
        $this->_production_url = !empty( $_production_url ) ? $_production_url : get_stylesheet_directory_uri();
        $this->_development_url = !empty( $_development_url )
            ? $_development_url
            : ( defined( 'KANOPI_DEVELOPMENT_ASSET_URL' ) ? KANOPI_DEVELOPMENT_ASSET_URL : '' );

		// Bugfix to workaround limitations with current loader to ensure the manifest is loaded via file path in production
		if ( empty( $this->_configuration->production_file_path() ) ) {
			$this->_configuration = new LoaderConfiguration(
				$this->_configuration->version(),
				$this->_configuration->production_domains(),
				$this->_configuration->asset_manifest_path(),
				$this->_configuration->handle_prefix(),
				$this->_configuration->script_path(),
				$this->_configuration->style_path(),
				$this->_configuration->static_path(),
				get_stylesheet_directory()
			);
		}

        $this->register_loader();
    }

    /**
     * Register an asset loader instance with the following configuration and instance name
     *  - Multiple registrations are possible, for multiple Kanopi Pack instances, specify different $_instance_names
     *  - Once registered, you cannot overwrite a named instance, it will return the current registration
     *
     * @param LoaderConfiguration   $_configuration     Configuration for the AssetLoader instance
     * @param string                $_instance_name     (Optional) Registered instance handle/name
     * @param ?string               $_production_url    (Optional) Production URL base path, defaults to theme directory
     * @param ?string               $_development_url   (Optional) Development URL base path, defaults to KANOPI_DEVELOPMENT_ASSET_URL or empty
     */
    public static function register(
        LoaderConfiguration $_configuration,
        string $_instance_name = self::DEFAULT_INSTANCE_NAME,
        ?string $_production_url = null,
        ?string $_development_url = null
    ) : WordPress {
        if ( empty( self::$_instances ) || empty( self::$_instances[ $_instance_name ] ) ) {
            self::$_instances[ $_instance_name ] = new WordPress( $_configuration, $_production_url, $_development_url );
        }

        return self::$_instances[ $_instance_name ];
    }

    /**
     * Find an instance by registered handle/name
     *
     * @param string $_instance_name    (Optional) Registered instance handle/name
     *
     * @return ?WordPress
     */
    public static function instance( string $_instance_name = self::DEFAULT_INSTANCE_NAME ): ?WordPress {
        if ( empty( self::$_instances ) || empty( self::$_instances[ $_instance_name ] ) ) {
			// phpcs:ignore -- Intentionally provide an error log entry when Kanopi Pack errors
            error_log( "Kanopi Pack: Cannot find registry " . $_instance_name
				. "please ensure it is registered first using register(...)" );
            return null;
        }

        return self::$_instances[ $_instance_name ];
    }

    /**
     * Read the current themes version
     *
     * @return string
     */
    public static function read_theme_version(): string {
        $theme = wp_get_theme();
        if ( !( $theme instanceof \WP_Theme ) ) {
            return false;
        }

        $version = $theme->get( 'Version' );

        return empty( $version ) || !is_string( $version ) ? 'DEV' : $version;
    }

    /**
     * Register the Asset Loader, dies on failure
     */
    protected function register_loader(): void {
        try {
            $this->_loader = new AssetLoader(
                $this->_production_url,
                $this->_development_url,
                $this->_configuration
            );
        }
        catch ( Exception $exception ) {
			// phpcs:ignore -- Intentionally provide an error log entry when Kanopi Pack does not load
            error_log( wp_kses_post( $exception->getMessage() ) );
            $this->_loader = null;
        }
    }

    /**
     * Current instances asset loader
     */
    public function asset_loader(): ?AssetLoader {
        return $this->_loader;
    }

    /**
     * Current environment URL path to static assets
     *
     * @param string $_file_path
     *
     * @return string
     */
    public function static_asset_url( string $_file_path ): string {
        return $this->asset_loader()->static_assets_url( $_file_path );
    }

    /**
     * Register front-end scripts
     *
     * @param callable $_script_registration    Callable function passed the current WordPress instance
     */
    public function register_frontend_scripts( callable $_script_registration ) {
        add_action( 'wp_enqueue_scripts',
            function () use ( $_script_registration ) {
                call_user_func_array( $_script_registration, [ $this ] );
            } );
    }

    /**
     * Register block editor scripts
     *
     * @param callable $_script_registration    Callable function passed the current WordPress instance
     */
    public function register_block_editor_scripts( callable $_script_registration ) {
        add_action( 'enqueue_block_editor_assets',
            function () use ( $_script_registration ) {
                call_user_func_array( $_script_registration, [ $this ] );
            }
        );
    }
}
