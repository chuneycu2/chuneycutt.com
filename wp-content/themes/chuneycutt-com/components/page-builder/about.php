<?php
/**
 * WYSIWYG Component
 * For use with the "Component Builder" template
 */

// Content
$name          = strtr($component['acf_fc_layout'], '_', '-');
$content_width = $component['row_width'];
$sidebar       = $component['sidebar'];
$page_content  = $component['about_content'];


// Options
include locate_template('./components/partials/component-options.php'); ?>

<?php
// Mobile sticky sidebar
if ($sidebar) : ?>
    <aside class="sidebar-mobile d-flex d-sm-none col-12 col-sm-4 col-md-3 mb-3" <?php if ($animate) : ?>data-aos="fade-right" data-aos-duration="500"<?php endif; ?>>
        <?php
        $image   = $sidebar['image'];
        $menu    = $sidebar['content_menu'];
        ?>
        <?php if ($image) : ?>
            <div class="sidebar-image">
                <img class="lozad w-100" data-src="<?= $image['url']; ?>" />
            </div>
        <?php endif; ?>
        <?php if ($menu || $content) : ?>
            <div class="sidebar-content">
                <?php if ($menu) : ?>
                    <select class="d-block d-sm-none p-2">
                        <?php foreach ($menu as $item) : ?>
                            <option value="<?= $item['link_hash']; ?>"><?= $item['link_text']; ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
<?php endif; ?>

<section id="<?= $section_ID ?>" class="component component-<?= $name; ?> <?= implode(' ', $classes); ?>" >
    <div class="container-fluid p-0 <?= $text_color_class;?> <?= $text_alignment_class; ?> w-md-<?= $content_width; ?> m-auto" <?= $animation_attributes; ?>>
        <article class="row p-0 m-0">

            <?php if ($sidebar) : ?>
                <aside class="sidebar d-none d-sm-flex flex-column col-12 col-sm-4 col-md-3 mb-3 p-3" <?php if ($animate) : ?>data-aos="fade-right" data-aos-duration="500"<?php endif; ?>>
                    <?php
                    $image   = $sidebar['image'];
                    $hover   = $sidebar['hover_image'];
                    $menu    = $sidebar['content_menu'];
                    $content = $sidebar['sidebar_content'];
                    ?>
                    <?php if ($image) : ?>
                        <div class="sidebar-image-container d-flex align-items-center">
                            <div class="sidebar-image d-none d-sm-flex" <?php if ($hover) : ?>style="background-image: url('<?= $hover['url'] ?>');"<?php endif; ?>>
                                <img class="lozad" data-src="<?= $image['url']; ?>" />
                            </div>
                            <div class="pl-2">
                                <p class="mb-1 p-0"><strong>Cyrus Huneycutt</strong></p>
                                <p>Durham, NC</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($menu || $content) : ?>
                        <div class="sidebar-content">
                            <?php if ($menu) : ?>
                                <nav class="sidebar-menu d-none d-sm-flex flex-column text-left">
                                    <?php foreach ($menu as $item) : ?>
                                        <a href="<?= $item['link_hash']; ?>"><?= $item['link_text']; ?></a>
                                    <?php endforeach; ?>
                                </nav>
                            <?php endif; ?>
                            <?php if ($content) : ?>
                                <div class="sidebar-links">
                                    <?= $content; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            <?php endif; ?>


            <?php if ($page_content) : ?>
                <div class="about-content col-12<?php if ($sidebar): ?> col-sm-8 col-md-9 pr-0<?php endif;?>">
                    <?php foreach ($page_content as $key => $content) : ?>
                        <section id="<?= $key; ?>">
                            <?php // TODO: flexible content inside repeater so that sections can have anchors ?>
                            <?php foreach ($content as $type) : ?>
                                <?php foreach ($type as $row) :
                                    // Free content layout
                                    if ($row['acf_fc_layout'] === 'editor_content') :
                                        echo $row['editor_content'];
                                    endif;

                                    // Icon grid layout
                                    if ($row['acf_fc_layout'] === 'icon_row') : ?>
                                    <div class="icon-grid">
                                        <?php foreach ($row['icons'] as $icon) : ?>
                                            <figure class="icon d-flex flex-column align-items-center px-2">
                                                <img class="lozad" data-src="<?= $icon['icon_graphic']['url']; ?>" alt="<?= $icon['icon_graphic']['alt']; ?>" />
                                                <p class="caption"><?= $icon['icon_text']; ?></p>
                                            </figure>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif;
                                endforeach ;
                            endforeach; ?>
                            <div class="bottom-anchor"></div>
                        </section>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

        </article>
    </div>
</section>
