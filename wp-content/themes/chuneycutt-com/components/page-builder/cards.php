<?php
/**
 * Cards Component
 * For use with the "Component Builder" template
 */

// Content
$name         = strtr($component['acf_fc_layout'], '_', '-');
$per_row      = $component['cards_per_row'];
$cards        = $component['cards'];

// Options
include locate_template('./components/partials/component-options.php'); ?>

<section id="<?= $section_ID ?>" class="component component-<?= $name; ?> <?= implode(' ', $classes); ?>">
    <div class="container-fluid p-0">
        <?php if ($cards) : ?>
            <div class="card-deck <?= $per_row; ?>-per-row">
                <?php
                $count = 0;
                foreach ($cards as $card) :

                    // Define card values at component level
                    $card_bg        = $card['card_background_image'];
                    $card_title     = $card['card_title'];
                    $card_subtitle  = $card['card_subtitle'];
                    $card_link      = $card['card_link'];
                    $bg_image_style = '';
                    $count++;

                    if ($card_bg) :
                        $bg_image_style = 'data-background-image="'. $card_bg['url'] . '"';
                    endif;

                    // Begin card layout (desktop) ?>
                    <div class="card d-none d-sm-block" <?= $animation_attributes; ?>>

                        <?php if ($card_bg) : ?>
                            <div class="card-image lozad" <?= $bg_image_style; ?>></div>
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
                    <div class="card d-block d-sm-none" <?= $animation_attributes; ?>>
                        <a class="card-link" href="<?= $card_link; ?>">
                            <?php if ($card_bg) : ?>
                                <div class="card-image lozad" <?= $bg_image_style; ?>></div>
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