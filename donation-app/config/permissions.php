<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Staff permission matrix — docs/srs.md §3.1
|--------------------------------------------------------------------------
| Single source of truth for who can do what. RolesAndPermissionsSeeder turns
| this into spatie/laravel-permission roles + permissions named "{area}.{ability}"
| (e.g. "donors.update"). Policies check those names. Change access here, in
| code, and re-run the seeder — not by clicking in the admin panel.
|
| Levels: F = full · C = view/create/update · A = view/approve · V = view · - = none
| super_admin bypasses every check (Gate::before), its column is informational.
*/

return [

    'abilities' => ['view_any', 'view', 'create', 'update', 'delete', 'approve', 'export'],

    'levels' => [
        'F' => ['view_any', 'view', 'create', 'update', 'delete', 'approve', 'export'],
        'C' => ['view_any', 'view', 'create', 'update'],
        'A' => ['view_any', 'view', 'approve'],
        'V' => ['view_any', 'view'],
        '-' => [],
    ],

    'roles' => ['super_admin', 'manager', 'finance', 'campaign_manager', 'field_officer', 'trustee', 'auditor'],

    // area => [super_admin, manager, finance, campaign_manager, field_officer, trustee, auditor]
    'matrix' => [
        'users' => ['F', 'V', '-', '-', '-', '-', 'V'], // users, roles & settings
        'donors' => ['F', 'C', 'C', 'V', '-', 'V', 'V'],
        'campaigns' => ['F', 'C', 'V', 'C', '-', 'V', 'V'], // funds & campaigns
        'donations' => ['F', 'V', 'C', 'V', '-', 'V', 'V'], // donations & payments
        'proof_verification' => ['F', '-', 'A', '-', '-', '-', 'V'],
        'receipts' => ['F', 'V', 'C', '-', '-', '-', 'V'], // receipts & certificates
        'beneficiaries' => ['F', 'C', 'V', '-', 'C', 'V', 'V'], // beneficiaries & applications
        'disbursements' => ['F', 'A', 'C', '-', 'C', 'A', 'V'],
        'qurbani' => ['F', 'C', 'C', 'C', 'C', 'V', 'V'], // qurbani & inventory
        'ledger' => ['F', 'V', 'C', '-', '-', 'V', 'V'], // ledger & reconciliation
        'reports' => ['F', 'V', 'V', 'V', 'V', 'V', 'V'], // reports & dashboard
        'cms' => ['F', 'C', '-', 'C', '-', '-', '-'],
        'audit_log' => ['V', 'V', '-', '-', '-', 'V', 'V'],
    ],

];
