import chuneycuttComponent from "./Components";
import lozad from "lozad";

export class Component extends chuneycuttComponent {
    constructor(name, node) {
        super(name, node);
    }
    init() {
        console.log(this.name);

        // OWL carousel settings
        jQuery(document).ready(function(){

            // See more carousel settings:
            // https://owlcarousel2.github.io/OwlCarousel2/docs/api-options.html
            let $carousels = jQuery(".owl-carousel");

            let carouselArgs = {
                autoplay: false,
                autoplaySpeed: 800,
                autoplayTimeout: 8000,
                autoHeight: false,
                loop: true,
                rewind: false,
                margin: 16,
                nav: false,
                dots: true,
                dotsSpeed: 400,
                navSpeed: 500,
                slideTransition: 'ease',
                lazyLoad: true,
                items: 1
            }

            $carousels.owlCarousel(carouselArgs);

        });

    }
}