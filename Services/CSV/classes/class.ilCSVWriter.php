<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

class ilCSVWriter
{
    private string $csv = '';
    private string $separator = ',';
    private string $delimiter = '"';
    private string $new_line = "\n";
    private bool $first_entry = true;
    private bool $sep_directive = false;
    private bool $normalize_line_breaks = false;

    public function setSeparator(string $a_sep): void
    {
        $this->separator = $a_sep;
    }

    /**
     * Prepend a sep= directive so Excel auto-detects the separator.
     * Must be called before any addColumn()/addRow() calls.
     */
    public function setSepDirective(bool $enabled = true): void
    {
        $this->sep_directive = $enabled;
    }

    /**
     * Replace line breaks in field values with spaces.
     * Keep disabled by default to preserve CSV data fidelity.
     */
    public function setNormalizeLineBreaks(bool $enabled = true): void
    {
        $this->normalize_line_breaks = $enabled;
    }

    public function setDelimiter(string $a_del): void
    {
        $this->delimiter = $a_del;
    }

    public function addRow(): void
    {
        $this->csv .= $this->new_line;
        $this->first_entry = true;
    }

    public function addColumn(string $a_col): void
    {
        if (!$this->first_entry) {
            $this->csv .= $this->separator;
        }
        $this->csv .= $this->delimiter;
        $this->csv .= $this->quote($a_col);
        $this->csv .= $this->delimiter;
        $this->first_entry = false;
    }

    public function getCSVString(): string
    {
        if ($this->sep_directive) {
            return 'sep=' . $this->separator . $this->new_line . $this->csv;
        }
        return $this->csv;
    }

    private function quote(string $a_str): string
    {
        if ($this->normalize_line_breaks) {
            $a_str = str_replace(["\r\n", "\r", "\n"], ' ', $a_str);
        }
        return str_replace(
            $this->delimiter,
            $this->delimiter . $this->delimiter,
            $a_str
        );
    }
}
