<?php

use Framework\Application;

return Application::configure(FRAMEWORK_MCP_EXAMPLE_PATH)
    ->use_prefix('mcp_example_')
    ->use_app_mode('development')
    ->use_routing(FRAMEWORK_MCP_EXAMPLE_PATH . 'routes/api.php')
    ->boot();
