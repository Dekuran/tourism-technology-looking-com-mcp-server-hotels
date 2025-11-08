<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/tourism', \App\Mcp\TourismServer\TourismServer::class);
Mcp::web('/mcp/dsapi', \App\Mcp\DSAPIServer\DSAPIServer::class);