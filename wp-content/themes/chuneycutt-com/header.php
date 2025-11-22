<!DOCTYPE html>
<html lang="en">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />
        <meta name="generator" content="WordPress <?php bloginfo( 'version' ); ?>" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?php wp_title(); ?></title>

        <?php // Fonts ?>
        <link href="https://fonts.googleapis.com/css?family=Oxygen|Source+Sans+Pro&display=swap" rel="stylesheet">

        <?php // Google tag (gtag.js) ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-4CHJ8VZ28C"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-4CHJ8VZ28C');
        </script>

        <?php // Google Tag Manager ?>
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-T9ZMVSR5');</script>
        <?php // End Google Tag Manager ?>

        <?php wp_head(); ?>

        <header>
            <?php include locate_template('./components/navigation/main-navigation.php'); ?>
        </header>

    </head>
    <body>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-T9ZMVSR5"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <main>

