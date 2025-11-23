<?php

$my_functions = [
    'functions/build-assets.php', // Styles and scripts
    'functions/init.php', // Theme setup
    'functions/nav.php', // Nav walker
];

// Load each function partial
foreach ($my_functions as $file) :
    if (!$filepath = locate_template($file)) :
        trigger_error(sprintf(__('Error locating % for inclusion', 'chuneycutt-com'), $file), E_USER_ERROR);
    endif;
    require_once $filepath;
endforeach;

function portfolio_register() {
    $labels = array(
        'name' => _x('Portfolio', 'post type general name'),
        'singular_name' => _x('Portfolio Item', 'post type singular name'),
        'add_new' => _x('Add New', 'portfolio item'),
        'add_new_item' => __('Add New Portfolio Item'),
        'edit_item' => __('Edit Portfolio Item'),
        'new_item' => __('New Portfolio Item'),
        'view_item' => __('View Portfolio Item'),
        'search_items' => __('Search Portfolio Items'),
        'not_found' =>  __('Nothing found'),
        'not_found_in_trash' => __('Nothing found in Trash'),
        'parent_item_colon' => ''
    );
    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'query_var' => true,
        'rewrite' => true,
        'capability_type' => 'post',
        'hierarchical' => false,
        'menu_position' => 8,
        'supports' => array('title','editor','thumbnail')
    );
    register_post_type( 'portfolio' , $args );
}
add_action('init', 'portfolio_register');

function portfolio_taxonomies() {
    $labels = array(
        'name'              => _x( 'Categories', 'taxonomy general name' ),
        'singular_name'     => _x( 'Category', 'taxonomy singular name' ),
        'search_items'      => __( 'Search Categories' ),
        'all_items'         => __( 'All Categories' ),
        'parent_item'       => __( 'Parent Category' ),
        'parent_item_colon' => __( 'Parent Category:' ),
        'edit_item'         => __( 'Edit Category' ),
        'update_item'       => __( 'Update Category' ),
        'add_new_item'      => __( 'Add New Category' ),
        'new_item_name'     => __( 'New Category Name' ),
        'menu_name'         => __( 'Categories' ),
    );

    $args = array(
        'hierarchical'      => true, // Set this to 'false' for non-hierarchical taxonomy (like tags)
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'portfolio_categories' ),
    );

    register_taxonomy( 'portfolio_categories', array( 'portfolio' ), $args );
}
add_action( 'init', 'portfolio_taxonomies', 0 );


