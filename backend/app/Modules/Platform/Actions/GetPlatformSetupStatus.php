<?php

namespace App\Modules\Platform\Actions;

use App\Core\Authorization\EffectivePlatformPermissions;

final class GetPlatformSetupStatus
{
    /**
     * @return array{available: bool}
     */
    public function execute(): array
    {
        return [
            'available' => ! EffectivePlatformPermissions::platformAdministratorExists(),
        ];
    }
}
