const imageContainer = document.getElementById("product-image-container");
const productImage = document.getElementById("main-product-image");


imageContainer.addEventListener("mousemove", function (event) {

    const container = imageContainer.getBoundingClientRect();

    const mouseX = event.clientX - container.left;
    const mouseY = event.clientY - container.top;

    const xPercent = (mouseX / container.width) * 100;
    const yPercent = (mouseY / container.height) * 100;

    productImage.style.transformOrigin =
        xPercent + "% " + yPercent + "%";
});


imageContainer.addEventListener("mouseleave", function () {

    productImage.style.transformOrigin = "center center";

});