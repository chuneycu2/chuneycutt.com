<?php
$header_options = get_field('header_options', 'option');
$title          = $header_options['site_name'];
$tagline        = $header_options['site_tagline'];
?>

<div class="component component-header m-auto">
    <div class="container-fluid d-flex align-items-center justify-content-between px-0 py-4">
        <div class="site-logo" data-aos="fade" data-aos-duration="500">
            <a href="<?php echo get_home_url(); ?>" class="logo-link d-flex align-items-center">
                <?php include locate_template('./assets/img/ch-site-logo.php'); ?>
                <?php if ($tagline) : ?>
                    <div class="site-title">
                        <?php if ($title) : ?>
                            <div class="title m-0 p-0"><?= $title ?></div>
                        <?php endif; ?>
                        <?php if ($tagline) : ?>
                            <div class="tagline"><?= $tagline ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </a>
        </div>
        <?php // desktop nav ?>
        <nav class="desktop-menu d-none d-md-flex" data-aos="fade" data-aos-duration="500">
            <?php if (has_nav_menu('main-nav')) :
                wp_nav_menu( array(
                    'theme_location' => 'main-nav',
                    'menu'           => 'main-nav',
                    'depth'          => 1,
                    'menu_class'     => 'd-flex',
                    'fallback_cb'    => 'WP_Bootstrap_Navwalker::fallback',
                    //'walker'         => new WP_Bootstrap_Navwalker(),
                ));
            endif; ?>
        </nav>
        <?php // mobile nav ?>
        <nav class="mobile-menu nav-disabled">
            <?php if (has_nav_menu('main-nav')) :
                wp_nav_menu( array(
                    'theme_location' => 'main-nav',
                    'menu'           => 'main-nav',
                    'depth'          => 1,
                    'menu_class'     => 'd-flex flex-column',
                    'fallback_cb'    => 'WP_Bootstrap_Navwalker::fallback',
                    //'walker'         => new WP_Bootstrap_Navwalker(),
                ));
            endif; ?>
        </nav>
        <div class="hamburger hamburger--squeeze js-hamburger">
            <div class="hamburger-box">
                <div class="hamburger-inner"></div>
            </div>
        </div>
    </div>
</div>