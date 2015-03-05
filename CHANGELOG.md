# Changelog


## 1.7.2

* "Links only" support
* "no_results" tag support (bug fixed: printing {EE} tags when no results)

## 1.7.1

* Don't include blank target attributes (target='') in generated links. ([#33][33])

## 1.7.0

* Update to work with ExpressionEngine 2.8.0
* Use the new caching layer introduced in ExpressionEngine 2.8.0

## 1.6.1

* [bug]: Fix depricated function warning in EE 2.6 ([#25][25])
* [bug]: Stop caching authentication failure results ([#15][15])

## 1.6.0

* [feature]: Improvements to `images_only=` parameter to not show link text for images

## 1.5.0

* [feature]: Better date handling that doesn't require prefixes

## 1.4.2

* [bug]: Fix database tables to not conflict with other Twitter plugins

## 1.4.1

* [bug]: Fix issue with URL replacement

## 1.4.0

* [feature]: Add `images_only=` parameter to only show tweets with images


[15]: https://github.com/click-rain/EE_Twitter/issues/15
[25]: https://github.com/click-rain/EE_Twitter/issues/25
[33]: https://github.com/click-rain/EE_Twitter/issues/33
