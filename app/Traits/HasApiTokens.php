<?php

namespace App\Traits;

use Laravel\Sanctum\HasApiTokens as SanctumHasApiTokens;

trait HasApiTokens
{
    use SanctumHasApiTokens;

    /**
     * Compatibility shim for older OAuth-aware code paths.
     */
    public function token()
    {
        return $this->currentAccessToken();
    }
}
