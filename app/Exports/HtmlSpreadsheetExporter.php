<?php

namespace App\Exports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an Excel-compatible spreadsheet written as an HTML table.
 *
 * Excel opens the markup natively, no spreadsheet library is needed, and rows are
 * written as they are produced so an export never holds its whole result in memory.
 * Subclasses decide the rows; this owns the framing, the download headers and the
 * cell escaping — including the formula-injection guard every export needs.
 */
abstract class HtmlSpreadsheetExporter
{
    /**
     * Stream a spreadsheet whose body is written by `$writeBody`, which echoes rows
     * built with {@see row()} and {@see sectionRow()}.
     *
     * @param  list<string>  $headers
     */
    protected function stream(string $filename, string $sheetName, array $headers, callable $writeBody): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheetName, $headers, $writeBody) {
            echo $this->open($sheetName, $headers);
            $writeBody();
            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * The document preamble, worksheet name and header row.
     *
     * @param  list<string>  $headers
     */
    protected function open(string $sheetName, array $headers): string
    {
        $headerCells = '';
        foreach ($headers as $header) {
            $headerCells .= '<th>' . $this->escape($header) . '</th>';
        }

        return '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
            . '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
            . '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
            . '<x:Name>' . $this->escape($sheetName) . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>'
            . '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>'
            . '<body><table border="1"><thead><tr>'
            . $headerCells
            . '</tr></thead><tbody>';
    }

    /**
     * One data row.
     *
     * @param  list<string|int|float|null>  $cells
     */
    protected function row(array $cells): string
    {
        $row = '<tr>';
        foreach ($cells as $cell) {
            $row .= '<td>' . $this->escape((string) $cell) . '</td>';
        }

        return $row . '</tr>';
    }

    /**
     * A bold heading row spanning every column, to divide a sheet into sections.
     */
    protected function sectionRow(string $label, int $columns): string
    {
        return '<tr><td colspan="' . $columns . '" style="font-weight:bold;background:#e5e7eb">'
            . $this->escape($label) . '</td></tr>';
    }

    /**
     * HTML-escape a cell value, first neutralizing CSV/Excel formula-injection
     * prefixes (=, +, -, @, tab, CR, LF) by prepending a single quote.
     */
    protected function escape(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            $value = "'" . $value;
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
