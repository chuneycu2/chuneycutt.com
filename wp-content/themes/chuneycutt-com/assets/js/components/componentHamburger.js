import chuneycuttComponent from "./Components";

export class Component extends chuneycuttComponent {
    constructor(name, node) {
        super(name, node);
    }
    init() {
        console.log(this.name);

        // Hamburger mobile menu button functionality
        let $mainMenu   = $('header');
        let $hamburger  = $(".hamburger");
        let $mobileMenu = $('.mobile-menu');
        $hamburger.click(function() {
            $(this).toggleClass('is-active');
            if ($mobileMenu.hasClass('nav-disabled')) {
                $mobileMenu.removeClass('nav-disabled');
                $mainMenu.addClass('open');
            } else {
                resetMenu();
            }
        });

        function resetMenu() {
            $mainMenu.removeClass('open');
            $hamburger.removeClass('is-active');
            $mobileMenu.addClass('nav-disabled');
        }

        $(window).resize(function() {
            resetMenu();
        });


    }
}