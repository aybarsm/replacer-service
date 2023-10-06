<?php

namespace Aybarsm\Replacer\Service\Abstracts;

use Aybarsm\Replacer\Service\Contracts\ReplacerInterface;

abstract class AbstractReplacer implements ReplacerInterface
{
    abstract protected static function prepareReplacementKey(string $key): string;
    abstract protected static function prepareReplacements(array $replacements): array;
    abstract protected static function standardiseReplacementSource(array $data): array;
    abstract protected static function standardiseReplacementSourceKey(string $key): string;
    abstract protected static function applyModifier(string $modifier, $value);
    abstract protected static function arrDot($array, $prepend = ''): array;

}