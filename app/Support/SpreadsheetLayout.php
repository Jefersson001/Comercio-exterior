<?php

namespace App\Support;

use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;

class SpreadsheetLayout
{
    /** @param array<int, int> $widths */
    public static function configure(Sheet $sheet, string $name, int $columns, array $widths = []): void
    {
        $sheet->setName($name);
        for ($column = 1; $column <= $columns; $column++) {
            $sheet->setColumnWidth($widths[$column] ?? 22, $column);
        }
        $sheet->setSheetView(new SheetView(freezeRow: 2));
        $sheet->setPrintTitleRows('1:1');
    }

    public static function header(): Style
    {
        return new Style(fontBold: true, fontSize: 8, fontColor: Color::WHITE, backgroundColor: '203764', shouldWrapText: true);
    }

    public static function body(): Style
    {
        return new Style(fontSize: 8, shouldWrapText: true);
    }
}
