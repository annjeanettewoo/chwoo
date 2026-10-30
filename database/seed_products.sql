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
-- Task 03C: approved preview colour labels and display swatches; not supplier colour specifications.
-- Image 6 retains its DEMONSTRATION price 48.00 (copied from image 7).
-- Images 8/9 deliberately retain their different exported prices.
INSERT INTO product_colours
    (colour_id, product_id, colour_name, swatch_hex, image_path, price, is_default)
VALUES
    (1, 1, 'Midnight Navy', '#222532', 'images/newin1.jpg', 42.00, 1),
    (2, 2, 'Navy', '#2F3D5A', 'images/newin2.jpg', 48.00, 1),
    (3, 3, 'Charcoal', '#4D4B54', 'images/newin3.jpg', 45.00, 1),
    (4, 4, 'Chocolate Brown', '#604332', 'images/newin4.jpg', 45.00, 1),
    (5, 5, 'Charcoal', '#46454A', 'images/newin5.png', 56.90, 1),
    (6, 7, 'Dusty Blue', '#8DA0B2', 'images/newin6.jpg', 48.00, 0),
    (7, 7, 'Dusty Mauve', '#9A7A94', 'images/newin7.jpg', 48.00, 1),
    (8, 8, 'Midnight', '#3D3C40', 'images/newin8.jpg', 46.00, 1),
    (9, 8, 'Ivory', '#F1EFE8', 'images/newin9.jpg', 44.00, 0),
    (10, 10, 'Ivory', '#EDEBE5', 'images/newin10.jpg', 46.00, 1),
    (11, 11, 'Ivory', '#EEE9DF', 'images/newin11.jpg', 62.00, 1),
    (12, 12, 'Butter Yellow', '#F6E9AB', 'images/newin12.jpg', 58.00, 1),
    (13, 13, 'Blush Pink', '#EECFD0', 'images/newin13.jpg', 58.00, 1),
    (14, 13, 'Powder Blue', '#D1E7EA', 'images/newin14.jpg', 58.00, 0),
    (15, 15, 'Ivory', '#F2EBDD', 'images/newin15.jpg', 62.00, 1),
    (16, 16, 'Charcoal', '#555458', 'images/newin16.jpg', 54.00, 1);

-- Mock inventory only: 5 units per colour/size, 20 per colour, 320 total.
-- Explicit deterministic variant IDs: (colour_id - 1) * 4 + size sort order.
-- Do not infer variant IDs in PHP; look them up from the database.
INSERT INTO product_variants (variant_id, colour_id, size_code, stock_quantity)
SELECT (c.colour_id - 1) * 4 + s.sort_order, c.colour_id, s.size_code, 5
FROM product_colours AS c
CROSS JOIN size_guide AS s
ORDER BY c.colour_id, s.sort_order;

COMMIT;
