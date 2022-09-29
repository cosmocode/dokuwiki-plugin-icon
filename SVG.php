<?php

namespace dokuwiki\plugin\icon;

use dokuwiki\HTTP\HTTPClient;
use dokuwiki\StyleUtils;

class SVG
{
    const SOURCES = [
        'mdi' => "https://raw.githubusercontent.com/Templarian/MaterialDesign/master/svg/%s.svg",
        'fab' => "https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/svgs/brands/%s.svg",
        'fas' => "https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/svgs/solid/%s.svg",
        'fa' => "https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/svgs/regular/%s.svg",
        'twbs' => "https://raw.githubusercontent.com/twbs/icons/main/icons/%s.svg",
    ];

    protected $file;
    protected $svg;

    protected $url = '';
    protected $color = 'currentColor';
    protected $width = 'auto';
    protected $height = '1.2em';

    public function __construct($source, $icon)
    {
        if (!isset(self::SOURCES[$source])) throw new \RuntimeException('Unknown icon source');
        $this->url = sprintf(self::SOURCES[$source], $icon);
    }

    public function getFile()
    {
        $cache = getCacheName(
            join("\n", [$this->url, $this->color, $this->width, $this->height]),
            '.icon.svg'
        );

        if (!file_exists($cache) || filemtime($cache) < filemtime(__FILE__)) {
            $http = new HTTPClient();
            $svg = $http->get($this->url);
            if (!$svg) throw new \RuntimeException('Failed to download SVG: ' . $http->error);
            $svg = $this->processSVG($svg);
            io_saveFile($cache, $svg);
        }

        return $cache;
    }

    /**
     * Set the intrinsic width
     *
     * @param string $width
     * @return void
     */
    public function setWidth($width)
    {
        // these are fine
        if ($width == 'auto' || $width == '') {
            $this->width = $width;
            return;
        }

        // we accept numbers and units only
        if (preg_match('/^\d*\.?\d+(px|em|ex|pt|in|pc|mm|cm|rem|vh|vw)?$/', $width)) {
            $this->width = $width;
            return;
        }

        $this->width = 'auto'; // fall back to default
    }

    /**
     * Set the intrinsic height
     *
     * @param string $height
     * @return void
     */
    public function setHeight($height)
    {
        // these are fine
        if ($height == 'auto' || $height == '') {
            $this->height = $height;
            return;
        }

        // we accept numbers and units only
        if (preg_match('/^\d*\.?\d+(px|em|ex|pt|in|pc|mm|cm|rem|vh|vw)?$/', $height)) {
            $this->height = $height;
            return;
        }

        $this->height = '1.3em'; // fall back to default
    }

    /**
     * Set the fill color to use
     *
     * Can be a hex color or a style.ini replacement
     *
     * @param string $color
     * @return void
     */
    public function setColor($color)
    {
        // these are fine
        if ($color == 'currentColor' || $color == '') {
            $this->color = $color;
            return;
        }

        // hex colors are easy too
        if (self::isHexColor($color)) {
            $this->color = '#' . ltrim($color, '#');
            return;
        }
        // see if the given color is an ini replacement
        $styleUtil = new StyleUtils();
        $ini = $styleUtil->cssStyleini();
        if (isset($ini['replacements'][$color]) && self::isHexColor($ini['replacements'][$color])) {
            $this->color = '#' . ltrim($ini['replacements'][$color], '#');
            return;
        }
        if (isset($ini['replacements']["__{$color}__"]) && self::isHexColor($ini['replacements']["__{$color}__"])) {
            $this->color = '#' . ltrim($ini['replacements']["__{$color}__"], '#');
            return;
        }

        $this->color = 'currentColor';
    }

    /**
     * Check if the given value is a hex color
     *
     * @link https://stackoverflow.com/a/53330328
     * @param string $color
     * @return bool
     */
    public static function isHexColor($color)
    {
        return (bool)preg_match('/^#?(?:(?:[\da-f]{3}){1,2}|(?:[\da-f]{4}){1,2})$/i', $color);
    }

    /**
     * Minify SVG and apply transofrmations
     *
     * @param string $svgdata
     * @return string
     */
    protected function processSVG($svgdata)
    {
        // strip namespace declarations FIXME is there a cleaner way?
        $svgdata = preg_replace('/\sxmlns(:.*?)?="(.*?)"/', '', $svgdata);

        $dom = new \DOMDocument();
        $dom->loadXML($svgdata, LIBXML_NOBLANKS);
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;

        $svg = $dom->getElementsByTagName('svg')->item(0);

        // prefer viewbox over width/height
        if (!$svg->hasAttribute('viewBox')) {
            $w = $svg->getAttribute('width');
            $h = $svg->getAttribute('height');
            if ($w && $h) {
                $svg->setAttribute('viewBox', "0 0 $w $h");
            }
        }

        // remove unwanted attributes from root
        $this->removeAttributes($svg, ['viewBox']);

        // remove unwanted attributes from primitives
        foreach ($dom->getElementsByTagName('path') as $elem) {
            $this->removeAttributes($elem, ['d']);
        }
        foreach ($dom->getElementsByTagName('rect') as $elem) {
            $this->removeAttributes($elem, ['x', 'y', 'rx', 'ry']);
        }
        foreach ($dom->getElementsByTagName('circle') as $elem) {
            $this->removeAttributes($elem, ['cx', 'cy', 'r']);
        }
        foreach ($dom->getElementsByTagName('ellipse') as $elem) {
            $this->removeAttributes($elem, ['cx', 'cy', 'rx', 'ry']);
        }
        foreach ($dom->getElementsByTagName('line') as $elem) {
            $this->removeAttributes($elem, ['x1', 'x2', 'y1', 'y2']);
        }
        foreach ($dom->getElementsByTagName('polyline') as $elem) {
            $this->removeAttributes($elem, ['points']);
        }
        foreach ($dom->getElementsByTagName('polygon') as $elem) {
            $this->removeAttributes($elem, ['points']);
        }

        // remove comments see https://stackoverflow.com/a/60420210
        $xpath = new \DOMXPath($dom);
        for ($els = $xpath->query('//comment()'), $i = $els->length - 1; $i >= 0; $i--) {
            $els->item($i)->parentNode->removeChild($els->item($i));
        }

        // readd namespace
        $svg->setAttribute('xmlns', 'http://www.w3.org/2000/svg');

        if ($this->color) $svg->setAttribute('fill', $this->color);
        if ($this->width) $svg->setAttribute('width', $this->width);
        if ($this->height) $svg->setAttribute('height', $this->height);

        $svgdata = $dom->saveXML($svg);
        return $svgdata;
    }

    /**
     * Remove all attributes except the given keepers
     *
     * @param \DOMNode $element
     * @param string[] $keep
     */
    protected function removeAttributes($element, $keep)
    {
        $attributes = $element->attributes;
        for ($i = $attributes->length - 1; $i >= 0; $i--) {
            $name = $attributes->item($i)->name;
            if (in_array($name, $keep)) continue;
            $element->removeAttribute($name);
        }
    }

}
