<?php
/**
 * Highlights Component
 * For use with the "Component Builder" template
 */

// Content
$name          = strtr($component['acf_fc_layout'], '_', '-');
$content_width = $component['row_width'];
$highlights    = $component['highlights'];
$stats         = $component['stats'];
$per_row       = $component['stats_per_row'];

// Options
include locate_template('./components/partials/component-options.php'); ?>

<section id="<?= $section_ID ?>" class="component component-<?= $name; ?> <?= implode(' ', $classes); ?>">
    <div class="container-fluid p-0 <?= $text_color_class;?> <?= $text_alignment_class; ?> w-md-<?= $content_width; ?> m-auto" <?= $animation_attributes; ?>>
        <?php if ($highlights) : ?>
            <div class="highlights py-4 pl-3 pr-4">
                <ul>
                    <?php foreach ($highlights as $highlight) : ?>
                        <li>
                            <div class="arrow" data-aos="fade" data-aos-duration="500" data-aos-delay="<?= $duration; ?>"></div>
                            <?php echo $highlight['highlight']; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($stats) : ?>
            <div class="stats<?php if ($highlights) : ?> pt-4<?php endif; ?>">
                <div class="card-deck <?= $per_row; ?>-per-row">
                    <?php foreach ($stats as $stat) :
                        $stat         = $stat['stat'];
                        $icon         = $stat['icon'];
                        $stat_line    = $stat['stat_line'];
                        $support_text = $stat['supporting_text'];
                        ?>
                        <div class="card">
                            <?php if ($icon) : ?>
                                <div class="stat-icon">
                                    <img class="lozad" data-src="<?= $icon['url']; ?>" alt="<?= $icon['alt']; ?>">
                                </div>
                            <?php endif; ?>
                            <?php if ($stat_line) : ?>
                                <div class="stat-line">
                                    <?= $stat_line; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($support_text) : ?>
                                <div class="stat-text">
                                    <?= $support_text; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
