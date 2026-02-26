(function ($) {
    "use strict";

    $(".scroll-top").hide();
    $(window).on("scroll", function () {
        if ($(this).scrollTop() > 300) {
            $(".scroll-top").fadeIn();
        } else {
            $(".scroll-top").fadeOut();
        }
    });
    $(".scroll-top").on("click", function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    $(document).ready(function () {
        $(".select2").select2({
            theme: "bootstrap",
        });
    });

    new WOW().init();

    $(".video-button").magnificPopup({
        type: "iframe",
        gallery: {
            enabled: true,
        },
    });
    $(".magnific").magnificPopup({
        type: "image",
        gallery: {
            enabled: true,
        },
    });

    $(".slide-carousel").owlCarousel({
        loop: true,
        autoplay: true,
        autoplayHoverPause: true,
        margin: 0,
        mouseDrag: false,
        animateIn: "fadeIn",
        animateOut: "fadeOut",
        nav: true,
        navText: [
            "<i class='fas fa-long-arrow-alt-left'></i>",
            "<i class='fas fa-long-arrow-alt-right'></i>",
        ],
        responsive: {
            0: {
                items: 1,
            },
            600: {
                items: 1,
            },
            1000: {
                items: 1,
            },
        },
    });

    $(".testimonial-carousel").owlCarousel({
        loop: true,
        autoplay: true,
        autoplayHoverPause: true,
        autoplaySpeed: 1500,
        smartSpeed: 1500,
        margin: 30,
        nav: false,
        animateIn: "fadeIn",
        animateOut: "fadeOut",
        navText: [
            "<i class='fa fa-caret-left'></i>",
            "<i class='fa fa-caret-right'></i>",
        ],
        responsive: {
            0: {
                items: 1,
                dots: false,
                nav: true,
            },
            768: {
                items: 1,
                dots: true,
            },
            992: {
                items: 2,
                dots: true,
            },
        },
    });

    $(".room-detail-carousel").owlCarousel({
        loop: true,
        autoplay: false,
        autoplayHoverPause: true,
        margin: 0,
        mouseDrag: false,
        animateIn: "fadeIn",
        animateOut: "fadeOut",
        nav: true,
        navText: [
            "<i class='fa fa-angle-left'></i>",
            "<i class='fa fa-angle-right'></i>",
        ],
        responsive: {
            0: {
                items: 1,
            },
            600: {
                items: 1,
            },
            1000: {
                items: 1,
            },
        },
    });

    // If our custom mobile bar exists, don't initialize meanmenu; we'll build a tiny mobile menu
    if (jQuery('.mobile-bar').length) {
        function buildSimpleMobileMenu(){
            var $container = jQuery('#mobileMenuContainer');
            if(!$container.length) return;
            var $desktop = jQuery('.navbar-nav').first();
            if(!$desktop.length) return;
            var $newUl = jQuery('<ul class="navbar-nav"></ul>');
            $desktop.children('li').each(function(){
                var $li = jQuery(this);
                var $a = $li.find('> a').first();
                if(!$a.length) return;
                var href = $a.attr('href')||'#';
                var text = $a.text().trim();
                if(!text) return;
                var $newLi = jQuery('<li></li>');
                var $newA = jQuery('<a></a>').attr('href',href).text(text);
                $newLi.append($newA);
                // intentionally do NOT clone submenu items here - mobile menu should show only top-level links
                $newUl.append($newLi);
            });

                // also include offer-link (some templates wrap the Offer item outside the main UL)
                // try to add Offer link (robustly)
                var $offerA = jQuery('.offer-link').find('> li > a, > a').first();
                var offerHref, offerText;
                if($offerA.length){
                    offerHref = $offerA.attr('href')||'#';
                    offerText = $offerA.text().trim();
                } else {
                    // try to find by text anywhere in the nav or document
                    var $alt = jQuery('a').filter(function(){ return /Oferta wycieczek szkolnych/i.test(jQuery(this).text()); }).first();
                    if($alt.length){
                        offerHref = $alt.attr('href')||'#';
                        offerText = $alt.text().trim();
                    } else {
                        // fallback: static known route path (safe default)
                        offerHref = '/directory-packages';
                        offerText = 'Oferta wycieczek szkolnych 2025';
                    }
                }
                // avoid duplicate by matching text or href
                var exists = $newUl.find('a').filter(function(){ var t=jQuery(this).text().trim(); var h=jQuery(this).attr('href'); return t===offerText || h===offerHref; }).length;
                if(!exists){
                    var $offerLi = jQuery('<li></li>');
                    $offerLi.append(jQuery('<a></a>').attr('href', offerHref).text(offerText));
                    // preferred placement: after 'Start'
                    var $startLink = $newUl.find('a').filter(function(){ return jQuery(this).text().trim().toLowerCase() === 'start'; }).first();
                    if($startLink.length){
                        $startLink.parent().after($offerLi);
                    } else {
                        // fallback: insert before 'Aktualności' if present, else prepend
                        var $akt = $newUl.find('a').filter(function(){ return /aktualno/i.test(jQuery(this).text()); }).first();
                        if($akt.length){
                            $akt.parent().before($offerLi);
                        } else {
                            $newUl.prepend($offerLi);
                        }
                    }
                }
            $container.empty().append($newUl);
        }
        buildSimpleMobileMenu();
        jQuery('.mobile-toggle').on('click', function(){
            var $btn = jQuery(this);
            var $container = jQuery('#mobileMenuContainer');
            if($container.is(':visible')){
                $container.slideUp(180);
                $btn.attr('aria-expanded','false');
            } else {
                buildSimpleMobileMenu();
                $container.slideDown(180);
                $btn.attr('aria-expanded','true');
            }
        });
        jQuery(window).on('resize', function(){ if(window.innerWidth>991){ jQuery('#mobileMenuContainer').hide(); jQuery('.mobile-toggle').attr('aria-expanded','false'); } });
    } else {
        // fallback: initialize meanmenu normally
        jQuery(".mean-menu").meanmenu({
            meanScreenWidth: "991",
            meanMenuContainer: 'body',
            meanRevealPosition: 'right'
        });
    }

    $(".datepicker").datepicker({
        format: "yyyy-mm-dd",
        todayHighlight: true
    });

    $('.counter').counterUp();

})(jQuery);
