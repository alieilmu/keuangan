<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export transaksi dengan kolom yang identik dengan template import,
 * sehingga hasil export bisa langsung dipakai untuk import balik.
 */
class TransactionsExport implements Export, FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  Builder<Transaction>  $query
     */
    public function __construct(private readonly Builder $query, private readonly string $title = 'Transaksi') {}

    /**
     * @return Builder<Transaction>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['tanggal', 'tipe', 'kategori', 'akun', 'nominal', 'keterangan'];
    }

    /**
     * @param  Transaction  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->transaction_date?->format('Y-m-d'),
            $row->type->value,
            $row->category?->name,
            $row->account?->name,
            (float) $row->amount,
            $row->description,
        ];
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('#,##0');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '059669']],
            ],
        ];
    }
}
