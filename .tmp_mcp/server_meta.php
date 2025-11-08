<?php
require __DIR__ . '/../vendor/autoload.php';

$fqcn = \App\Mcp\CapCornServer\CapCornServer::class;

try {
    $rc = new ReflectionClass($fqcn);
    $defaults = $rc->getDefaultProperties();

    $instructions = $defaults['instructions'] ?? null;
    $toolClasses = $defaults['tools'] ?? [];
} catch (\Throwable $e) {
    echo json_encode([
        'server' => $fqcn,
        'error' => 'reflection_failed',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);
}

$tools = [];
foreach ($toolClasses as $toolClass) {
    $desc = null;
    try {
        $trc = new ReflectionClass($toolClass);
        $tDefaults = $trc->getDefaultProperties();
        if (array_key_exists('description', $tDefaults)) {
            $desc = $tDefaults['description'];
        } elseif ($trc->hasProperty('description')) {
            // Fallback: read protected property via reflection without constructor
            $rp = $trc->getProperty('description');
            $rp->setAccessible(true);
            $inst = $trc->newInstanceWithoutConstructor();
            $desc = $rp->getValue($inst);
        }
    } catch (\Throwable $e) {
        $desc = null;
    }
    $tools[] = [
        'class' => $toolClass,
        'description' => $desc,
    ];
}

echo json_encode([
    'server' => $fqcn,
    'instructions' => $instructions,
    'tools' => $tools,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
