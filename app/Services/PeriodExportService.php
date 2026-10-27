<?php

namespace App\Services;

use App\Models\Period;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PeriodExportService extends CoreService
{
    private const MONTH_NAMES = [
        1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень',
        5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень',
        9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень',
    ];

    public function buildSpreadsheet(Period $period, Collection $totals): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Результат');

        $sheet->setCellValue('A1', $period->organization->name);
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $periodLabel = sprintf('Звітний період: %s %d', self::MONTH_NAMES[$period->month], $period->year);
        if ($period->isOpen()) {
            $periodLabel .= ' (попередній розрахунок, період не закрито)';
        }
        $sheet->setCellValue('A2', $periodLabel);
        $sheet->mergeCells('A2:C2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $headerRow = 4;
        $sheet->setCellValue("A{$headerRow}", 'Найменування лікарських засобів та виробів медичного призначення');
        $sheet->setCellValue("B{$headerRow}", 'Одиниця виміру');
        $sheet->setCellValue("C{$headerRow}", 'Витрати за період');

        $headerStyle = $sheet->getStyle("A{$headerRow}:C{$headerRow}");
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('198754');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        $row = $headerRow + 1;
        foreach ($totals as $total) {
            $sheet->setCellValue("A{$row}", $total->material->name);
            $sheet->setCellValue("B{$row}", $total->material->unit);
            $sheet->setCellValue("C{$row}", (float) $total->total_quantity);
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:C{$lastRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        foreach (['A', 'B', 'C'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(55);

        return $spreadsheet;
    }

    public function buildFileName(Period $period): string
    {
        return sprintf(
            'Результат_витрат_Мат_%s_%d.xlsx',
            self::MONTH_NAMES[$period->month],
            $period->year
        );
    }
}
