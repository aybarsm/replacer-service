<?php

namespace Aybarsm\Replacer\Service\Contracts;

interface ReplacerInterface
{
    public function __construct(
        array $replacements,
        array $classMap,
        string $leftDelimiter,
        string $rightDelimiter,
        string $modifierDelimiter,
        string $keyRulePattern,
        string $keyRuleReplace
    );

    public function getLeftDelimiter(): string;
    public function getRightDelimiter(): string;
    public function getModifierDelimiter(): string;
    public function getLeftPattern(): string;
    public function getRightPattern(): string;
    public function getModifierPattern(): string;
    public function getReplacements(): array;
    public function addReplacements(array $replacements);
    public function addClasses(array $classes);
    public function setDelimiters(?string $left, ?string $right, ?string $modifier);
    public function setKeyRule(string $pattern = '', string $replace = '');
    public function apply($subject, array $withReplacements = []);
}