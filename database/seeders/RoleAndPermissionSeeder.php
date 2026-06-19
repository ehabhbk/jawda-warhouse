<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'view-items', 'label' => 'عرض الأصناف', 'group' => 'الأصناف'],
            ['name' => 'create-items', 'label' => 'إضافة صنف', 'group' => 'الأصناف'],
            ['name' => 'edit-items', 'label' => 'تعديل صنف', 'group' => 'الأصناف'],
            ['name' => 'delete-items', 'label' => 'حذف صنف', 'group' => 'الأصناف'],

            ['name' => 'view-purchases', 'label' => 'عرض المشتريات', 'group' => 'المشتريات'],
            ['name' => 'create-purchases', 'label' => 'إضافة مشتريات', 'group' => 'المشتريات'],
            ['name' => 'edit-purchases', 'label' => 'تعديل مشتريات', 'group' => 'المشتريات'],
            ['name' => 'delete-purchases', 'label' => 'حذف مشتريات', 'group' => 'المشتريات'],

            ['name' => 'view-orders', 'label' => 'عرض الطلبات', 'group' => 'الطلبات'],
            ['name' => 'create-orders', 'label' => 'إنشاء طلب', 'group' => 'الطلبات'],
            ['name' => 'approve-orders', 'label' => 'اعتماد الطلبات', 'group' => 'الطلبات'],
            ['name' => 'reject-orders', 'label' => 'رفض الطلبات', 'group' => 'الطلبات'],
            ['name' => 'receive-orders', 'label' => 'تسليم الطلبات', 'group' => 'الطلبات'],
            ['name' => 'complete-orders', 'label' => 'إكمال الطلبات', 'group' => 'الطلبات'],

            ['name' => 'view-warehouses', 'label' => 'عرض المخازن', 'group' => 'المخازن'],
            ['name' => 'create-warehouses', 'label' => 'إضافة مخزن', 'group' => 'المخازن'],
            ['name' => 'edit-warehouses', 'label' => 'تعديل مخزن', 'group' => 'المخازن'],
            ['name' => 'delete-warehouses', 'label' => 'حذف مخزن', 'group' => 'المخازن'],

            ['name' => 'view-shelves', 'label' => 'عرض الرفوف', 'group' => 'الرفوف'],
            ['name' => 'create-shelves', 'label' => 'إضافة رف', 'group' => 'الرفوف'],
            ['name' => 'edit-shelves', 'label' => 'تعديل رف', 'group' => 'الرفوف'],
            ['name' => 'delete-shelves', 'label' => 'حذف رف', 'group' => 'الرفوف'],

            ['name' => 'view-categories', 'label' => 'عرض التصنيفات', 'group' => 'التصنيفات'],
            ['name' => 'create-categories', 'label' => 'إضافة تصنيف', 'group' => 'التصنيفات'],
            ['name' => 'edit-categories', 'label' => 'تعديل تصنيف', 'group' => 'التصنيفات'],
            ['name' => 'delete-categories', 'label' => 'حذف تصنيف', 'group' => 'التصنيفات'],

            ['name' => 'view-suppliers', 'label' => 'عرض الموردين', 'group' => 'الموردين'],
            ['name' => 'create-suppliers', 'label' => 'إضافة مورد', 'group' => 'الموردين'],
            ['name' => 'edit-suppliers', 'label' => 'تعديل مورد', 'group' => 'الموردين'],
            ['name' => 'delete-suppliers', 'label' => 'حذف مورد', 'group' => 'الموردين'],

            ['name' => 'view-users', 'label' => 'عرض المستخدمين', 'group' => 'المستخدمين'],
            ['name' => 'create-users', 'label' => 'إضافة مستخدم', 'group' => 'المستخدمين'],
            ['name' => 'edit-users', 'label' => 'تعديل مستخدم', 'group' => 'المستخدمين'],
            ['name' => 'delete-users', 'label' => 'حذف مستخدم', 'group' => 'المستخدمين'],

            ['name' => 'view-roles', 'label' => 'عرض الأدوار', 'group' => 'الأدوار'],
            ['name' => 'create-roles', 'label' => 'إضافة دور', 'group' => 'الأدوار'],
            ['name' => 'edit-roles', 'label' => 'تعديل دور', 'group' => 'الأدوار'],
            ['name' => 'delete-roles', 'label' => 'حذف دور', 'group' => 'الأدوار'],

            ['name' => 'view-permissions', 'label' => 'عرض الصلاحيات', 'group' => 'الصلاحيات'],

            ['name' => 'view-reports', 'label' => 'عرض التقارير', 'group' => 'التقارير'],
            ['name' => 'view-stock-movements', 'label' => 'عرض حركة المخزن', 'group' => 'التقارير'],

            ['name' => 'view-pos', 'label' => 'عرض نقطة البيع', 'group' => 'نقطة البيع'],
            ['name' => 'create-pos-sales', 'label' => 'إجراء عملية بيع', 'group' => 'نقطة البيع'],
            ['name' => 'view-pos-sales', 'label' => 'عرض عمليات البيع', 'group' => 'نقطة البيع'],
            ['name' => 'cancel-pos-sales', 'label' => 'إلغاء عملية بيع', 'group' => 'نقطة البيع'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        $roles = [
            'admin' => ['label' => 'مدير النظام', 'description' => 'صلاحية كاملة على جميع أجزاء النظام', 'all' => true],
            'storekeeper' => ['label' => 'أمين مخزن', 'description' => 'إدارة المخازن والمشتريات والطلبات', 'permissions' => [
                'view-items', 'create-items', 'edit-items',
                'view-purchases', 'create-purchases', 'edit-purchases',
                'view-orders', 'create-orders', 'approve-orders', 'reject-orders', 'receive-orders',
                'view-warehouses',
                'view-shelves', 'create-shelves', 'edit-shelves',
                'view-categories', 'create-categories', 'edit-categories',
                'view-suppliers', 'create-suppliers', 'edit-suppliers',
                'view-stock-movements',
                'view-reports',
                'view-pos', 'create-pos-sales', 'view-pos-sales', 'cancel-pos-sales',
            ]],
            'user' => ['label' => 'مستخدم', 'description' => 'طلب الأصناف وعرضها', 'permissions' => [
                'view-items',
                'view-orders', 'create-orders', 'receive-orders',
                'view-warehouses',
            ]],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $data['label'], 'description' => $data['description'], 'is_active' => true]
            );

            if ($data['all'] ?? false) {
                $role->permissions()->sync(Permission::pluck('id'));
            } else {
                $permIds = Permission::whereIn('name', $data['permissions'])->pluck('id');
                $role->permissions()->sync($permIds);
            }
        }
    }
}
