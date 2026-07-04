<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Update source
    |--------------------------------------------------------------------------
    | GitHub repository checked for new releases (owner/name). Release tags
    | are compared against the running core version (UpdateController::VERSION).
    */

    'repository' => env('HYBRIDCORE_UPDATE_REPO', 'HybridMindLabs/HybridCore'),

    /*
    |--------------------------------------------------------------------------
    | Allow updating from the admin panel
    |--------------------------------------------------------------------------
    | Disable on managed/immutable deployments where updates are rolled out
    | by CI instead of the panel.
    */

    'panel_updates' => env('HYBRIDCORE_PANEL_UPDATES', true),
];
