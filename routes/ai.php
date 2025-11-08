<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/capcorn', \App\Mcp\CapCornServer\CapCornServer::class);