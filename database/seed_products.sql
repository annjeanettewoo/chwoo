-- Initial sample products; stock quantities are demonstration values.
-- Import after schema.sql. Image paths are relative to the project root.
USE chwoo_db;

INSERT IGNORE INTO products
    (product_id, product_name, category, price, image_path, description, stock_quantity)
VALUES
    (1, 'Cloud Check Top', 'Tops', 42.00, 'images/newin1.jpg',
     'A playful everyday top designed with a soft, easy silhouette for effortless styling.', 20),
    (2, 'Sunday Midi Skirt', 'Bottoms', 48.00, 'images/newin2.jpg', NULL, 20),
    (3, 'Butter Day Dress', 'Dresses', 55.00, 'images/newin3.jpg', NULL, 20),
    (4, 'Everyday Mini', 'Dresses', 45.00, 'images/newin4.jpg', NULL, 20),
    (5, 'Blue Sunday Top', 'Tops', 39.00, 'images/newin1.jpg', NULL, 20),
    (6, 'Picnic Mini Skirt', 'Bottoms', 46.00, 'images/newin2.jpg', NULL, 20);
