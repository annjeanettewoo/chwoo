-- Task 02B amended: 13 garments, 16 colours, 64 colour/size variants.
-- Based on the existing Task 02 seed; source export dated 30 October 2026 09:00.
-- Fresh installation only. No existing database is migrated or reset.
USE chwoo_db;
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO products
    (product_id, product_name, category, description, is_active)
VALUES
    (1, 'Cloud Check Top', 'Tops', 'A playful everyday top designed with a soft, easy silhouette for effortless styling.', 1),
    (2, 'Sunday Midi Skirt', 'Bottoms', NULL, 1),
    (3, 'Kiara Skirt', 'Bottoms', NULL, 1),
    (4, 'Everyday Mini', 'Tops', NULL, 1),
    (5, 'Sallie Skirt', 'Bottoms', NULL, 1),
    (7, 'Pleated Mini Skirt', 'Bottoms', 'A soft mauve pleated mini skirt for everyday looks.', 1),
    (8, 'Ruffle Top', 'Tops', 'A dark statement top with ruffled details. An ivory top with delicate floral and ruffle details.', 1),
    (10, 'Ivory Lace Corset Top', 'Tops', 'An elegant ivory lace-inspired fitted top.', 1),
    (11, 'Ivory Lace Mini Dress', 'Dresses', 'A light ivory mini dress with lace-inspired details.', 1),
    (12, 'Butter Yellow Sundress', 'Dresses', 'A breezy sundress in a soft butter-yellow shade.', 1),
    (13, 'Sundress', 'Dresses', 'A light pink sundress with a relaxed silhouette. A flowing powder-blue sundress for effortless dressing.', 1),
    (15, 'Ivory Ruffle Sundress', 'Dresses', 'A delicate ivory sundress with ruffle details.', 1),
    (16, 'Charcoal Wide-Leg Trousers', 'Bottoms', 'Relaxed charcoal wide-leg trousers.', 1);

-- Four shared labels only; no measured fit claims.
INSERT INTO size_guide (size_code, guidance, sort_order)
VALUES
    ('XS', NULL, 1),
    ('S', NULL, 2),
    ('M', NULL, 3),
    ('L', NULL, 4);

-- Colour IDs retain image numbers; parent IDs retain representative source IDs.
-- Blank names are explicitly unspecified; do not render them as named swatches.
-- Image 6: unverified colour and DEMONSTRATION price 48.00 (copied from image 7).
-- Images 8/9 deliberately retain their different exported prices.
INSERT INTO product_colours
    (colour_id, product_id, colour_name, image_path, price, is_default)
VALUES
    (1, 1, '', 'images/newin1.jpg', 42.00, 1),
    (2, 2, '', 'images/newin2.jpg', 48.00, 1),
    (3, 3, '', 'images/newin3.jpg', 45.00, 1),
    (4, 4, '', 'images/newin4.jpg', 45.00, 1),
    (5, 5, '', 'images/newin5.png', 56.90, 1),
    (6, 7, '', 'images/newin6.jpg', 48.00, 0),
    (7, 7, 'Mauve', 'images/newin7.jpg', 48.00, 1),
    (8, 8, 'Midnight', 'images/newin8.jpg', 46.00, 1),
    (9, 8, 'Ivory', 'images/newin9.jpg', 44.00, 0),
    (10, 10, 'Ivory', 'images/newin10.jpg', 46.00, 1),
    (11, 11, 'Ivory', 'images/newin11.jpg', 62.00, 1),
    (12, 12, 'Butter Yellow', 'images/newin12.jpg', 58.00, 1),
    (13, 13, 'Blush Pink', 'images/newin13.jpg', 58.00, 1),
    (14, 13, 'Powder Blue', 'images/newin14.jpg', 58.00, 0),
    (15, 15, 'Ivory', 'images/newin15.jpg', 62.00, 1),
    (16, 16, 'Charcoal', 'images/newin16.jpg', 54.00, 1);

-- Mock inventory only: 5 units per colour/size, 20 per colour, 320 total.
-- Explicit deterministic variant IDs: (colour_id - 1) * 4 + size sort order.
-- Do not infer variant IDs in PHP; look them up from the database.
INSERT INTO product_variants (variant_id, colour_id, size_code, stock_quantity)
SELECT (c.colour_id - 1) * 4 + s.sort_order, c.colour_id, s.size_code, 5
FROM product_colours AS c
CROSS JOIN size_guide AS s
ORDER BY c.colour_id, s.sort_order;

COMMIT;
