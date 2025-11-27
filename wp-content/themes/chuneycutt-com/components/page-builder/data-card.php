<?php
/**
 * Data card Component
 * For use with the "Component Builder" template
 */

// Content
$name           = strtr($component['acf_fc_layout'], '_', '-');
$content_width  = $component['row_width'];
$card_title     = $component['card_title'];
$logo           = $component['company_logo'];
$years          = $component['years_served'];
$role_info      = $component['role_info'];
$about          = $component['about'];
$highlights     = $component['highlights'];
$projects_title = $component['cards_title'];
$per_row        = $component['cards_per_row'];
$projects       = $component['cards'];
$sidebar        = '';

if ($card_title || $logo || $years || $about || $role_info) : $sidebar = true; endif;

// Options
include locate_template('./components/partials/component-options.php'); ?>

<section id="<?= $section_ID ?>" class="component component-<?= $name; ?> <?= implode(' ', $classes); ?>">
    <div class="container-fluid p-0 <?= $text_color_class;?> <?= $text_alignment_class; ?> w-md-<?= $content_width; ?> m-auto">
        <div class="row p-0 m-0">
            <?php if ($sidebar) : ?>
                <div class="col-12 col-sm-6 col-md-4 pl-0">
                    <div class="projects-sidebar d-flex flex-column" data-aos="fade-right" data-aos-duration="500" data-aos-delay="0">
                        <?php if ($logo) : ?>
                            <div class="logo">
                                <img class="lozad pb-3" data-src="<?= $logo['url']; ?>" alt="<?= $logo['alt']; ?>">
                            </div>
                        <?php endif; ?>
                        <?php if ($card_title || $years) : ?>
                            <div class="credentials pb-4">
                                <?php if ($card_title) : ?>
                                    <div class="title icon">
                                        <?= $card_title; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($years) : ?>
                                    <div class="years icon">
                                        <?= $years; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($about || $role_info) : ?>
                            <div class="details">
                                <?php if ($role_info) : ?>
                                    <div class="role-info pb-4">
                                        <?= $role_info; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($highlights['bullets']) : ?>
                                    <div class="component-highlights pb-4">
                                        <?php if ($highlights['title']) : ?>
                                            <h4 class="mb-0 pb-3"><?= $highlights['title']; ?></h4>
                                        <?php endif; ?>
                                        <?php if ($highlights['bullets']) :
                                            $count = 0; ?>
                                            <div class="highlights">
                                                <ul>
                                                    <?php foreach ($highlights['bullets'] as $bullet) :
                                                        $duration = '';
                                                        $count++;
                                                        $duration = ($count * 200);
                                                        ?>
                                                        <li class="pb-2">
                                                            <div class="arrow" data-aos="fade" data-aos-duration="500" data-aos-delay="<?= $duration; ?>"></div>
                                                            <?= $bullet['bullet']; ?>
                                                        </li>
                                                    <?php
                                                    endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($about) : ?>
                                <div class="about">
                                    <div><?= $about; ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($projects) : ?>
                <div class="component-cards col-12 col-sm-6 col-md-8 p-0 order-first order-sm-last" <?= $animation_attributes; ?> >
                    <?php if ($projects_title) : ?>
                        <div class="cards-title text-center text-lg-left">
                            <h1 class="mb-0 h3"><?= $projects_title; ?></h1>
                        </div>
                    <?php endif; ?>
                    <div class="card-deck <?= $per_row; ?>-per-row">
                        <?php
                        $count = 0;
                        foreach ($projects as $project) :

                            // Define card values at component level
                            $card_bg        = $project['card_background_image'];
                            $card_title     = $project['card_title'];
                            $card_subtitle  = $project['card_subtitle'];
                            $card_link      = $project['card_link'];
                            $bg_image_style = '';
                            $count++;

                            if ($card_bg) :
                                $bg_image_style = 'data-background-image="'. $card_bg['url'] . '"';
                            endif;

                            if ($animate) :
                                $animation_attributes = 'data-aos="'.$animation_style.'" data-aos-duration="'.$animation_duration.'" data-aos-delay="'.$animation_delay + ($count * 150) .'" data-aos-offset="-'. ($count * 350) .'"';
                            endif; 

                            // Begin card layout (desktop) ?>
                            <div class="card d-none d-sm-block" <?= $animation_attributes; ?>>

                                <?php if ($card_bg) : ?>
                                    <div class="card-image lozad" <?= $bg_image_style; ?>></div>
                                <?php endif;

                                // Card detail overlay (appears on hover)
                                if ($card_title || $card_subtitle) : ?>
                                    <a class="card-link d-flex w-100" href="<?= $card_link; ?>">
                                        <div class="card-content d-flex flex-column justify-content-center w-100 text-center p-3">
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
                                        <div class="card-content d-flex flex-column justify-content-center w-100 text-center px-3 pb-3 pt-2 ">
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

                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
