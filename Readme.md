# Kanopi Pack Asset Loader

PHP library for use in PHP and WordPress applications to facilitate development using Kanopi Pack (wrapper for Webpack) and deployment of its published assets. Loader provides a systematic method to enqueue scripts and styles produced from Webpack entry points.


## Documentation Reference

* [Classes](#classes)
* [WordPress Configuration Note](#wordpress-configuration-note)
* [WordPress Examples](#wordpress-examples)


## Classes

### Loader Configuration Class

All assets are registered with the following sets of configuration parameters:

| Parameter | Default |
| --- | --- |
| `Asset Manifest Path` | None |
| `Handle Prefixes`| kanopi-pack- |
| `Production Domains` | None |
| `Script Path`| /assets/dist/js/ |
| `Static Path`| /assets/dist/static/ |
| `Style Path`| /assets/dist/css/ |
| `Version` | None |


| Class Method | Description |
| --- | --- |
| `__construct( ?string $_version = null, ?array $_production_domains = null, ?string $_asset_manifest_path = null, ?string $_handle_prefix = null, ?string $_script_path = null, ?string $_style_path = null, ?string $_static_path = null )` | Override any default values associated with the configuration |


### Asset Loader API Class

The underlying class, which coordinates the load of assets from a Kanopi Pack configuration, is located at `Kanopi\Assets\AssetLoader`. 

Webpack generates two special script types which are loaded at specific times, though are optional based on other assets present in the app/site: 

1. **Runtime** - Present to coordinate modules shared between multiple packages. For instance, if `app1.js` and `app2.js` share Vue3, a runtime file (default handle of `runtime`) is generated and must be loaded before either.
2. **Vendor** - Contains any third-party imported modules, either listed under the `package.json` key of `require`, or referenced in an entry point with `@import <named_node_module>`. For instance, if `app1.js` and `app2.js` share Vue3, a vendor file (default handle of `vendor`) is generated and must be loaded before any Runtime and App entry points. **NOTE** - Webpack may also generate another module coordinating file, default handle of `central`, which is always present on the Dev Server. 

| Class Method | Description |
| --- | --- |
| `__construct( string $_base_url, ?string $_development_url, Model\LoaderConfiguration $_configuration )` | Coordinate the asset loader, based on a set of production and development URLs, and path configurations |
| `enqueue_assets()` | Helper function generates a series of WordPress `wp_enqueue_*` calls |
| `in_development_mode(): bool` | Flag to tell whether the assets are currently loaded in Production or Development mode |
| `register_applications( string $_handle, array $_dependencies = [] )` | Register an application (contains both script and styles), via entry point handle and any preceding script handles (not styles) |
| `register_runtime_script( string $_handle, array $_dependencies = [] )` | Register the Webpack runtime script, via entry point handle and any preceding scripts |
| `register_script( string $_handle, array $_dependencies = [] )` | Register a script, via entry point handle and any preceding scripts |
| `register_style( string $_handle, array $_dependencies = [] )` | Register a style, via entry point handle and any preceding styles |
| `register_vendor_script( string $_handle, array $_dependencies = [] )` | Register a Webpack generated vendor script, via entry point handle and any preceding scripts |


### WordPress Enqueue Registry

Coordinates enqueuing assets within WordPress with the Front-end and Block Editor for Kanopi Pack.

#### API

The main WordPress registration class is located at `Kanopi\Assets\Registry\WordPress`. 

| Class Method | Description |
| --- | --- |
| `__construct( LoaderConfiguration $_configuration )` | Configures the asset manifest and versioning features |
| `register_block_editor_scripts( callback $_closure )` | Wrapper to enqueue scripts and styles for the Block Editor |
| `register_frontend_scripts( callback $_closure )` | Wrapper to enqueue scripts and styles on the site front-end |


## WordPress Configuration Note

In default implementations, the package assumes the constant `KANOPI_DEVELOPMENT_ASSET_URL` is defined before calling the package, otherwise only Production mode is available


## WordPress Examples

#### Single set of assets in the active WordPress theme

Consider this sample set of Kanopi Pack entry points:

```js
module.exports = {
    //... other configuration ...
    "filePatterns": {
        "cssOutputPath": "css/[name].css",
        "entryPoints": {
            "theme": "./assets/src/scss/theme/index.scss",
            "theme-app": "./assets/src/js/theme/index.js"
        },
        "jsOutputPath": "js/[name].js"
    },
    //... other configuration ...
}
```

An example loader sequence is as follows, though it's recommended this be placed in a module or some other structure.

```php
use Kanopi\Assets\Registry\WordPress;

$loader = WordPress( 
            new LoaderConfiguration(
				WordPress::read_theme_version(),
				[ 
                    // ... list of Domain names, no protocol or path
                    'domain-name.com',
                    'staging.domain-name.com'
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
    $loader->register_script( 'theme-app' );

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
```

### Adding a New Script or Style

When a new script of style is added, add the handle to the appropriate registration function. 

Consider adding a new script and style for a Song post type to the site in the [previous example](#single-set-of-assets-in-the-active-wordpress-theme).

The new configuration becomes:

```js
module.exports = {
    //... other configuration ...
    "filePatterns": {
        "cssOutputPath": "css/[name].css",
        "entryPoints": {
            "song": "./assets/src/scss/song/index.scss",
            "song-app": "./assets/src/js/song/index.js"
            "theme": "./assets/src/scss/theme/index.scss",
            "theme-app": "./assets/src/js/theme/index.js"
        },
        "jsOutputPath": "js/[name].js"
    },
    //... other configuration ...
}
```

```php
use Kanopi\Assets\Registry\WordPress;

$loader = WordPress( 
            new LoaderConfiguration(
				WordPress::read_theme_version(),
				[ 
                    // ... list of Domain names, no protocol or path
                    'domain-name.com',
                    'staging.domain-name.com'
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
    $loader->register_style( 'song' );
    $loader->register_script( 'theme-app' );
    $loader->register_script( 'song-app' );

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
```

### Adding Gutenberg Blocks to the Site

When a new script of style is added, add the handle to the appropriate registration function. 

Consider adding new Song Listing and Testimonial blocks to the site in the [previous example](#single-set-of-assets-in-the-active-wordpress-theme). 

It is strongly recommended to add all of the blocks to the site through a common script with an auto-loader function for HMR. Also, styles written modularly for each block can be included in their front-end stylesheets and separately included in a Block Editor specific sheet, `blocks-theme` entry point.

 Both blocks, in this example, are registered both in PHP and in the `block-editor` entry point. Both blocks contain front-end script functionality, the song listing is a widget on all pages so is included here. The testimonial block, however, is situational, so we will let the `block.json` on the PHP side register its scripts and styles.  

The new configuration becomes:

```js
module.exports = {
    //... other configuration ...
    "filePatterns": {
        "cssOutputPath": "css/[name].css",
        "entryPoints": {
            "block-editor": "./assets/src/js/block-editor/index.ts",
            "block-theme": "./assets/src/scss/block-editor/index.scss"
            "song-listing": "./assets/src/scss/song-listing/index.scss",
            "song-listing-app": "./assets/src/js/song-listing/index.js"
            "testimonial": "./assets/src/scss/testimonial/index.scss",
            "testimonial-app": "./assets/src/js/testimonial/index.js"
            "theme": "./assets/src/scss/theme/index.scss",
            "theme-app": "./assets/src/js/theme/index.js"
        },
        "jsOutputPath": "js/[name].js"
    },
    //... other configuration ...
}
```

```php
use Kanopi\Assets\Registry\WordPress;

$loader = WordPress( 
            new LoaderConfiguration(
				WordPress::read_theme_version(),
				[ 
                    // ... list of Domain names, no protocol or path
                    'domain-name.com',
                    'staging.domain-name.com'
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
    $loader->register_style( 'song-listing' );
    $loader->register_script( 'theme-app' );
    $loader->register_script( 'song-listing-app' );
    // Note, the Testimonial script and style are not included, the block.json for the block covers conditionally including those assets. Add a dependency handle of kanopi-pack-runtime (or adjust kanopi-pack if you changed the handle prefix) to ensure all of its modules are available.

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
    $loader->register_style( 'block-theme' );
    $loader->register_script( 'block-editor' );

    $loader->enqueue_assets();
});
```