<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Menghasilkan nomor dokumen berurutan: PREFIX/YYYY/000001.
     * Menggunakan lock baris counter agar aman dari race condition.
     */
    public function next(string $prefix): string
    {
        $year = (int) date('Y');

        return DB::transaction(function () use ($prefix, $year): string {
            $row = DB::table('document_sequences')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('document_sequences')->insert([
                    'prefix' => $prefix,
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $number = 1;
            } else {
                $number = $row->last_number + 1;
                DB::table('document_sequences')
                    ->where('id', $row->id)
                    ->update(['last_number' => $number, 'updated_at' => now()]);
            }

            return sprintf('%s/%d/%06d', $prefix, $year, $number);
        });
    }
}
