<?php

namespace App\Domains\Grant\Validators;

use App\Enums\DisbursementStatus;
use App\Enums\GrantApplicationStatus;
use App\Enums\GrantPaymentMethod;
use App\Exceptions\DomainException;
use App\Models\GrantApplication;
use App\Models\User;

class GrantApplicationValidator
{
    /** @param  array<int, GrantApplicationStatus>  $allowed */
    public function assertStatus(GrantApplication $grant, array $allowed): void
    {
        if (! in_array($grant->status, $allowed, true)) {
            $labels = implode(', ', array_map(fn (GrantApplicationStatus $s) => $s->value, $allowed));
            throw new DomainException(
                "Aksi tidak valid untuk status \"{$grant->status->value}\". Status diizinkan: {$labels}."
            );
        }
    }

    public function assertAssignableVerifier(User $verifier): void
    {
        if (! $verifier->is_active) {
            throw new DomainException('Verifikator yang ditugaskan harus akun aktif.');
        }

        if (! $verifier->can('grant.verify')) {
            throw new DomainException('User yang ditugaskan harus memiliki permission grant.verify.');
        }
    }

    public function assertReadyForVerification(GrantApplication $grant): void
    {
        if ($grant->assigned_verifier_id === null) {
            throw new DomainException('Verifikator wajib diisi sebelum dikirim ke verifikasi.');
        }
    }

    public function assertReadyForApproval(GrantApplication $grant): void
    {
        if ($grant->assigned_verifier_id === null) {
            throw new DomainException('Verifikator wajib terisi sebelum naik ke approval.');
        }

        $hasIdentity = filled($grant->recipient_identity_number) || $grant->hasIdentityDocument();
        if (! $hasIdentity) {
            throw new DomainException('Identitas penerima wajib: isi NIK atau unggah dokumen identitas (title: identity).');
        }

        if (! filled($grant->recipient_address)) {
            throw new DomainException('Alamat / cara menemui penerima wajib diisi sebelum approval.');
        }

        $verified = $grant->verified_amount !== null
            ? bcadd((string) $grant->verified_amount, '0', 2)
            : bcadd((string) $grant->recommended_amount, '0', 2);

        if (bccomp($verified, '0', 2) <= 0) {
            throw new DomainException('Nominal hasil verifikasi harus lebih besar dari nol.');
        }

        if (! filled($grant->verifier_notes)) {
            throw new DomainException('Catatan verifikator wajib diisi sebelum naik ke approval.');
        }

        if ($grant->payment_method === GrantPaymentMethod::TRANSFER) {
            if (! filled($grant->bank_name) || ! filled($grant->bank_account_number) || ! filled($grant->bank_account_holder)) {
                throw new DomainException('Rekening (bank, nomor, atas nama) wajib jika cara bayar transfer.');
            }
        }
    }

    public function assertApprovedAmount(GrantApplication $grant, string $approvedAmount): void
    {
        if (bccomp($approvedAmount, '0', 2) <= 0) {
            throw new DomainException('Nominal disetujui harus lebih besar dari nol.');
        }

        $ceiling = $grant->verified_amount !== null
            ? bcadd((string) $grant->verified_amount, '0', 2)
            : bcadd((string) $grant->recommended_amount, '0', 2);

        if (bccomp($approvedAmount, $ceiling, 2) > 0) {
            throw new DomainException(
                "Nominal disetujui ({$approvedAmount}) tidak boleh lebih besar dari hasil verifikasi ({$ceiling}). Kembalikan ke verifikasi jika perlu dinaikkan."
            );
        }
    }

    public function assertReadyToComplete(GrantApplication $grant): void
    {
        $this->assertStatus($grant, [GrantApplicationStatus::APPROVED]);

        $disbursement = $grant->disbursement;
        if ($disbursement === null) {
            throw new DomainException('Pengeluaran tertaut wajib ada sebelum serah terima selesai.');
        }

        if ($disbursement->status !== DisbursementStatus::APPROVED) {
            throw new DomainException('Pengeluaran tertaut harus berstatus approved sebelum pengajuan ditandai selesai.');
        }

        if ($grant->payment_method === GrantPaymentMethod::CASH && ! $grant->hasHandoverPhoto()) {
            throw new DomainException('Foto penyerahan wajib untuk bantuan tunai (title lampiran: handover).');
        }
    }
}
