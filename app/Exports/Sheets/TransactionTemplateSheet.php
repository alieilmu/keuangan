<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet utama template import: header baku + 2 baris contoh.
 */
class TransactionTemplateSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public const HEADINGS = ['tanggal', 'tipe', 'kategori', 'akun', 'nominal', 'keterangan'];

    /**
     * @param  array<int, array<int, string>>  $samples
     */
    public function __construct(private readonly array $samples = []) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return $this->samples;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return self::HEADINGS;
    }

    public function title(): string
    {
        return 'Transaksi';
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '059669']],
            ],
        ];
    }
}
