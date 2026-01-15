<?php
/**
 * @file
 * Site alias for all sites
 */
$root = '/var/www/html';
$aliases['dev'] = array(
    'uri' => 'developers.gasy.live',
    'root' => $root,
    'path-aliases' => array(
        '%dump-dir' => '/tmp'
    ),
);
$aliases['mizara'] = array(
    'uri' => 'www.mizara.org',
    'root' => $root,
    'path-aliases' => array(
        '%dump-dir' => '/tmp'
    ),
);