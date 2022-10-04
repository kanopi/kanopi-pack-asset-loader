# Kanopi Pack Asset Loader

PHP library for use in WordPress applications to facilitate development using Kanopi Pack (wrapper for Webpack) and deployment of its published assets. Loader provides a systematic method to enqueue scripts and styles produced from Webpack entry points.

## Default Configuration

All assets are registered with the following sets of configuration parameters:

- `Handle Prefixes`: kanopi-pack-
- `Script Path`: /assets/dist/js/
- `Static Path`: /assets/dist/static/
- `Style Path`: /assets/dist/css/

## Usage

Register sets of assets associated with a given Kanopi Pack configuration.

### WordPress

Coordinates enqueuing assets within WordPress with the Front-end and Block Editor for Kanopi Pack.

#### Single set of assets in the active WordPress theme

```php
use Kanopi\Assets\Registry\WordPress;

// All assets are held in the active theme under the directory /assets
// The constant `KANOPI_DEVELOPMENT_ASSET_URL` is defined before calling, otherwise only Production mode is available
$loader = WordPress( 
            new LoaderConfiguration(
				WordPress::read_theme_version(),
				[ 
                    // ... list of Domain names, no protocol or path
                 ],
				'/assets/dist/webpack-assets.json'
            )
        );

$loader->register_frontend_scripts( function ( $_registry ) {
    $loader = $_registry->asset_loader();
    $loader->register_vendor_script( 'central' );
    $loader->register_vendor_script( 'vendor' );

    $loader->register_runtime_script( 'runtime', [ 'jquery' ] );
    $loader->register_style( 'theme' );
    $loader->register_script( 'legacy' );

    $loader->enqueue_assets();

    // Required theme stylesheet
    wp_register_style(
        'site-theme',
        esc_url_raw( get_stylesheet_directory_uri() . '/style.css' ),
        [],
        $_registry::read_theme_version();
    );
    wp_enqueue_style( 'site-theme' );
});

$loader->register_block_editor_scripts( function ( $_registry ) {
    $loader = $_registry->asset_loader();
    $loader->register_vendor_script( 'central' );
    $loader->register_vendor_script( 'vendor' );

    $loader->register_runtime_script( 'runtime', [ 'jquery' ] );
    $loader->register_style( 'editor' );
    $loader->register_script( 'legacy' );

    $loader->enqueue_assets();
});
```