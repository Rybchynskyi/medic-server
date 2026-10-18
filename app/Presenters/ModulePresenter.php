<?php

namespace App\Presenters;

use App\Models\Module;

class ModulePresenter extends CorePresenter
{
    public function transform(Module $module): array
    {
        return [
            'key' => $module->key,
            'name' => $module->name,
            'description' => $module->description,
            'enabledForNewOrganizations' => $module->enabled_for_new_organizations,
        ];
    }
}
