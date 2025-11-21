<div class="nav-container m-auto">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <div class="site-logo">
            <a href="<?php echo get_home_url(); ?>" class="logo-link">
                <?php include locate_template('./assets/img/ch-site-logo.php'); ?>
            </a>
        </div>
        <?php // desktop nav ?>
        <nav class="desktop-menu d-none d-md-flex">
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
        <div class="component component-hamburger hamburger hamburger--squeeze js-hamburger">
            <div class="hamburger-box">
                <div class="hamburger-inner"></div>
            </div>
        </div>
    </div>
</div>