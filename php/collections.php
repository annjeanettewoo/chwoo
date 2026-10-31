<?php
// CHWOO — Collections page.
// Uses the same global CSS, header and footer classes as the existing website.
// Image placeholders disappear automatically when you add the named image files.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bag.php';
$bagCount = $chwooBagCount;

// Content links are relative to this PHP page; navigation lives in header.php.
$shopUrl = 'shop.php';

// Keep the image folder inside the existing /images directory.
// PHP checks for the optional pictures so missing images display labelled placeholders.
function collectionImage(string $filename, string $alt, string $placeholderText): void
{
    $path = 'images/collections/' . $filename;
    $url = chwooImageUrl($path, chwooAuthRootUrl());
    if ($url !== '') {
        echo '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="'
            . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
    } else {
        echo '<div class="collection-image-placeholder" role="img" aria-label="'
            . htmlspecialchars($placeholderText, ENT_QUOTES, 'UTF-8') . '">'
            . '<span>IMAGE PLACEHOLDER</span><small>'
            . htmlspecialchars($placeholderText, ENT_QUOTES, 'UTF-8')
            . '</small></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections | CHWOO Fashion Studio</title>

    <!-- Use the same original CHWOO stylesheets. Do not replace them. -->
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/collections.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="<?= chwooEscape($chwooRootUrl) ?>css/bag.css">
</head>
<body class="collections-page">

    <!-- Original website header and announcement bar -->
    <?php include __DIR__ . '/header.php'; ?>

    <main>
        <!-- Same heading hierarchy as the Shop page -->
        <section class="collection-intro" aria-labelledby="collections-title">
            <p class="collection-eyebrow">CHWOO EDITORIAL</p>
            <h1 id="collections-title">Collections</h1>
            <p class="collection-description">A little story for every season, starting with Midsommar.</p>
        </section>

        <!-- YOUR EXISTING CANVA BANNER: no text, logo or colors have been changed -->
        <section class="collection-banner" aria-label="Midsommar Summer Solstice collection">
            <img src="../images/collections/midsommarhero.png"
                 alt="Midsommar Summer Solstice campaign banner featuring a blue sky, a lake and playful stars."
                 width="1672" height="941">
        </section>

        <!-- Editorial section: only the photographs are placeholders -->
        <section class="collection-story" aria-labelledby="midsommar-title">
            <div class="collection-story-text">
                <p class="collection-eyebrow">SUMMER SOLSTICE / COLLECTION 01</p>
                <h2 id="midsommar-title">Midsommar</h2>
                <p>Long days, blue skies and nowhere else to be.</p>
                <p>Our Midsommar collection is inspired by slow afternoons, warm sunshine and the little moments that make summer feel special.</p>
                <a class="collection-text-link" href="<?= htmlspecialchars($shopUrl) ?>">SHOP THE COLLECTION →</a>
            </div>
            <figure class="collection-photo collection-photo-main">
                <?php collectionImage('midsommar-editorial-01.jpg', 'Midsommar campaign lifestyle photograph', '01 · Lifestyle / landscape photo · 4:5'); ?>
            </figure>
            <figure class="collection-photo collection-photo-detail">
                <?php collectionImage('midsommar-editorial-02.jpg', 'Summer flowers or campaign detail', '02 · Flowers / details photo · 1:1'); ?>
            </figure>
        </section>

        <!-- A product / flat-lay photograph is enough: no model shoot required -->
        <section class="collection-feature" aria-labelledby="wear-title">
            <figure class="collection-photo collection-photo-outfit">
                <?php collectionImage('midsommar-outfit.jpg', 'Midsommar outfit or clothing flat lay', '03 · Clothing / flat lay photo · 3:4'); ?>
            </figure>
            <div class="collection-feature-text">
                <p class="collection-eyebrow">THE MIDSOMMAR EDIT</p>
                <h2 id="wear-title">Made for sunny days.</h2>
                <p>Easy pieces for everyday plans, impromptu outings, and everything in between.</p>
                <a class="collection-button" href="<?= htmlspecialchars($shopUrl) ?>">EXPLORE THE COLLECTION →</a>
            </div>
        </section>
    </main>

    <!-- Original website footer: same colors, classes and layout -->
    <footer>
        <div class="footer-logo">CHWOO</div>
        <div class="footer-links">
            <a href="<?= htmlspecialchars($shopUrl) ?>">Shop</a>
            <!-- ABOUT is on hold until a page is available. -->
            <!-- Contact is on hold until a page is available. -->
            <a href="account.php?section=orders">Orders</a>
        </div>
        <p>&copy; <?= date('Y') ?> CHWOO Fashion Studio</p>
    </footer>
    <?php include __DIR__ . '/bag_drawer.php'; ?>
</body>
</html>
