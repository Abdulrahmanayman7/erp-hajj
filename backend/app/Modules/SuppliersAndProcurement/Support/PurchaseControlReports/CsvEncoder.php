<?php

namespace App\Modules\SuppliersAndProcurement\Support\PurchaseControlReports;

/**
 * UTF-8 BOM CSV with formula-injection prefix (Phase V EC-V07).
 */
final class CsvEncoder
{
    public const BOM = "\xEF\xBB\xBF";

    public const MAX_ROWS = 10000;

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public function encode(array $headers, iterable $rows): string
    {
        $buffer = self::BOM;
        $buffer .= $this->line($headers);

        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $cell) {
                $cells[] = $this->cell($cell);
            }
            $buffer .= $this->line($cells);
        }

        return $buffer;
    }

    /**
     * @param  list<string>  $cells
     */
    private function line(array $cells): string
    {
        return implode(',', array_map(
            static fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"',
            $cells,
        ))."\n";
    }

    private function cell(string|int|float|null $value): string
    {
        $text = $value === null ? '' : (string) $value;
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
            return "'".$text;
        }

        return $text;
    }
}
