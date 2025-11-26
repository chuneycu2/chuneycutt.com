<?php

// Enqueue styles and scripts
function chuneycutt_com_enqueue_assets() {

    // Adjust version number with each release
    $ver = '1.0.4';

    wp_enqueue_style( 'main-styles', get_template_directory_uri() . '/assets/build/style.min.css', [], $ver);
    wp_enqueue_script('main-scripts', get_template_directory_uri() . '/assets/build/main.js', ['jquery'], $ver);

    // Carousel assets
    wp_register_style( 'owl-carousel-stylesheet', get_template_directory_uri() . '/assets/external/owl.carousel.min.css', [], '1.0', 'all' );
    wp_register_script('owl-carousel-script', get_template_directory_uri() . '/assets/external/owl-carousel.min.js', ['jquery'], '1.0', 'all' );

}
add_action( 'wp_enqueue_scripts', 'chuneycutt_com_enqueue_assets' );