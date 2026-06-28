<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankFeeController;
use App\Http\Controllers\Api\BankReconciliationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DisbursementController;
use App\Http\Controllers\Api\DonorController;
use App\Http\Controllers\Api\FundController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ReceiptAllocationController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
*/
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | Master Data
    |----------------------------------------------------------------------
    */
    Route::get('donors', [DonorController::class, 'index'])->middleware('permission:donor.view');
    Route::get('donors/{donor}', [DonorController::class, 'show'])->middleware('permission:donor.view');
    Route::post('donors', [DonorController::class, 'store'])->middleware('permission:donor.manage');
    Route::put('donors/{donor}', [DonorController::class, 'update'])->middleware('permission:donor.manage');
    Route::delete('donors/{donor}', [DonorController::class, 'destroy'])->middleware('permission:donor.manage');

    Route::get('funds', [FundController::class, 'index'])->middleware('permission:fund.view');
    Route::get('funds/{fund}', [FundController::class, 'show'])->middleware('permission:fund.view');
    Route::post('funds', [FundController::class, 'store'])->middleware('permission:fund.manage');
    Route::put('funds/{fund}', [FundController::class, 'update'])->middleware('permission:fund.manage');
    Route::delete('funds/{fund}', [FundController::class, 'destroy'])->middleware('permission:fund.manage');

    Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:account.view');
    Route::get('accounts/{account}', [AccountController::class, 'show'])->middleware('permission:account.view');
    Route::post('accounts', [AccountController::class, 'store'])->middleware('permission:account.manage');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->middleware('permission:account.manage');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->middleware('permission:account.manage');

    Route::get('programs', [ProgramController::class, 'index'])->middleware('permission:program.view');
    Route::get('programs/{program}', [ProgramController::class, 'show'])->middleware('permission:program.view');
    Route::post('programs', [ProgramController::class, 'store'])->middleware('permission:program.manage');
    Route::put('programs/{program}', [ProgramController::class, 'update'])->middleware('permission:program.manage');
    Route::delete('programs/{program}', [ProgramController::class, 'destroy'])->middleware('permission:program.manage');

    /*
    |----------------------------------------------------------------------
    | Penerimaan & Alokasi
    |----------------------------------------------------------------------
    */
    Route::get('receipts', [ReceiptController::class, 'index'])->middleware('permission:receipt.view');
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->middleware('permission:receipt.view');
    Route::post('receipts', [ReceiptController::class, 'store'])->middleware('permission:receipt.create');
    Route::post('receipts/{receipt}/post', [ReceiptController::class, 'post'])->middleware('permission:receipt.post');
    Route::post('receipts/{receipt}/reverse', [ReceiptController::class, 'reverse'])->middleware('permission:receipt.reverse');

    Route::get('receipts/{receipt}/allocations', [ReceiptAllocationController::class, 'index'])->middleware('permission:allocation.view');
    Route::post('receipts/{receipt}/allocations', [ReceiptAllocationController::class, 'store'])->middleware('permission:allocation.manage');
    Route::post('allocations/{allocation}/reverse', [ReceiptAllocationController::class, 'reverse'])->middleware('permission:allocation.manage');

    /*
    |----------------------------------------------------------------------
    | Pengeluaran & Approval
    |----------------------------------------------------------------------
    */
    Route::get('disbursements', [DisbursementController::class, 'index'])->middleware('permission:disbursement.view');
    Route::get('disbursements/{disbursement}', [DisbursementController::class, 'show'])->middleware('permission:disbursement.view');
    Route::post('disbursements', [DisbursementController::class, 'store'])->middleware('permission:disbursement.create');
    Route::put('disbursements/{disbursement}', [DisbursementController::class, 'update'])->middleware('permission:disbursement.create');
    Route::post('disbursements/{disbursement}/submit', [DisbursementController::class, 'submit'])->middleware('permission:disbursement.submit');
    Route::post('disbursements/{disbursement}/verify', [DisbursementController::class, 'verify'])->middleware('permission:disbursement.verify');
    Route::post('disbursements/{disbursement}/approve', [DisbursementController::class, 'approve'])->middleware('permission:disbursement.approve');
    Route::post('disbursements/{disbursement}/reject', [DisbursementController::class, 'reject'])->middleware('permission:disbursement.reject');
    Route::post('disbursements/{disbursement}/reverse', [DisbursementController::class, 'reverse'])->middleware('permission:disbursement.reverse');

    /*
    |----------------------------------------------------------------------
    | Biaya Administrasi Bank
    |----------------------------------------------------------------------
    */
    Route::get('bank-fees', [BankFeeController::class, 'index'])->middleware('permission:bankfee.view');
    Route::get('bank-fees/{bankFee}', [BankFeeController::class, 'show'])->middleware('permission:bankfee.view');
    Route::post('bank-fees', [BankFeeController::class, 'store'])->middleware('permission:bankfee.manage');
    Route::post('bank-fees/{bankFee}/post', [BankFeeController::class, 'post'])->middleware('permission:bankfee.post');
    Route::post('bank-fees/{bankFee}/reverse', [BankFeeController::class, 'reverse'])->middleware('permission:bankfee.reverse');

    /*
    |----------------------------------------------------------------------
    | Rekonsiliasi Bank
    |----------------------------------------------------------------------
    */
    Route::get('bank-reconciliations', [BankReconciliationController::class, 'index'])->middleware('permission:reconciliation.view');
    Route::get('bank-reconciliations/{bankReconciliation}', [BankReconciliationController::class, 'show'])->middleware('permission:reconciliation.view');
    Route::post('bank-reconciliations', [BankReconciliationController::class, 'store'])->middleware('permission:reconciliation.manage');
    Route::post('bank-reconciliations/{bankReconciliation}/lines', [BankReconciliationController::class, 'addLine'])->middleware('permission:reconciliation.manage');
    Route::post('bank-reconciliations/{bankReconciliation}/complete', [BankReconciliationController::class, 'complete'])->middleware('permission:reconciliation.manage');

    /*
    |----------------------------------------------------------------------
    | Audit Trail
    |----------------------------------------------------------------------
    */
    Route::get('audits', [AuditController::class, 'index'])->middleware('permission:audit.view');
    Route::get('audits/{audit}', [AuditController::class, 'show'])->middleware('permission:audit.view');

    /*
    |----------------------------------------------------------------------
    | Laporan
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:report.view')->prefix('reports')->group(function () {
        Route::get('fund-balances', [ReportController::class, 'fundBalances']);
        Route::get('account-balances', [ReportController::class, 'accountBalances']);
        Route::get('ledger', [ReportController::class, 'ledger']);
        Route::get('fund-statement', [ReportController::class, 'fundStatement']);
    });

    /*
    |----------------------------------------------------------------------
    | Portal Donatur
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:portal.view')->prefix('portal')->group(function () {
        Route::get('profile', [PortalController::class, 'profile']);
        Route::get('summary', [PortalController::class, 'summary']);
        Route::get('donations', [PortalController::class, 'donations']);
    });
});
