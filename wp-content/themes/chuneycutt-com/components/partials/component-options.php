<?php
/**
 * Global component options and setup classes
 */
$options                            = $component['options'];
$section_ID                         = $options['section_id'];
$animate                            = $options['animate_component'];
$animation_type                     = $options['animation_type'];
$fade_type                          = $options['fade_type'];
$flip_type                          = $options['flip_type'];
$slide_type                         = $options['slide_type'];
$zoom_type                          = $options['zoom_type'];
$animation_duration                 = $options['animation_duration'];
$animation_delay                    = $options['animation_delay'];
$background_color                   = $options['background_color'];
$text_color                         = $options['text_color'];
$text_alignment                     = $options['text_alignment'];
$padding_top                        = $options['padding_top'];
$padding_top_small                  = $options['padding_top_small'];
$padding_top_medium                 = $options['padding_top_medium'];
$padding_top_large                  = $options['padding_top_large'];
$padding_top_xl                     = $options['padding_top_xl'];
$padding_bottom                     = $options['padding_bottom'];
$padding_bottom_small               = $options['padding_bottom_small'];
$padding_bottom_medium              = $options['padding_bottom_medium'];
$padding_bottom_large               = $options['padding_bottom_large'];
$padding_bottom_xl                  = $options['padding_bottom_xl'];

// Option classes and attributes
$classes         = array();
$padding_classes = array();

// Animation attribute
$animation_style = '';
if ($animation_type === 'fade')  :
    if ($fade_type === 'fade') : $animation_style = 'fade'; else : $animation_style = 'fade-' . $fade_type; endif;
endif;
if ($animation_type === 'flip')  :
    if ($fade_type === 'flip') : $animation_style = 'flip'; else : $animation_style = 'flip-' . $flip_type; endif;
endif;
if ($animation_type === 'slide')  :
    if ($fade_type === 'slide') : $animation_style = 'slide'; else : $animation_style = 'slide-' . $slide_type; endif;
endif;
if ($animation_type === 'zoom')  :
    if ($fade_type === 'zoom') : $animation_style = 'zoom'; else : $animation_style = 'zoom-' . $zoom_type; endif;
endif;
$animation_attributes = ($animate == 'yes') ? 'data-aos="'.$animation_style.'" data-aos-delay="'.$animation_delay.'" data-aos-duration="'.$animation_duration.'"' : '';

// Background color/image classes
$bg_color_class = 'background-color-' . $background_color;
array_push($classes, $bg_color_class);

// Text contrast class
$text_class = 'text-color-'.$text_color;

// Set padding classes, if padding is set in component (at end of array for legibility)
$pt_class         = '';
$pt_sm_class      = '';
$pt_md_class      = '';
$pt_lg_class      = '';
$pt_xl_class      = '';
$pb_class         = '';
$pb_sm_class      = '';
$pb_md_class      = '';
$pb_lg_class      = '';
$pb_xl_class      = '';
if ($padding_top || $padding_top == 0) :
    $pt_class = 'pt-' . $padding_top;
    array_push($classes, $pt_class);
    array_push($padding_classes, $pt_class);
endif;
if ($padding_top_small || $padding_top_small == 0) :
    $pt_sm_class = 'pt-sm-' . $padding_top_small;
    array_push($classes, $pt_sm_class);
    array_push($padding_classes, $pt_sm_class);
endif;
if ($padding_top_medium || $padding_top_medium == 0) :
    $pt_md_class = 'pt-md-' . $padding_top_medium;
    array_push($classes, $pt_md_class);
    array_push($padding_classes, $pt_md_class);
endif;
if ($padding_top_large || $padding_top_large == 0) :
    $pt_lg_class = 'pt-lg-' . $padding_top_large;
    array_push($classes, $pt_lg_class);
    array_push($padding_classes, $pt_lg_class);
endif;
if ($padding_top_xl || $padding_top_xl == 0) :
    $pt_xl_class = 'pt-xl-' . $padding_top_xl;
    array_push($classes, $pt_xl_class);
    array_push($padding_classes, $pt_xl_class);
endif;
if ($padding_bottom || $padding_bottom == 0) :
    $pb_class = 'pb-' . $padding_bottom;
    array_push($classes, $pb_class);
    array_push($padding_classes, $pb_class);
endif;
if ($padding_bottom_small || $padding_bottom_small == 0) :
    $pb_sm_class = 'pb-sm-' . $padding_bottom_small;
    array_push($classes, $pb_sm_class);
    array_push($padding_classes, $pb_sm_class);
endif;
if ($padding_bottom_medium || $padding_bottom_medium == 0) :
    $pb_md_class = 'pb-md-' . $padding_bottom_medium;
    array_push($classes, $pb_md_class);
    array_push($padding_classes, $pb_md_class);
endif;
if ($padding_bottom_large || $padding_bottom_large == 0) :
    $pb_lg_class = 'pb-lg-' . $padding_bottom_large;
    array_push($classes, $pb_lg_class);
    array_push($padding_classes, $pb_lg_class);
endif;
if ($padding_bottom_xl || $padding_bottom_xl == 0) :
    $pb_xl_class = 'pb-xl-' . $padding_bottom_xl;
    array_push($classes, $pb_xl_class);
    array_push($padding_classes, $pb_xl_class);
endif;
