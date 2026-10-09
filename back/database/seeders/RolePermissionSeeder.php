<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\PermissionCode;
use App\Domain\Users\Enums\RoleCode;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Os 6 perfis, as permissões iniciais e a matriz perfil → permissões.
 * Idempotente: pode rodar em qualquer ambiente, inclusive produção.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCode::cases() as $permission) {
            Permission::query()->updateOrCreate(
                ['code' => $permission->value],
                ['name' => $permission->label()],
            );
        }

        foreach (PermissionCode::matrix() as $roleCode => $permissions) {
            $code = RoleCode::from($roleCode);

            $role = Role::query()->updateOrCreate(
                ['code' => $code->value],
                ['name' => $code->label(), 'description' => $code->description()],
            );

            $role->permissions()->sync(
                Permission::query()->whereIn('code', array_map(fn ($p) => $p->value, $permissions))->pluck('id'),
            );
        }
    }
}
