<?php
declare(strict_types=1);

return [
    'name' => 'installer',
    'label' => 'Installation wizard',
    'version' => '1.0.0',
    'description' => 'Step-by-step installer. Only active until the site is installed; locks itself afterwards.',
    'core' => true,
    'installer_only' => true,
    'routes' => 'routes.php',
];
