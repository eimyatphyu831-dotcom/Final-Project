<?php

class XlsxWriter
{
    private array $sheets = [];

    public function addSheet(string $name): void
    {
        $this->sheets[] = [
            'name' => $name,
            'rows' => [],
        ];
    }

    public function writeRow(array $values, array $options = []): void
    {
        $idx = count($this->sheets) - 1;
        $this->sheets[$idx]['rows'][] = [
            'values' => $values,
            'bold' => $options['bold'] ?? false,
            'header' => $options['header'] ?? false,
            'bordered' => $options['bordered'] ?? false,
            'total' => $options['total'] ?? false,
            'section' => $options['section'] ?? false,
        ];
    }

    public function output(string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        echo $this->build();
        exit;
    }

    public function build(): string
    {
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString("xl/worksheets/sheet" . ($i + 1) . ".xml", $this->sheetXml($sheet));
        }

        $zip->close();
        $data = file_get_contents($tmp);
        unlink($tmp);
        return $data;
    }

    private function contentTypesXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->sheets as $i => $_) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
        return $xml;
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function workbookXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>';
        foreach ($this->sheets as $i => $sheet) {
            $name = htmlspecialchars($sheet['name'], ENT_XML1);
            $xml .= '<sheet name="' . $name . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }

    private function workbookRelsXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $_) {
            $xml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $xml .= '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
        return $xml;
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="4">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/></font>
<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="12"/><name val="Calibri"/></font>
</fonts>
<fills count="4">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFC3B1E1"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFF3F0FA"/></patternFill></fill>
</fills>
<borders count="2">
<border><left/><right/><top/><bottom/><diagonal/></border>
<border>
<left style="thin"><color auto="1"/></left>
<right style="thin"><color auto="1"/></right>
<top style="thin"><color auto="1"/></top>
<bottom style="thin"><color auto="1"/></bottom>
<diagonal/>
</border>
</borders>
<cellStyleXfs count="1">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
</cellStyleXfs>
<cellXfs count="6">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
</cellXfs>
</styleSheet>';
    }

    private function sheetXml(array $sheet): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        $maxCols = 0;
        foreach ($sheet['rows'] as $row) {
            $maxCols = max($maxCols, count($row['values']));
        }

        if ($maxCols > 0) {
            $xml .= '<cols>';
            for ($c = 1; $c <= $maxCols; $c++) {
                $width = ($c === 1 && $maxCols > 3) ? 30 : 22;
                $xml .= '<col min="' . $c . '" max="' . $c . '" width="' . $width . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($sheet['rows'] as $rIdx => $row) {
            $xml .= '<row r="' . ($rIdx + 1) . '">';
            $styleIdx = $this->getStyleIndex($row);
            foreach ($row['values'] as $cIdx => $value) {
                $col = chr(65 + $cIdx);
                $cellRef = $col . ($rIdx + 1);
                if (is_numeric($value)) {
                    $xml .= '<c r="' . $cellRef . '" s="' . $styleIdx . '"><v>' . $value . '</v></c>';
                } else {
                    $safe = htmlspecialchars($value ?? '', ENT_XML1);
                    $xml .= '<c r="' . $cellRef . '" t="inlineStr" s="' . $styleIdx . '"><is><t>' . $safe . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function getStyleIndex(array $row): int
    {
        if ($row['header']) return 2;
        if ($row['total']) return 4;
        if ($row['section']) return 5;
        if ($row['bordered']) return 3;
        if ($row['bold']) return 1;
        return 0;
    }
}
