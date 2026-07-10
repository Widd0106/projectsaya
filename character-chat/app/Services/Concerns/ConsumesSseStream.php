<?php

namespace App\Services\Concerns;

use Psr\Http\Message\StreamInterface;

trait ConsumesSseStream
{
    /**
     * Baca body response HTTP sebagai Server-Sent Events, potong per
     * blok event (dipisah baris kosong), lalu panggil $onEvent untuk
     * tiap baris "data: ...". Berhenti otomatis kalau ketemu "[DONE]"
     * (dipakai OpenRouter/OpenAI-style; Gemini cukup berhenti saat stream selesai).
     */
    protected function consumeSse(StreamInterface $body, callable $onEvent): void
    {
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $rawEvent) as $line) {
                    $line = trim($line);

                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));

                    if ($data === '' ) {
                        continue;
                    }

                    if ($data === '[DONE]') {
                        return;
                    }

                    $onEvent($data);
                }
            }
        }
    }
}