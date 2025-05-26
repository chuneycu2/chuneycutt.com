<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />
        <meta name="generator" content="WordPress <?php bloginfo( 'version' ); ?>" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?php wp_title(); ?></title>

        <?php // Google tag (gtag.js) ?>
        <script defer src="https://www.googletagmanager.com/gtag/js?id=G-066GQF17MZ"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-066GQF17MZ');
        </script>

        <?php wp_head(); ?>

    </head>
    <body>
        <main>

