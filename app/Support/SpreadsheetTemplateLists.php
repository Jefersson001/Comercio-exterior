<?php

namespace App\Support;

use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Common\Helper\CellHelper;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Validation\Rules\WholeNumberValidationRule;
use OpenSpout\Writer\XLSX\Validation\ValidationDisplay;
use OpenSpout\Writer\XLSX\Validation\ValidationOperator;
use OpenSpout\Writer\XLSX\Writer;

class SpreadsheetTemplateLists
{
    /** @var list<array{title: string, values: list<string>}> */
    private array $columns = [];

    public function __construct(private Options $options) {}

    /** @param list<string> $values */
    public function source(string $title, array $values): string
    {
        $column = CellHelper::getColumnLettersFromColumnIndex(count($this->columns));
        $this->columns[] = ['title' => $title, 'values' => array_values($values)];

        return "'Listas'!\${$column}\$2:\${$column}\$".max(2, count($values) + 1);
    }

    public function formula(string $source): string
    {
        return 'INDIRECT("'.$source.'")';
    }

    /** @param array<string, list<string>> $groups */
    public function dependent(string $cell, string $label, array $groups): string
    {
        $names = [];
        $ranges = [];
        $column = CellHelper::getColumnLettersFromColumnIndex(count($this->columns));
        $empty = "'Listas'!\${$column}\$2:\${$column}\$2";
        $allValues = [''];
        foreach ($groups as $name => $values) {
            $names[] = (string) $name;
            if ($values === []) {
                $ranges[] = $empty;
            } else {
                $start = count($allValues) + 2;
                $allValues = array_merge($allValues, $values);
                $end = count($allValues) + 1;
                $ranges[] = "'Listas'!\${$column}\${$start}:\${$column}\${$end}";
            }
        }
        $this->source($label, $allValues);
        $startColumn = CellHelper::getColumnLettersFromColumnIndex(count($this->columns));
        $this->source($label.' / selección', $names);
        $endColumn = CellHelper::getColumnLettersFromColumnIndex(count($this->columns));
        $this->source($label.' / referencia', $ranges);
        $map = "'Listas'!\${$startColumn}\$2:\${$endColumn}\$".max(2, count($names) + 1);

        return 'INDIRECT(IFERROR(VLOOKUP('.$cell.',INDIRECT("'.$map.'"),2,FALSE),"'.$empty.'"))';
    }

    public function dropdown(int $sheet, int $column, string $formula, string $label, bool $strict = true): void
    {
        $this->options->addValidation($column, 2, $column, 1048576, new SpreadsheetListRule($formula), new ValidationDisplay(
            showErrorMessage: $strict,
            promptTitle: 'Selecciona '.$label,
            prompt: $strict ? 'Usa la lista. Si el dato no aparece, regístralo primero en el sistema y descarga otra vez la plantilla.' : 'Puedes seleccionar un valor existente o escribir uno nuevo. La importación validará el contenido.',
            errorTitle: 'Valor fuera del catálogo',
            error: 'El valor no está en la lista. Selecciona un dato existente o regístralo primero en el sistema.',
        ), $sheet);
    }

    public function number(int $sheet, int $column, int $minimum = 1): void
    {
        $this->options->addValidation($column, 2, $column, 1048576, new WholeNumberValidationRule(ValidationOperator::Between, $minimum, 2147483647), new ValidationDisplay(errorTitle: 'Cantidad incorrecta', error: 'Ingresa un número entero dentro del rango permitido.'), $sheet);
    }

    public function write(Writer $writer): void
    {
        $mainSheet = $writer->getCurrentSheet();
        $writer->addNewSheetAndMakeItCurrent();
        SpreadsheetLayout::configure($writer->getCurrentSheet(), 'Listas', count($this->columns));
        $writer->addRow(Row::fromValuesWithStyle(array_column($this->columns, 'title'), SpreadsheetLayout::header()));
        $rows = max(1, ...array_map(fn (array $column): int => count($column['values']), $this->columns));
        for ($row = 0; $row < $rows; $row++) {
            $writer->addRow(new Row(array_map(fn (array $column): StringCell => new StringCell($column['values'][$row] ?? ''), $this->columns)));
        }
        $writer->setCurrentSheet($mainSheet);
    }
}
