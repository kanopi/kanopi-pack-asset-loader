<?php
/**
 * Webpack Asset Loader Configuration
 */

namespace Kanopi\Assets\Model;

class LoaderConfiguration {
	const DEFAULT_SCRIPT_PATH = '/assets/dist/js/';
	const DEFAULT_STATIC_PATH = '/assets/dist/static/';
	const DEFAULT_STYLE_PATH = '/assets/dist/css/';
	const DEFAULT_PREFIX = 'kanopi-pack-';

	/**
	 * @var string
	 */
	protected string $_asset_manifest_path;

	/**
	 * Whether to place all Development stylesheets (that are loaded as scripts) in the document head
	 *
	 * @var bool
	 */
	protected bool $_development_styles_in_head;

	/**
	 * @var string
	 */
	protected string $_handle_prefix;

	/**
	 * @var array
	 */
	protected array $_production_domains;

	/**
	 * Root file path to the files in production, to find the asset manifest
	 *
	 * @var string
	 */
	protected string $_production_file_path;

	/**
	 * @var string
	 */
	protected string $_script_path;

	/**
	 * @var string
	 */
	protected string $_static_path;

	/**
	 * @var string
	 */
	protected string $_style_path;

	/**
	 * @var string
	 */
	protected string $_version;

	/**
	 * LoaderConfiguration constructor.
	 *
	 * @param ?string $_version                     Version to assign stylesheet URL
	 * @param ?array  $_production_domains          Guaranteed Production domains
	 * @param ?string $_asset_manifest_path         Relative path to the Asset Manifest file
	 * @param ?string $_handle_prefix               Prefix before the entry point handle
	 * @param ?string $_script_path                 Relative path to scripts
	 * @param ?string $_style_path                  Relative path to styles
	 * @param ?string $_static_path                 Relative path to static files
	 * @param ?string $_production_file_path        Production file path (for Asset Manifest and WP Blocks)
	 * @param ?bool   $_development_styles_in_head  Whether to include development styles in the head tag
	 */
	public function __construct(
		?string $_version = null,
		?array $_production_domains = null,
		?string $_asset_manifest_path = null,
		?string $_handle_prefix = null,
		?string $_script_path = null,
		?string $_style_path = null,
		?string $_static_path = null,
		?string $_production_file_path = null,
		?bool $_development_styles_in_head = false
	) {
		$this->_version                    = $this->check_string( $_version, null );
		$this->_asset_manifest_path        = $this->check_string( $_asset_manifest_path, '' );
		$this->_handle_prefix              = $this->check_string( $_handle_prefix, self::DEFAULT_PREFIX );
		$this->_script_path                = $this->check_string( $_script_path, self::DEFAULT_SCRIPT_PATH );
		$this->_static_path                = $this->check_string( $_static_path, self::DEFAULT_STATIC_PATH );
		$this->_style_path                 = $this->check_string( $_style_path, self::DEFAULT_STYLE_PATH );
		$this->_production_domains         = is_array( $_production_domains ) ? $_production_domains : [];
		$this->_production_file_path       = $this->check_string( $_production_file_path, '' );
		$this->_development_styles_in_head = $_development_styles_in_head;
	}

	/**
	 * @param ?string $_entry
	 * @param ?string $_default
	 *
	 * @return ?string
	 */
	protected function check_string( ?string $_entry, ?string $_default ): ?string {
		return empty( trim( $_entry ?? '' ) ) ? $_default : trim( $_entry );
	}

	/**
	 * Path to the asset manifest file, if one exists, empty if unused
	 *
	 * @return string
	 */
	public function asset_manifest_path(): string {
		return $this->_asset_manifest_path;
	}

	/**
	 * Whether to place all Development stylesheets (that are loaded as scripts) in the document head
	 *
	 * @return bool
	 */
	public function development_styles_in_head(): bool {
		return $this->_development_styles_in_head;
	}

	/**
	 * Prefix to namespace, placed before all enqueued asset handles
	 */
	public function handle_prefix(): string {
		return $this->_handle_prefix;
	}

	/**
	 * Set of production domains to prevent development mode
	 */
	public function production_domains(): array {
		return $this->_production_domains;
	}

	/**
	 * Url path fragment before scripts
	 */
	public function production_file_path(): string {
		return $this->_production_file_path;
	}

	/**
	 * Url path fragment before scripts
	 */
	public function script_path(): string {
		return $this->_script_path;
	}

	/**
	 * Url path fragment before static files
	 */
	public function static_path(): string {
		return $this->_static_path;
	}

	/**
	 * Url path fragment before stylesheets
	 */
	public function style_path(): string {
		return $this->_style_path;
	}

	/**
	 * Asset version passed to enqueue calls
	 */
	public function version(): string {
		return $this->_version;
	}
}
