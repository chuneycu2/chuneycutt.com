import chuneycuttComponent from "./Components";

export class Component extends chuneycuttComponent {
    constructor(name, node) {
        super(name, node);
    }
    init() {
        console.log(this.name);

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

        // Set 'scrollspy' behavior (highlight sections as they are scrolled into view)
        let headOffset = 200;
        function scrollSpy() {
            let $scrollTop = $(window).scrollTop(); // current distance from top of window
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
        }

        scrollSpy();

        // Invoke nav check and scrollspy on scroll
        $(window).scroll(function() {
            scrollSpy();
        });
    }
}