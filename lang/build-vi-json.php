<?php

declare(strict_types=1);

/**
 * Regenerates lang/vi.json from the map below. Run: php lang/build-vi-json.php
 */
$map = require __DIR__.'/vi_translations.php';

ksort($map);

$json = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

file_put_contents(__DIR__.'/vi.json', $json);

echo 'Wrote '.count($map)." entries to lang/vi.json\n";
