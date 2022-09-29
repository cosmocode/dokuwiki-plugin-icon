<?php

use dokuwiki\plugin\icon\SVG;

/**
 * DokuWiki Plugin icon (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class action_plugin_icon extends \dokuwiki\Extension\ActionPlugin
{

    /** @inheritDoc */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('FETCH_MEDIA_STATUS', 'BEFORE', $this, 'handleMediaStatus');

    }

    /**
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  optional parameter passed when event was registered
     * @return void
     */
    public function handleMediaStatus(Doku_Event $event, $param)
    {
        $d = &$event->data;
        if(substr($d['media'],0, 5) !== 'icon:') return;

        $parts = explode(':', $d['media']); // we use pseudo namespaces for configuration
        array_shift($parts); // remove icon prefix
        if(!$parts) return; // no icon given - return the default 404
        $icon = array_pop($parts);
        $icon = basename($icon, '.svg');
        $conf = $this->parseConfig($parts);

        try {
            $svg = new SVG($conf['source'], $icon);
            $svg->setColor($conf['color']);
            $svg->setWidth($conf['width']);
            $svg->setHeight($conf['height']);
        } catch (\Exception $e) {
            \dokuwiki\ErrorHandler::logException($e);
            return;
        }

        $d['file'] = $svg->getFile();
        $d['status'] = 200;
        $d['statusmessage'] = 'Ok';
    }

    /**
     * Try to figure out what the different pseudo namespaces represent in terms of configuration
     *
     * @param string[] $parts
     * @return array
     */
    protected function parseConfig($parts) {
        // defaults
        $conf = [
            'source' => 'mdi',
            'color' => 'currentColor',
            'width' => 'auto',
            'height' => '1.2rem',
        ];

        // regular expressions to use
        $regexes = [
            'source' => '/^('.join('|', array_keys(SVG::SOURCES)).')$/',
            'width' => '/^w-(.*)$/',
            'height' => '/^h-(.*)$/',
        ];

        // find the pieces by regex
        $all = count($parts);
        for($i=0; $i<$all; $i++) {
            $part = $parts[$i];
            foreach ($regexes as $key => $regex) {
                if(preg_match($regex, $part, $m)) {
                    $conf[$key] = $m[1];
                    unset($parts[$i]);
                    continue 2;
                }
            }
        }

        // any remains are the color
        if(count($parts)) {
            $conf['color'] = array_shift($parts);
        }

        return $conf;
    }

}

