<?php

/*
 * The image slots the site draws, and the smallest photograph that fills
 * each one sharply (item 1i, Day 3). One list, read by the media upload
 * form, the news form's "Upload a photo", the media list and the server
 * side warning, so a slot changes size in one place.
 *
 * Each minimum comes from the CSS that draws the slot and from the WebP
 * widths App\Services\MediaLibrary::WIDTHS builds (400, 800, 1200, 1600;
 * never upscaled, so a photograph narrower than 1600 never gets the 1600
 * rendition the widest slots ask for):
 *
 * hero: the homepage slider. k.css .k-hero__box is the 1200px container
 *   (.k-wrap, --k-container) split 45fr/55fr and 560px tall, so the photo
 *   column (.k-hero__photo) is about 659 by 560 CSS pixels at 1366 wide
 *   (home.blade.php sizes "660px"); at 900px and below it is the full
 *   width and 340px tall. On a 2x screen that is 1318 by 1120 device
 *   pixels, cropped with object-fit: cover. 1600 wide is the largest
 *   rendition; 1200 tall at 4:3 covers the 1120.
 *
 * news: the news cards and the article hero. ui.css .u-card__media is
 *   16:10; the featured card (pages.css .cp-news-card--featured) is 7/12 of
 *   1200, about 700px wide; the article hero (ui.css .u-hero__photo) is 4:3
 *   at 500px and 16:9 full width below 992px, served from the 'hero' role
 *   of ResizedImage (up to the 1600 rendition). 1600 by 1000 is 16:10 at
 *   the largest rendition and covers the 4:3 and 16:9 crops.
 *
 * people: ui.css .u-avatar in the about page people grids
 *   (.cp-about-people__grid) is a 56px circle, object-fit: cover. The
 *   smallest rendition is 400 wide; 400 by 400 square fills the circle at
 *   any screen density with room for the crop.
 *
 * other: no slot, no minimum.
 */

return [
    'hero' => ['width' => 1600, 'height' => 1200, 'ratio' => '4:3'],
    'news' => ['width' => 1600, 'height' => 1000, 'ratio' => '16:10'],
    'people' => ['width' => 400, 'height' => 400, 'ratio' => '1:1'],
    'other' => null,
];
