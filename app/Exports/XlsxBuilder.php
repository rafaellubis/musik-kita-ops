<?php

namespace App\Exports;

/**
 * Generator xlsx minimal yang sepenuhnya kompatibel dengan Excel.
 *
 * Mengapa tidak pakai PhpSpreadsheet?
 * PhpSpreadsheet menambah XML yang tidak perlu (namespace kosong, headerFooter kosong,
 * calcPr yang memicu recalculation) — menyebabkan dialog "repair" di Excel.
 * Builder ini menghasilkan xlsx seminimal mungkin: hanya elemen yang wajib ada.
 */
class XlsxBuilder
{
    /** @var string[] Shared strings table — urutan menentukan index */
    private array $strings = [];

    /** @var int[] Map string => index di shared strings */
    private array $stringIndex = [];

    /**
     * Build file xlsx dari data sheets, return raw bytes siap kirim sebagai HTTP response.
     *
     * @param  array $sheets [['name' => 'Nama Sheet', 'rows' => [[sel, sel, ...], ...]], ...]
     * @return string Raw bytes xlsx
     */
    public function build(array $sheets): string
    {
        // Reset state antar pemanggilan
        $this->strings     = [];
        $this->stringIndex = [];

        // Pass pertama: kumpulkan semua string unik untuk shared strings table
        foreach ($sheets as $sheet) {
            foreach ($sheet['rows'] as $row) {
                foreach ($row as $cell) {
                    if (is_string($cell) && $cell !== '') {
                        $this->registerString($cell);
                    }
                }
            }
        }

        // Buat file xlsx di temp file (ZipArchive butuh path, tidak bisa stream langsung)
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',         $this->buildContentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels',                 $this->buildRootRels());
        $zip->addFromString('xl/workbook.xml',             $this->buildWorkbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels',  $this->buildWorkbookRels(count($sheets)));
        $zip->addFromString('xl/styles.xml',               $this->buildStyles());
        $zip->addFromString('xl/sharedStrings.xml',        $this->buildSharedStrings());

        foreach ($sheets as $i => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($i + 1) . '.xml',
                $this->buildSheet($sheet['rows'])
            );
        }

        $zip->close();

        $content = file_get_contents($tmp);
        @unlink($tmp);

        return $content;
    }

    // ============= PRIVATE BUILDERS =============

    private function registerString(string $s): int
    {
        if (!array_key_exists($s, $this->stringIndex)) {
            $this->stringIndex[$s] = count($this->strings);
            $this->strings[] = $s;
        }
        return $this->stringIndex[$s];
    }

    /** Konversi index kolom (0-based) ke huruf Excel: 0=A, 25=Z, 26=AA, dst */
    private function colLetter(int $col): string
    {
        $letter = '';
        $n = $col + 1;
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)) . $letter;
            $n      = (int)($n / 26);
        }
        return $letter;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function buildContentTypes(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function buildRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
            . ' Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbook(array $sheets): string
    {
        $sheetEls = '';
        foreach ($sheets as $i => $sheet) {
            $sheetEls .= '<sheet name="' . $this->esc($sheet['name']) . '"'
                . ' sheetId="' . ($i + 1) . '"'
                . ' r:id="rId' . ($i + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetEls . '</sheets>'
            . '</workbook>';
    }

    private function buildWorkbookRels(int $sheetCount): string
    {
        $rels = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . $i . '.xml"/>';
        }

        $stylesId = $sheetCount + 1;
        $ssId     = $sheetCount + 2;

        $rels .= '<Relationship Id="rId' . $stylesId . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            . ' Target="styles.xml"/>';
        $rels .= '<Relationship Id="rId' . $ssId . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"'
            . ' Target="sharedStrings.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    private function buildStyles(): string
    {
        // Minimal styles.xml — hanya elemen wajib, tidak ada elemen kosong
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';
    }

    private function buildSharedStrings(): string
    {
        $count = count($this->strings);
        $items = '';
        foreach ($this->strings as $s) {
            // xml:space="preserve" menjaga spasi di awal/akhir string
            $items .= '<si><t xml:space="preserve">' . $this->esc($s) . '</t></si>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' count="' . $count . '" uniqueCount="' . $count . '">'
            . $items
            . '</sst>';
    }

    private function buildSheet(array $rows): string
    {
        $rowXml = '';
        foreach ($rows as $rowIdx => $row) {
            $r      = $rowIdx + 1;
            $colXml = '';

            foreach ($row as $colIdx => $cell) {
                if ($cell === null || $cell === '') {
                    continue; // Skip sel kosong — hemat ukuran file
                }

                $ref = $this->colLetter($colIdx) . $r;

                if (is_int($cell) || is_float($cell)) {
                    // Angka — simpan langsung sebagai value numerik
                    $colXml .= '<c r="' . $ref . '"><v>' . $cell . '</v></c>';
                } else {
                    // String — referensi ke shared strings
                    $idx    = $this->registerString((string)$cell);
                    $colXml .= '<c r="' . $ref . '" t="s"><v>' . $idx . '</v></c>';
                }
            }

            if ($colXml !== '') {
                $rowXml .= '<row r="' . $r . '">' . $colXml . '</row>';
            }
        }

        // Worksheet minimal — tidak ada headerFooter kosong, tidak ada namespace yang tidak dipakai
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $rowXml . '</sheetData>'
            . '</worksheet>';
    }
}
