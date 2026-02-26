document.addEventListener("DOMContentLoaded", function () {
    function equalizeCardHeights() {
        let allCards = document.querySelectorAll(".carousel .card");
        let maxHeight = 0;

        // Resetowanie wysokości kart przed ponownym obliczeniem
        allCards.forEach(card => {
            card.style.height = "auto";
        });

        // Znalezienie najwyższej karty
        allCards.forEach(card => {
            let cardHeight = card.offsetHeight;
            if (cardHeight > maxHeight) {
                maxHeight = cardHeight;
            }
        });

        // Ustawienie jednolitej wysokości dla wszystkich kart
        allCards.forEach(card => {
            card.style.height = maxHeight + "px";
        });
    }

    // Wyrównanie wysokości po załadowaniu strony
    window.addEventListener("load", equalizeCardHeights);

    // Wyrównanie wysokości po zmianie rozmiaru okna
    window.addEventListener("resize", equalizeCardHeights);

    // Wyrównanie wysokości po każdej zmianie slajdu
    let carousel = document.querySelector("#carouselExampleControls");
    if (carousel) {
    carousel.addEventListener("slid.bs.carousel", equalizeCardHeights);
}
});
