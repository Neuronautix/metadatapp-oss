<?php

declare(strict_types=1);

namespace App\Service\Pdf;

final class TextPdfRenderer
{
    // A4 page dimensions in PDF points (1 pt = 1/72 inch)
    private const int PAGE_WIDTH_A4 = 595;
    private const int PAGE_HEIGHT_A4 = 842;

    // PDF layout parameters
    private const int MARGIN_LEFT = 50;
    private const int MARGIN_TOP = 800;
    private const int LINE_HEIGHT = 14;
    private const int MAX_CHARS_PER_LINE = 95;

    /**
     * Renders a minimal raw PDF document containing the provided text lines.
     *
     * @param list<string> $lines
     */
    public function render(string $title, array $lines, string $producer): string
    {
        $wrappedLines = [];
        foreach ($lines as $line) {
            if ('' === $line) {
                $wrappedLines[] = '';
                continue;
            }
            $chunks = wordwrap($line, self::MAX_CHARS_PER_LINE, "\n", true);
            foreach (explode("\n", $chunks) as $chunk) {
                $wrappedLines[] = $chunk;
            }
        }

        $linesPerPage = (int) ((self::MARGIN_TOP - 50) / self::LINE_HEIGHT);
        $pages = array_chunk($wrappedLines, $linesPerPage);
        if ([] === $pages) {
            $pages = [[]];
        }

        $objects = [];
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[3] = "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        $nextObj = 4;
        $pageObjectIds = [];

        foreach ($pages as $pageLines) {
            $contentLines = [
                'BT',
                '/F1 10 Tf',
                \sprintf('%d %d Td', self::MARGIN_LEFT, self::MARGIN_TOP),
                \sprintf('%d TL', self::LINE_HEIGHT),
            ];

            foreach ($pageLines as $textLine) {
                $contentLines[] = '(' . $this->escapePdf($textLine) . ") '\n";
            }

            $contentLines[] = 'ET';

            $stream = implode("\n", $contentLines);
            $streamLen = \strlen($stream);

            $contentObjId = $nextObj;
            $objects[$contentObjId] = \sprintf(
                "%d 0 obj\n<< /Length %d >>\nstream\n%s\nendstream\nendobj\n",
                $contentObjId,
                $streamLen,
                $stream,
            );
            ++$nextObj;

            $pageObjId = $nextObj;
            $objects[$pageObjId] = \sprintf(
                "%d 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Contents %d 0 R /Resources << /Font << /F1 3 0 R >> >> >>\nendobj\n",
                $pageObjId,
                self::PAGE_WIDTH_A4,
                self::PAGE_HEIGHT_A4,
                $contentObjId,
            );
            $pageObjectIds[] = $pageObjId;
            ++$nextObj;
        }

        $kids = implode(' 0 R ', $pageObjectIds) . ' 0 R';
        $objects[2] = \sprintf(
            "2 0 obj\n<< /Type /Pages /Kids [%s] /Count %d >>\nendobj\n",
            $kids,
            \count($pageObjectIds),
        );

        $escapedTitle = $this->escapePdf($title);
        $body = "%PDF-1.4\n";
        $body .= "%\xe2\xe3\xcf\xd3\n";
        $xrefOffsets = [];

        foreach (range(1, $nextObj - 1) as $i) {
            if (!isset($objects[$i])) {
                continue;
            }
            $xrefOffsets[$i] = \strlen($body);
            $body .= $objects[$i];
        }

        $xrefPos = \strlen($body);
        $xrefCount = $nextObj;

        $xref = "xref\n0 {$xrefCount}\n";
        $xref .= "0000000000 65535 f \n";
        for ($i = 1; $i < $xrefCount; ++$i) {
            if (isset($xrefOffsets[$i])) {
                $xref .= \sprintf("%010d 00000 n \n", $xrefOffsets[$i]);
            } else {
                $xref .= "0000000000 65535 f \n";
            }
        }

        $trailer = \sprintf(
            "trailer\n<< /Size %d /Root 1 0 R /Info << /Title (%s) /Producer (%s) >> >>\nstartxref\n%d\n%%%%EOF",
            $xrefCount,
            $escapedTitle,
            $this->escapePdf($producer),
            $xrefPos,
        );

        return $body . $xref . $trailer;
    }

    private function escapePdf(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\(', $text);
        $text = str_replace(')', '\)', $text);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $text) ?? $text;
    }
}
