-- Fresh installation only; import before seed_products.sql.
-- Existing tables cause an error. This file does not upgrade or reset a database.
CREATE DATABASE IF NOT EXISTS chwoo_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE chwoo_db;
SET NAMES utf8mb4;

CREATE TABLE products (
    product_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_name VARCHAR(120) NOT NULL,
    category VARCHAR(30) NOT NULL,
    description TEXT,
    fit_information TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (product_id),
    CONSTRAINT chk_products_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared size codes and guidance; no unverified measurements are seeded.
CREATE TABLE size_guide (
    size_code VARCHAR(2) NOT NULL,
    guidance TEXT,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (size_code),
    CONSTRAINT chk_size_code CHECK (size_code IN ('XS', 'S', 'M', 'L'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogue images and prices belong to colours, never to size variants.
-- Empty colour_name means unspecified, not an invented colour option.
CREATE TABLE product_colours (
    colour_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    colour_name VARCHAR(50) NOT NULL DEFAULT '',
    swatch_hex CHAR(7) DEFAULT NULL,
    image_path VARCHAR(255) NOT NULL,
    price DECIMAL(8, 2) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    default_product_id INT UNSIGNED GENERATED ALWAYS AS
        (CASE WHEN is_default = 1 THEN product_id ELSE NULL END) PERSISTENT,
    PRIMARY KEY (colour_id),
    UNIQUE KEY uq_colours_name (product_id, colour_name),
    UNIQUE KEY uq_colours_image (image_path),
    UNIQUE KEY uq_colours_default (default_product_id),
    CONSTRAINT fk_colours_product FOREIGN KEY (product_id)
        REFERENCES products (product_id),
    CONSTRAINT chk_colours_price CHECK (price >= 0),
    CONSTRAINT chk_colours_default CHECK (is_default IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sole inventory authority: one row per colour and shared size code.
CREATE TABLE product_variants (
    variant_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    colour_id INT UNSIGNED NOT NULL,
    size_code VARCHAR(2) NOT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (variant_id),
    UNIQUE KEY uq_variants_selection (colour_id, size_code),
    KEY idx_variants_size (size_code),
    CONSTRAINT fk_variants_colour FOREIGN KEY (colour_id)
        REFERENCES product_colours (colour_id),
    CONSTRAINT fk_variants_size FOREIGN KEY (size_code)
        REFERENCES size_guide (size_code),
    CONSTRAINT chk_variants_stock CHECK (stock_quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PHP must supply password_hash(); never store plaintext passwords.
CREATE TABLE customers (
    customer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id),
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product-level favourites: one product per customer, independent of colour/size.
CREATE TABLE wishlist (
    wishlist_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (wishlist_id),
    UNIQUE KEY uq_wishlist_customer_product (customer_id, product_id),
    KEY idx_wishlist_product (product_id),
    CONSTRAINT fk_wishlist_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id)
        REFERENCES products (product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checkout snapshots preserve recipient/contact details when accounts change.
-- Payment is simulated; do not store card data. Currency is SGD throughout.
CREATE TABLE orders (
    order_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Unique, server-generated token makes committed checkout retries idempotent.
    checkout_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    customer_id INT UNSIGNED NOT NULL,
    recipient_name VARCHAR(120) NOT NULL,
    contact_phone VARCHAR(30) NOT NULL,
    contact_email VARCHAR(190) NOT NULL,
    delivery_address TEXT NOT NULL,
    item_subtotal DECIMAL(10, 2) NOT NULL,
    shipping_charge DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('Pending', 'Paid', 'Failed') NOT NULL DEFAULT 'Pending',
    order_status ENUM('Pending', 'Confirmed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    ordered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Accepted records local transport acceptance, never verified mailbox delivery.
    acknowledgement_sent_at DATETIME DEFAULT NULL,
    acknowledgement_status ENUM('Prepared', 'Accepted', 'Failed') NOT NULL DEFAULT 'Prepared',
    PRIMARY KEY (order_id),
    UNIQUE KEY uq_orders_checkout_token (checkout_token),
    KEY idx_orders_customer (customer_id),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id),
    CONSTRAINT chk_orders_amounts CHECK (
        item_subtotal >= 0 AND shipping_charge >= 0
        AND total_amount = item_subtotal + shipping_charge
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Name, size, colour and price are purchase snapshots, preserved by checkout PHP.
-- Product identity follows variant -> colour -> product; no redundant product FK.
CREATE TABLE order_items (
    order_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(120) NOT NULL,
    product_image_path VARCHAR(255) NOT NULL DEFAULT '',
    selected_size VARCHAR(10) NOT NULL,
    selected_colour VARCHAR(50) NOT NULL DEFAULT '',
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(8, 2) NOT NULL,
    PRIMARY KEY (order_item_id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_variant (variant_id),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id)
        REFERENCES orders (order_id),
    CONSTRAINT fk_items_variant FOREIGN KEY (variant_id)
        REFERENCES product_variants (variant_id),
    CONSTRAINT chk_items_quantity CHECK (quantity > 0),
    CONSTRAINT chk_items_price CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
