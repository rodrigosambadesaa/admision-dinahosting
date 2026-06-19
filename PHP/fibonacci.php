<?php

declare(strict_types=1);

require __DIR__ . '/src/autoload.php';

use App\Console\Application;

$application = new Application();
$application->run($argv);
