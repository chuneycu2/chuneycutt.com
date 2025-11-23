 <?php
 $footer_options = get_field('footer_options', 'option');
 $cta_bar   = $footer_options['cta_bar'];
 $socials   = $footer_options['social_icons'];
 $copyright = $footer_options['copyright'];
 ?>

    </main>
    <footer>
        <?php if ($cta_bar) :
            $cta_text = $cta_bar['cta_text'];
            $cta_link = $cta_bar['cta_link'];
            ?>
            <section class="footer-cta p-4 text-center">
                <a href="<?= $cta_link; ?>">
                    <h2 class="mb-0"><?= $cta_text; ?></h2>
                </a>
            </section>
        <?php endif; ?>
        <?php if ($socials || $copyright) : ?>
            <section class="socials">
                <?php if ($socials) : ?>
                    <div class="social-icons d-flex justify-content-center">
                        <?php foreach ($socials as $social) : ?>
                            <a href="<?= $social['link']; ?>" target="_blank">
                                <img class="lozad" data-src="<?= $social['icon']['url']; ?>" alt="<?= $social['icon']['alt']; ?>" />
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($copyright) : ?>
                    <div class="copyright text-center py-5 px-4">
                        <p><?= $copyright ?></p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </footer>
</body>
</html>