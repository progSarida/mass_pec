<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["view_role","view_any_role","create_role","update_role","delete_role","delete_any_role","view_admin::type","view_any_admin::type","create_admin::type","update_admin::type","restore_admin::type","restore_any_admin::type","replicate_admin::type","reorder_admin::type","delete_admin::type","delete_any_admin::type","force_delete_admin::type","force_delete_any_admin::type","view_attachment","view_any_attachment","create_attachment","update_attachment","restore_attachment","restore_any_attachment","replicate_attachment","reorder_attachment","delete_attachment","delete_any_attachment","force_delete_attachment","force_delete_any_attachment","view_city","view_any_city","create_city","update_city","restore_city","restore_any_city","replicate_city","reorder_city","delete_city","delete_any_city","force_delete_city","force_delete_any_city","view_istat::type","view_any_istat::type","create_istat::type","update_istat::type","restore_istat::type","restore_any_istat::type","replicate_istat::type","reorder_istat::type","delete_istat::type","delete_any_istat::type","force_delete_istat::type","force_delete_any_istat::type","view_province","view_any_province","create_province","update_province","restore_province","restore_any_province","replicate_province","reorder_province","delete_province","delete_any_province","force_delete_province","force_delete_any_province","view_recipient","view_any_recipient","create_recipient","update_recipient","restore_recipient","restore_any_recipient","replicate_recipient","reorder_recipient","delete_recipient","delete_any_recipient","force_delete_recipient","force_delete_any_recipient","view_region","view_any_region","create_region","update_region","restore_region","restore_any_region","replicate_region","reorder_region","delete_region","delete_any_region","force_delete_region","force_delete_any_region","view_sender","view_any_sender","create_sender","update_sender","restore_sender","restore_any_sender","replicate_sender","reorder_sender","delete_sender","delete_any_sender","force_delete_sender","force_delete_any_sender","view_shipment","view_any_shipment","create_shipment","update_shipment","restore_shipment","restore_any_shipment","replicate_shipment","reorder_shipment","delete_shipment","delete_any_shipment","force_delete_shipment","force_delete_any_shipment","view_state","view_any_state","create_state","update_state","restore_state","restore_any_state","replicate_state","reorder_state","delete_state","delete_any_state","force_delete_state","force_delete_any_state","view_user","view_any_user","create_user","update_user","restore_user","restore_any_user","replicate_user","reorder_user","delete_user","delete_any_user","force_delete_user","force_delete_any_user"]}]';
        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (! blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            /** @var Model $roleModel */
            $roleModel = Utils::getRoleModel();
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = $roleModel::firstOrCreate([
                    'name' => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (! blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect($rolePlusPermission['permissions'])
                        ->map(fn ($permission) => $permissionModel::firstOrCreate([
                            'name' => $permission,
                            'guard_name' => $rolePlusPermission['guard_name'],
                        ]))
                        ->all();

                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (! blank($permissions = json_decode($directPermissions, true))) {
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($permissions as $permission) {
                if ($permissionModel::whereName($permission)->doesntExist()) {
                    $permissionModel::create([
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}
