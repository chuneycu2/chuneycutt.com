<?php
/**
 * WYSIWYG Component
 * For use with the "Component Builder" template
 */

// Content
$name         = strtr($component['acf_fc_layout'], '_', '-');
$content      = $component['content'];

// Options
include locate_template('./components/partials/component-options.php'); ?>

<section id="<?= $section_ID ?>" class="component component-<?= $name; ?> <?= implode(' ', $classes); ?>">
    <div class="container-fluid p-0" <?= $animation_attributes; ?>>
        <?php echo $content; ?>
    </div>
</section>
