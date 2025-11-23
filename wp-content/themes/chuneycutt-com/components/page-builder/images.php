<?php
/**
 * Images Component
 * For use with the "Component Builder" template
 */

// Content
$name         = strtr($component['acf_fc_layout'], '_', '-');
$per_row      = $component['per_row'];
$row_width    = $component['row_width'];
$images       = $component['images'];
$caption      = $component['caption'];

// Options
include locate_template('./components/partials/component-options.php'); ?>

<section id="<?= $section_ID ?>" class="component component-<?= $name; ?> <?= implode(' ', $classes); ?>">
    <div class="container-fluid p-0 <?= $text_color_class;?>">
        <?php if ($images) : ?>
            <div class="card-deck <?= $per_row; ?>-per-row w-<?= $row_width; ?> m-auto">
                <?php
                $count = 0;
                foreach ($images as $image) :

                    // Define card values at component level
                    $src = $image['image']['url'];
                    $alt = $image['image']['alt'];
                    $count++;

                    // Set sequential card animations
                    if ($animate == 'yes') :
                        $animation_attributes = 'data-aos="'.$animation_style.'" data-aos-delay="' . ($count * 3) . '00" data-aos-duration="'.$animation_duration.'"';
                    endif; ?>
                    <div class="card image-container" <?php if ($animate == 'yes') : echo $animation_attributes; endif; ?>>
                        <?php if ($animate == 'yes') : ?>
                            <img class="lozad" src="<?= $src ?>" alt="<?= $alt; ?>" />
                        <?php else : ?>
                            <img class="lozad" data-src="<?= $src ?>" alt="<?= $alt; ?>" />
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($caption) : ?>
                <div class="caption w-<?= $row_width; ?> m-auto text-center">
                    <p><?= $caption ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>