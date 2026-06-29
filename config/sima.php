<?php

use App\Enums\UserRole;

return [

    /*
    |--------------------------------------------------------------------------
    | Daftar Permission
    |--------------------------------------------------------------------------
    | Format: "modul.aksi". Dipakai oleh seeder & policy.
    */
    'permissions' => [
        // Master data
        'donor.view', 'donor.manage',
        'fund.view', 'fund.manage',
        'account.view', 'account.manage',
        'program.view', 'program.manage',

        // Penerimaan (alokasi menyatu dengan penerimaan)
        'receipt.view', 'receipt.create',
        'receipt.submit', 'receipt.approve', 'receipt.reject', 'receipt.reverse',

        // Pengeluaran & approval
        'disbursement.view', 'disbursement.create',
        'disbursement.submit', 'disbursement.verify',
        'disbursement.approve', 'disbursement.reject', 'disbursement.reverse',

        // Biaya bank
        'bankfee.view', 'bankfee.manage', 'bankfee.post', 'bankfee.reverse',

        // Rekonsiliasi
        'reconciliation.view', 'reconciliation.manage',

        // Liabilitas operasional
        'liability.view', 'liability.manage',

        // Lampiran/bukti
        'attachment.view', 'attachment.manage',

        // Audit, laporan, pengguna, portal
        'audit.view',
        'report.view',
        'user.manage',
        'portal.view',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pemetaan Role -> Permission
    |--------------------------------------------------------------------------
    | Gunakan ['*'] untuk seluruh permission (super admin).
    */
    'roles' => [
        UserRole::ADMIN->value => ['*'],

        UserRole::BENDAHARA->value => [
            'donor.view', 'donor.manage',
            'fund.view',
            'account.view',
            'program.view', 'program.manage',
            'receipt.view', 'receipt.create', 'receipt.submit',
            'disbursement.view', 'disbursement.create', 'disbursement.submit',
            'bankfee.view', 'bankfee.manage', 'bankfee.post', 'bankfee.reverse',
            'reconciliation.view', 'reconciliation.manage',
            'liability.view', 'liability.manage',
            'attachment.view', 'attachment.manage',
            'report.view',
        ],

        UserRole::VERIFIKATOR->value => [
            'donor.view', 'fund.view', 'account.view', 'program.view',
            'receipt.view',
            'disbursement.view', 'disbursement.verify', 'disbursement.reject',
            'bankfee.view',
            'liability.view',
            'attachment.view',
            'report.view',
        ],

        UserRole::KETUA->value => [
            'donor.view', 'fund.view', 'account.view', 'program.view',
            'receipt.view', 'receipt.approve', 'receipt.reject', 'receipt.reverse',
            'disbursement.view', 'disbursement.approve', 'disbursement.reject', 'disbursement.reverse',
            'bankfee.view',
            'reconciliation.view',
            'liability.view',
            'attachment.view',
            'report.view',
        ],

        UserRole::AUDITOR->value => [
            'donor.view', 'fund.view', 'account.view', 'program.view',
            'receipt.view', 'disbursement.view',
            'bankfee.view', 'reconciliation.view',
            'liability.view', 'attachment.view',
            'audit.view', 'report.view',
        ],

        UserRole::DONATUR->value => [
            'portal.view',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dana Sistem (tidak boleh dihapus)
    |--------------------------------------------------------------------------
    */
    'system_funds' => [
        [
            'system_key' => 'suspense',
            'code' => 'SYS-SUSPENSE',
            'name' => 'Dana Belum Dialokasikan (Suspense)',
            'type' => 'unrestricted',
            'description' => 'Dana sistem legacy/placeholder. Alur penerimaan aktif memposting langsung ke Dana tujuan (inline allocation).',
        ],
        [
            'system_key' => 'operational',
            'code' => 'SYS-OPERASIONAL',
            'name' => 'Dana Operasional',
            'type' => 'unrestricted',
            'description' => 'Dana umum/operasional. Default penanggung biaya administrasi bank.',
        ],
        [
            'system_key' => 'bank_admin',
            'code' => 'SYS-BANKADMIN',
            'name' => 'Dana Biaya Administrasi Bank',
            'type' => 'unrestricted',
            'description' => 'Dana khusus penanggung biaya administrasi/transfer bank (opsional).',
        ],
        [
            'system_key' => 'opening_equity',
            'code' => 'SYS-OPENING',
            'name' => 'Saldo Awal (Opening Equity)',
            'type' => 'unrestricted',
            'description' => 'Dana lawan untuk posting saldo awal kas/bank.',
        ],
    ],
];
