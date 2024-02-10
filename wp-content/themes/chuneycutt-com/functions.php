<?php 
	 add_action( 'wp_enqueue_scripts', 'chuneycutt_com_enqueue_styles' );
	 function chuneycutt_com_enqueue_styles() {
 		  wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' ); 
 		  } 
 ?>