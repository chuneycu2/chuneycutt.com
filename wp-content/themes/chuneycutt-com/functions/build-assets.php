<?php

// Enqueue styles and scripts
function chuneycutt_com_enqueue_assets() {

    // Adjust version number with each release
    $ver = '1.0.1';

    wp_enqueue_style( 'main-styles', get_template_directory_uri() . '/assets/build/style.min.css', [], $ver);
    wp_enqueue_script('main-scripts', get_template_directory_uri() . '/assets/build/main.js', ['jquery'], $ver);

}
add_action( 'wp_enqueue_scripts', 'chuneycutt_com_enqueue_assets' );