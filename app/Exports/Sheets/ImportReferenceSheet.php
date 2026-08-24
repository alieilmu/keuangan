<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet panduan: daftar nama akun & kategori yang valid untuk user ini,
 * supaya isian pada sheet "Transaksi" tidak bentrok saat diimpor.
 */
class ImportReferenceSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array<int, string>>  $rows
     */
    public function __construct(private readonly array $rows) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['kolom', 'nilai yang diterima', 'catatan'];
    }

    public function title(): string
    {
        return 'Panduan';
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F172A']],
            ],
        ];
    }
}
