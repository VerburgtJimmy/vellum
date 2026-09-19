<?php

declare(strict_types=1);

namespace Vellum\Semantic;

use Normalizer;
use RuntimeException;

/**
 * BERT uncased WordPiece, matching Hugging Face tokenizers for BertNormalizer
 * (clean text, CJK padding, accent stripping, lowercase) and BertPreTokenizer.
 * The browser tokenizer mirrors this one; the parity fixtures hold them together.
 */
final class WordPieceTokenizer implements Tokenizer
{
    private const MAX_CHARS = 100;

    /**
     * @param  array<string, int>  $vocab  token => id
     */
    public function __construct(
        private readonly array $vocab,
        private readonly string $unknown = '[UNK]',
    ) {}

    public static function fromTokenizerJson(string $path): self
    {
        $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $model = is_array($json) ? ($json['model'] ?? null) : null;

        if (! is_array($model) || ($model['type'] ?? null) !== 'WordPiece' || ! is_array($model['vocab'] ?? null)) {
            throw new RuntimeException("{$path} is not a WordPiece tokenizer");
        }

        $vocab = [];

        foreach ($model['vocab'] as $token => $id) {
            $vocab[(string) $token] = (int) $id;
        }

        return new self($vocab, (string) ($model['unk_token'] ?? '[UNK]'));
    }

    public function restrictedTo(array $ids): self
    {
        $unknown = $this->unknownId();

        return new self(
            array_filter($this->vocab, static fn (int $id): bool => isset($ids[$id]) || $id === $unknown),
            $this->unknown,
        );
    }

    public function unknownId(): int
    {
        return $this->vocab[$this->unknown] ?? throw new RuntimeException("The vocabulary has no {$this->unknown} token");
    }

    /**
     * @return array<string, int>
     */
    public function vocab(): array
    {
        return $this->vocab;
    }

    /**
     * @return list<int>
     */
    public function ids(string $text): array
    {
        $ids = [];

        foreach ($this->tokens($text) as $token) {
            $ids[] = $this->vocab[$token] ?? $this->unknownId();
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $tokens = [];

        foreach ($this->words($this->normalize($text)) as $word) {
            array_push($tokens, ...$this->pieces($word));
        }

        return $tokens;
    }

    private function normalize(string $text): string
    {
        $text = (string) preg_replace('/[\x{0}\x{FFFD}]|(?![\t\n\r])[\p{Cc}\p{Cf}]/u', '', $text);
        $text = (string) preg_replace('/[\t\n\r\p{Zs}]/u', ' ', $text);
        $text = (string) preg_replace(
            '/([\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{20000}-\x{2A6DF}\x{2A700}-\x{2B73F}\x{2B740}-\x{2B81F}\x{2B820}-\x{2CEAF}\x{F900}-\x{FAFF}\x{2F800}-\x{2FA1F}])/u',
            ' $1 ',
            $text,
        );
        $text = (string) preg_replace('/\p{Mn}/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));

        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        preg_match_all('/[!-\/:-@\[-`{-~]|\p{P}|[^\s!-\/:-@\[-`{-~\p{P}]+/u', $text, $matches);

        return $matches[0];
    }

    /**
     * @return list<string>
     */
    private function pieces(string $word): array
    {
        $chars = mb_str_split($word, 1, 'UTF-8');
        $length = count($chars);

        if ($length > self::MAX_CHARS) {
            return [$this->unknown];
        }

        $pieces = [];
        $start = 0;

        while ($start < $length) {
            $end = $length;
            $found = null;

            while ($start < $end) {
                $piece = implode('', array_slice($chars, $start, $end - $start));

                if ($start > 0) {
                    $piece = '##'.$piece;
                }

                if (isset($this->vocab[$piece])) {
                    $found = $piece;

                    break;
                }

                $end--;
            }

            if ($found === null) {
                return [$this->unknown];
            }

            $pieces[] = $found;
            $start = $end;
        }

        return $pieces;
    }
}
