<?php

declare(strict_types=1);

namespace Vellum\Semantic;

use RuntimeException;

/**
 * Everything a build produced: quantised vocabulary rows and section vectors,
 * each tagged with the group (an access level, say) allowed to see it. The
 * browser never gets the set itself, only forGroups() for the groups a reader
 * holds, so a token used only on a private page never reaches anyone else.
 *
 * File layout, little-endian:
 *   "VSEM", u8 format (1), u8 vocab bits, u8 section bits, u8 reserved,
 *   u16 dims, u32 token count, u32 section count,
 *   u32 length + attribution (UTF-8),
 *   u32 length + tokens joined by "\n" (row order),
 *   u32 length + section ids joined by "\n",
 *   vocab records, then section records (see Quantizer).
 */
final class SemanticSet
{
    public const MAGIC = 'VSEM';

    public const FORMAT = 1;

    /**
     * @param  list<string>  $tokens
     * @param  list<list<string>>  $tokenGroups  empty means every reader
     * @param  list<string>  $vocabRecords
     * @param  list<array{id: string, group: string}>  $sections
     * @param  list<string>  $sectionRecords
     */
    public function __construct(
        public readonly string $model,
        public readonly string $attribution,
        public readonly int $dims,
        public readonly int $vocabBits,
        public readonly int $sectionBits,
        public readonly array $tokens,
        public readonly array $tokenGroups,
        public readonly array $vocabRecords,
        public readonly array $sections,
        public readonly array $sectionRecords,
    ) {}

    /**
     * @param  list<string>  $groups
     */
    public function forGroups(array $groups): string
    {
        $allowed = array_flip($groups);
        $tokens = [];
        $vocab = '';

        foreach ($this->tokens as $index => $token) {
            $tags = $this->tokenGroups[$index];

            if ($tags === [] || array_intersect_key(array_flip($tags), $allowed) !== []) {
                $tokens[] = $token;
                $vocab .= $this->vocabRecords[$index];
            }
        }

        $ids = [];
        $sections = '';

        foreach ($this->sections as $index => $section) {
            if (isset($allowed[$section['group']])) {
                $ids[] = $section['id'];
                $sections .= $this->sectionRecords[$index];
            }
        }

        $tokenBlob = implode("\n", $tokens);
        $idBlob = implode("\n", $ids);

        return self::MAGIC
            .pack('CCCCvVV', self::FORMAT, $this->vocabBits, $this->sectionBits, 0, $this->dims, count($tokens), count($ids))
            .pack('V', strlen($this->attribution)).$this->attribution
            .pack('V', strlen($tokenBlob)).$tokenBlob
            .pack('V', strlen($idBlob)).$idBlob
            .$vocab
            .$sections;
    }

    /**
     * Parse a file forGroups() wrote.
     *
     * @return array{attribution: string, dims: int, tokens: list<string>, ids: list<string>, vocab: list<list<float>>, sections: list<list<float>>}
     */
    public static function read(string $bytes): array
    {
        if (strlen($bytes) < 18 || ! str_starts_with($bytes, self::MAGIC)) {
            throw new RuntimeException('Not a Vellum semantic file');
        }

        /** @var array{format: int, vocabBits: int, sectionBits: int, reserved: int, dims: int, tokens: int, sections: int} $head */
        $head = unpack('Cformat/CvocabBits/CsectionBits/Creserved/vdims/Vtokens/Vsections', $bytes, 4);

        if ($head['format'] !== self::FORMAT) {
            throw new RuntimeException("Unsupported semantic file format {$head['format']}");
        }

        $offset = 18;
        $blob = static function () use ($bytes, &$offset): string {
            /** @var array{1: int} $length */
            $length = unpack('V', $bytes, $offset);
            $value = substr($bytes, $offset + 4, $length[1]);
            $offset += 4 + $length[1];

            return $value;
        };

        $attribution = $blob();
        $tokenBlob = $blob();
        $idBlob = $blob();
        $tokens = $head['tokens'] === 0 ? [] : explode("\n", $tokenBlob);
        $ids = $head['sections'] === 0 ? [] : explode("\n", $idBlob);

        $read = static function (Quantizer $quantizer, int $count) use ($bytes, &$offset, $head): array {
            $size = $quantizer->recordBytes($head['dims']);
            $rows = [];

            for ($i = 0; $i < $count; $i++) {
                $rows[] = $quantizer->unpack(substr($bytes, $offset, $size), $head['dims']);
                $offset += $size;
            }

            return $rows;
        };

        return [
            'attribution' => $attribution,
            'dims' => $head['dims'],
            'tokens' => $tokens,
            'ids' => $ids,
            'vocab' => $read(new Quantizer($head['vocabBits']), $head['tokens']),
            'sections' => $read(new Quantizer($head['sectionBits']), $head['sections']),
        ];
    }

    public function save(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $meta = [
            'model' => $this->model,
            'attribution' => $this->attribution,
            'dims' => $this->dims,
            'vocabBits' => $this->vocabBits,
            'sectionBits' => $this->sectionBits,
            'tokens' => $this->tokens,
            'tokenGroups' => $this->tokenGroups,
            'sections' => $this->sections,
        ];

        file_put_contents($directory.'/semantic.bin', implode('', $this->vocabRecords).implode('', $this->sectionRecords));
        file_put_contents($directory.'/semantic.json', json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function load(string $directory): ?self
    {
        $meta = is_file($directory.'/semantic.json') ? json_decode((string) file_get_contents($directory.'/semantic.json'), true) : null;
        $records = is_file($directory.'/semantic.bin') ? (string) file_get_contents($directory.'/semantic.bin') : null;

        if (! is_array($meta) || $records === null) {
            return null;
        }

        $vocabSize = (new Quantizer((int) $meta['vocabBits']))->recordBytes((int) $meta['dims']);
        $sectionSize = (new Quantizer((int) $meta['sectionBits']))->recordBytes((int) $meta['dims']);
        $tokenCount = count($meta['tokens']);
        $vocabBytes = $tokenCount * $vocabSize;

        return new self(
            (string) $meta['model'],
            (string) $meta['attribution'],
            (int) $meta['dims'],
            (int) $meta['vocabBits'],
            (int) $meta['sectionBits'],
            array_values($meta['tokens']),
            array_values($meta['tokenGroups']),
            $vocabSize > 0 && $tokenCount > 0 ? str_split(substr($records, 0, $vocabBytes), $vocabSize) : [],
            array_values($meta['sections']),
            $sectionSize > 0 && strlen($records) > $vocabBytes ? str_split(substr($records, $vocabBytes), $sectionSize) : [],
        );
    }
}
