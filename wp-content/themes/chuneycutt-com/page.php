<?php /* Template Name: Component Builder */

get_header();

$fields = get_fields();

// Loop through all components used and render their partials
if ($fields['components']) :

    // General use iterator
    $iterator     = 0;
    foreach ($fields['components'] as $component) :
        $iterator++;
        include locate_template('./components/page-builder/' . str_replace('_', '-', $component['acf_fc_layout']) . '.php');
    endforeach;

endif;

get_footer();