<?php

use Laravel\Mcp\Facades\Mcp;
use Illuminate\Support\Facades\Route;

Mcp::web('/mcp/capcorn', \App\Mcp\CapCornServer\CapCornServer::class);

/**
 * Public metadata endpoint to expose the MCP server's instructions and tools.
 * This enables remote verification (e.g., from Cloud Run) without local reflection.
 */
Route::get('/mcp/capcorn/meta', function () {
    $fqcn = \App\Mcp\CapCornServer\CapCornServer::class;

    try {
        $rc = new ReflectionClass($fqcn);
        $defaults = $rc->getDefaultProperties();

        $name = $defaults['name'] ?? null;
        $version = $defaults['version'] ?? null;
        $instructions = $defaults['instructions'] ?? null;
        $toolClasses = $defaults['tools'] ?? [];
    } catch (\Throwable $e) {
        return response()->json([
            'server' => $fqcn,
            'error' => 'reflection_failed',
            'message' => $e->getMessage(),
        ], 500);
    }

    $tools = [];
    foreach ($toolClasses as $toolClass) {
        $desc = null;
        try {
            $trc = new ReflectionClass($toolClass);
            $tDefaults = $trc->getDefaultProperties();
            if (array_key_exists('description', $tDefaults)) {
                $desc = $tDefaults['description'];
            }
        } catch (\Throwable $e) {
            $desc = null;
        }
        $tools[] = [
            'class' => $toolClass,
            'description' => $desc,
        ];
    }

    return response()->json([
        'server' => $fqcn,
        'name' => $name,
        'version' => $version,
        'instructions' => $instructions,
        'tools' => $tools,
    ]);
});