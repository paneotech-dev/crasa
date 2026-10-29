<?php

namespace App\Support\Documents;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * The text of a PDF, for search inside documents.
 *
 * Uses the tools already on the server: pdftotext where it is installed,
 * otherwise Ghostscript's text device (Ghostscript is what renders the
 * document thumbnails, so it is present wherever those work). Neither is a
 * new dependency. With neither, a PDF is found by its name and its folder's
 * description only, and that is a normal state rather than an error.
 *
 * The text is capped: search wants the words, not a second copy of a
 * two-hundred-page annex.
 */
final class PdfText
{
    private const MAX_CHARS = 200_000;

    private const TIMEOUT = 30;

    /** Which tool would be used here, or null when none is installed. */
    public static function tool(): ?string
    {
        $finder = new ExecutableFinder;

        if ($finder->find('pdftotext') !== null) {
            return 'pdftotext';
        }

        return $finder->find('gs') !== null ? 'gs' : null;
    }

    public static function extract(string $absolutePath): ?string
    {
        $finder = new ExecutableFinder;

        $command = match (self::tool()) {
            'pdftotext' => [$finder->find('pdftotext'), '-q', '-enc', 'UTF-8', $absolutePath, '-'],
            'gs' => [$finder->find('gs'), '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=txtwrite', '-sOutputFile=-', $absolutePath],
            default => null,
        };

        if ($command === null || ! is_file($absolutePath)) {
            return null;
        }

        try {
            $process = new Process($command);
            $process->setTimeout(self::TIMEOUT);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $text = preg_replace('/\s+/u', ' ', mb_convert_encoding($process->getOutput(), 'UTF-8', 'UTF-8')) ?? '';
            $text = trim($text);

            return $text === '' ? null : mb_substr($text, 0, self::MAX_CHARS);
        } catch (Throwable) {
            return null;
        }
    }
}
