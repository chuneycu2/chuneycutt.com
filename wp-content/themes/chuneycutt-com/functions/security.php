<?php
// Disable unneeded WP API endpoints for extra security
add_filter( 'rest_endpoints', 'disable_default_endpoints' );
function disable_default_endpoints( $endpoints ) {
    $endpoints_to_remove = array(
        '/oembed/1.0',
        '/wp/v2/types',
        '/wp/v2/statuses',
        '/wp/v2/taxonomies',
        '/wp/v2/tags',
        '/wp/v2/users',
        '/wp/v2/comments',
        '/wp/v2/settings',
        '/wp/v2/themes',
        '/wp/v2/blocks',
        '/wp/v2/oembed',
        '/wp/v2/block-renderer',
        '/wp/v2/search',
        '/wp/v2/categories'
    );

    foreach ( $endpoints_to_remove as $rem_endpoint ) {
        // $base_endpoint = "/wp/v2/{$rem_endpoint}";
        foreach ( $endpoints as $maybe_endpoint => $object ) {
            if ( stripos( $maybe_endpoint, $rem_endpoint ) !== false ) {
                unset( $endpoints[ $maybe_endpoint ] );
            }
        }
    }
    return $endpoints;
}


