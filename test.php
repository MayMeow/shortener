<?php 

require 'vendor/autoload.php';

use MayMeow\Shortener\LinkShortteningService;

$service = new LinkShortteningService();

try {
    for ($i = 1999; $i <= 2100; $i++) {
        $short = $service->numToShortString($i);
        $num = $service->shortStringToNum($short);
        if ($i !== $num) {
            echo "🛑 Test failed for number $i: got $num after conversion\n";
            exit(1);
        }
    }
} catch (InvalidArgumentException $e) {
    echo "🛑 Caught exception for invalid short string '$invalidShort': " . $e->getMessage() . "\n";
    exit(1);
}

echo "✅ All tests passed successfully.\n";
