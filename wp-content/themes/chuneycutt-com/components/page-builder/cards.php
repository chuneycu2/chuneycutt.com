<?php
/**
 * Cards Component
 * For use with the "Component Builder" template
 */

// Content
$name          = strtr($component['acf_fc_layout'], '_', '-');
$content_width = $component['content_width'];
$per_row       = $component['cards_per_row'];
$image_type    = $component['image_type'];
$cards         = $component['cards'];

// Options
include locate_template('./components/partials/component-options.php'); ?>

<section id="<?= $section_ID ?>" class="component component-<?= $name; ?> <?= implode(' ', $classes); ?>">
    <div class="container-fluid p-0">
        <?php if ($cards) : ?>
            <div class="card-deck <?= $per_row; ?>-per-row w-md-<?= $content_width; ?> m-auto<?php if ($image_type === 'graphic'): ?> graphic<?php endif; ?>">
                <?php
                $count = 0;
                foreach ($cards as $card) :

                    // Define card values at component level
                    $card_bg        = $card['card_background_image'];
                    $card_image     = $card['card_image'];
                    $card_title     = $card['card_title'];
                    $card_subtitle  = $card['card_subtitle'];
                    $card_link      = $card['card_link'];
                    $bg_image_style = '';
                    $count++;

                    if ($card_bg) :
                        $bg_image_style = 'data-background-image="'. $card_bg['url'] . '"';
                    endif;

                    if ($animate) :
                        $animation_attributes = 'data-aos="'.$animation_style.'" data-aos-duration="'.$animation_duration.'" data-aos-delay="'.$animation_delay + ($count * 150) .'" data-aos-offset="-'. ($count * 350) .'"';
                    endif;

                    // Begin card layout (desktop) ?>
                    <div class="card d-none d-sm-block<?php if ($image_type === 'graphic'): ?> graphic<?php endif; ?>" <?= $animation_attributes; ?>>

                        <?php if ($card_bg) : ?>
                            <div class="card-image lozad" <?= $bg_image_style; ?>></div>
                        <?php endif;

                        if ($card_image) : ?>
                            <div class="card-graphic">
                                <img class="lozad" data-src="<?= $card_image['url']; ?>" alt="<?= $card_image['alt']; ?>" />
                            </div>
                        <?php endif;

                        // Card detail overlay (appears on hover)
                        if ($card_title || $card_subtitle) : ?>
                            <a class="card-link d-flex w-100" href="<?= $card_link; ?>">
                                <div class="card-content d-flex flex-column justify-content-center w-100 <?= $text_alignment_class; ?>">
                                    <?php if ($card_title) : ?>
                                        <div class="card-title"><?= $card_title; ?></div>
                                    <?php endif; ?>
                                    <?php if ($card_subtitle) : ?>
                                        <div class="card-subtitle"><?= $card_subtitle; ?></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php // Begin card layout (mobile) ?>
                    <div class="card d-flex flex-column d-sm-none<?php if ($image_type === 'graphic'): ?> graphic<?php endif; ?>" <?= $animation_attributes; ?>>
                        <a class="card-link" href="<?= $card_link; ?>">
                            <?php if ($card_bg) : ?>
                                <div class="card-image lozad" <?= $bg_image_style; ?>></div>
                            <?php endif; ?>

                            <?php if ($card_image) : ?>
                                <div class="card-graphic">
                                    <img class="lozad" data-src="<?= $card_image['url']; ?>" alt="<?= $card_image['alt']; ?>" />
                                </div>
                            <?php endif;

                            // Card detail overlay (appears beneath image on mobile)
                            if ($card_title || $card_subtitle) : ?>
                                <div class="card-content d-flex flex-column justify-content-center w-100 <?= $text_alignment_class; ?>">
                                    <?php if ($card_title) : ?>
                                        <div class="card-title"><?= $card_title; ?></div>
                                    <?php endif; ?>
                                    <?php if ($card_subtitle) : ?>
                                        <div class="card-subtitle"><?= $card_subtitle; ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>