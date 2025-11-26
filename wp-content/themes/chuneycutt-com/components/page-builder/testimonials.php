<?php
/**
 * Testimonials Component
 * For use with the "Component Builder" template
 */

// Enqueue OWL carousel style and scripts
if (!wp_style_is('owl-carousel-stylesheet')) :
    wp_enqueue_style('owl-carousel-stylesheet');
endif;
if (!wp_script_is('owl-carousel-script')) :
    wp_enqueue_script('owl-carousel-script');
endif;

// Content
$name          = strtr($component['acf_fc_layout'], '_', '-');
$content_width = $component['row_width'];
$quotes        = $component['testimonial_cells'];

// Options
include locate_template('./components/partials/component-options.php'); ?>

<section id="<?= $section_ID ?>" class="component component-<?= $name; ?> <?= implode(' ', $classes); ?>">
    <div class="container-fluid p-0 <?= $text_color_class;?> <?= $text_alignment_class; ?> m-auto" <?= $animation_attributes; ?>>
        <?php if ($quotes) : ?>
            <div class="carousel owl-carousel row px-0 mx-0">
                <?php foreach ($quotes as $quote) :
                    $content      = $quote['text'];
                    $attribution  = $quote['attribution'];
                    $position     = $quote['position'];
                    $image        = $quote['image'];
                    ?>
                    <div class="quote-cell px-0 mx-0">
                        <?php if ($content) : ?>
                            <blockquote>
                                <?= $content; ?>
                            </blockquote>
                        <?php endif ; ?>
                        <?php if ($attribution) : ?>
                            <div class="attribution d-flex align-items-center pt-3 pt-sm-2">
                                <?php if ($image) : ?>
                                    <div class="attribution-image pr-3">
                                        <img class="lozad" data-src="<?= $image['url']; ?>" alt="<?= $image['alt']; ?>" />
                                    </div>
                                <?php endif; ?>
                                <div class="attribution-details">
                                    <p class="attribution-name">
                                        <?php echo $attribution; ?>
                                    </p>
                                    <p class="attribution-title">
                                        <?php echo $position; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif ; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
