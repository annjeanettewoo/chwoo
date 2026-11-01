// Product Details — Pointer-relative image origin for the legacy product.html template.
// The live PHP product page uses product-selection.js; this script expects the legacy gallery IDs.
const imageContainer = document.getElementById("product-image-container");
const productImage = document.getElementById("main-product-image");


// Express the pointer within the image container as percentages for the CSS transform origin.
imageContainer.addEventListener("mousemove", function (event) {

    const container = imageContainer.getBoundingClientRect();

    const mouseX = event.clientX - container.left;
    const mouseY = event.clientY - container.top;

    const xPercent = (mouseX / container.width) * 100;
    const yPercent = (mouseY / container.height) * 100;

    productImage.style.transformOrigin =
        xPercent + "% " + yPercent + "%";
});


// Restore the centred origin when the pointer leaves the legacy gallery.
imageContainer.addEventListener("mouseleave", function () {

    productImage.style.transformOrigin = "center center";

});
