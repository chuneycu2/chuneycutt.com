import chuneycuttComponent from "./Components";

export class Component extends chuneycuttComponent {
    constructor(name, node) {
        super(name, node);
    }
    init() {
        console.log(this.name);

        // Breakpoint references
        let sm = 576,
            md = 768,
            lg = 992,
            xl = 1200;

        // Viewport width helper function
        function viewport() {
            let e = window,
                a = 'inner';
            if (!('innerWidth' in window)) {
                a = 'client';
                e = document.documentElement || document.body;
            }
            return { width: e[ a + 'Width' ], height : e[ a + 'Height'] };
        }

        let vpWidth = viewport().width;
        console.log(vpWidth);

        // Set smooth scroll behavior
        let $menuItems = $('.component.component-about .sidebar-menu a')
        $menuItems.each(function() {
            $(this).click(function() {
                let id = $(this).attr('href');
                let section = $(id);
                $('html, body').stop().animate({
                    scrollTop: section.offset().top - 160
                });
            })
        });

        // Mobile select behavior
        let $selectMenu = $('.sidebar-mobile select');
        $selectMenu.each(function(i) {
            $(this).on('change', (event) => {
                const selectedId = event.target.value;
                let section = $(selectedId);
                $('html, body').stop().animate({
                    scrollTop: section.offset().top - 215
                });
            });
        });

        // Set 'scrollspy' behavior (highlight sections as they are scrolled into view)
        let headOffset = 200;
        function scrollSpy() {
            let $scrollTop = $(window).scrollTop(); // current distance from top of window

            // Change desktop nav items on scroll
            $menuItems.each(function() {
                let $secLink   = $(this);
                let $secId     = $secLink.attr('href');
                let $section   = $($secId);
                let $secHeight = $section.height();
                //let $bottom    = $section.siblings('.bottom-anchor');
                //let $bottomOffset = $bottom.offset().top;
                let $secOffset = $section.offset().top;

                if ($secOffset - $scrollTop < headOffset && $secOffset - $scrollTop > headOffset - $secHeight) {
                    if (!$secLink.hasClass('active')) {
                        $secLink.addClass('active');
                    }
                } else {
                    if ($secLink.hasClass('active')) {
                        $secLink.removeClass('active');
                    }
                }
            });

            // change mobile options on scroll
            let $tabs      = $('.sidebar-mobile select');
            let $options   = $tabs.find('option');
            $options.each(function() {
                let $secId     = $(this).attr('value');
                let $section   = $($secId);
                let $secHeight = $section.height();
                let $secOffset = $section.offset().top;
                if (($secOffset - $scrollTop < headOffset + 25) && ($secOffset - $scrollTop > headOffset - $secHeight)) {
                    $tabs.val($secId);
                }
            })
        }

        scrollSpy();

        // Invoke nav check and scrollspy on scroll
        $(window).scroll(function() {
            scrollSpy();
        });
    }
}