<?php

namespace Aybarsm\Replacer\Service;

use Aybarsm\Replacer\Service\Abstracts\AbstractReplacer;
class Replacer extends AbstractReplacer
{
    protected static string $keyRulePattern;
    protected static string $keyRuleReplace;
    protected static string $leftDelimiter;
    protected static string $leftPattern;
    protected static string $rightDelimiter;
    protected static string $rightPattern;
    protected static string $modifierDelimiter;
    protected static string $modifierPattern;
    protected static array $classMap;
    protected static array $replacements;

    public function __construct(
        array $replacements = [],
        array $classMap = [],
        string $leftDelimiter = '{{',
        string $rightDelimiter = '}}',
        string $modifierDelimiter = '|',
        string $keyRulePattern = '/[^a-zA-Z0-9]/',
        string $keyRuleReplace = '_'
    )
    {
        $this->addReplacements($replacements);
        $this->addClasses($classMap);
        $this->setDelimiters($leftDelimiter, $rightDelimiter, $modifierDelimiter);
        $this->setKeyRule($keyRulePattern, $keyRuleReplace);
    }

    public function getLeftDelimiter(): string
    {
        return static::$leftPattern;
    }

    public function getRightDelimiter(): string
    {
        return static::$rightPattern;
    }

    public function getModifierDelimiter(): string
    {
        return static::$modifierPattern;
    }

    public function getLeftPattern(): string
    {
        return static::$leftPattern;
    }

    public function getRightPattern(): string
    {
        return static::$rightPattern;
    }

    public function getModifierPattern(): string
    {
        return static::$modifierPattern;
    }

    public function getReplacements(): array
    {
        return static::$replacements;
    }

    public function addReplacements(array $replacements)
    {
        static::$replacements = array_merge((static::$replacements ?? []), static::standardiseReplacementSource($replacements));

        return $this;
    }

    public function addClasses(array $classes)
    {
        static::$classMap = array_merge(static::$classMap ?? [], $classes);

        return $this;
    }
    public function setDelimiters(?string $left, ?string $right, ?string $modifier)
    {
        if (! empty($left)){
            static::$leftDelimiter = trim($left);
            static::$leftPattern = '/\\' . implode('\\', str_split(static::$leftDelimiter)) . '\s*';
        }

        if (! empty($right)){
            static::$rightDelimiter = trim($right);
            static::$rightPattern = '\s*\\' . implode('\\', str_split(static::$rightDelimiter)) . '/';
        }

        if (! empty($modifier)){
            static::$modifierDelimiter = trim($modifier);
            static::$modifierPattern = static::$leftPattern . '([^|}]+)' . '\\' . implode('\\', str_split(static::$modifierDelimiter)) . '\s*([^}]+)' . static::$rightPattern;
        }

        return $this;
    }
    public function setKeyRule(string $pattern = '', string $replace = '')
    {
        static::$keyRulePattern = $pattern;
        static::$keyRuleReplace = $replace;

        return $this;
    }

    public function apply($subject, array $withReplacements = [])
    {
        $replacements = static::prepareReplacements(array_merge(static::$replacements, static::standardiseReplacementSource($withReplacements)));

        // Let the massive part taken care by json_encode
        $content = preg_replace($replacements['regex'], array_values($replacements['combined']), json_encode(is_array($subject) ? $subject : [$subject]));
//        dump($content);
//        dump(json_decode($content));

        preg_match_all(static::$modifierPattern, $content, $matches);
//        dump($matches);
        if (! empty($matches[0])){
            $done = [];
            foreach($matches[0] as $targetKey => $target){
                // No need to repeat the same process
                if (in_array($target, $done)){
                    continue;
                }

                // If there is no source, no value at all
                $source = trim($matches[2][$targetKey]);
                if (! array_key_exists($source, $replacements['combined'])){
                    continue;
                }

                $newValue = $replacements['combined'][$source];

                // Identify the modifiers and apply them starting from the end (inner modifiers first)
                $modifiers = array_reverse(preg_split('/\s*,\s*/', $matches[1][$targetKey], -1, PREG_SPLIT_NO_EMPTY));

                foreach($modifiers as $modifier){
                    $newValue = static::applyModifier($modifier, $newValue);
                }

                // If the new value is replaceable in json, treat it accordingly by removing double quotes
                // Avoid replacing a key
                if (is_bool($newValue) || is_int($newValue) || is_float($newValue)){
                    $content = str_replace(":\"{$target}\"", ':' . $newValue, $content);
                }
                $content = str_replace($target , $newValue, $content);

                $done[] = $target;
            }
        }

//        dump($content);

        $ready = json_decode($content, true);

        return is_array($subject) ? $ready : $ready[array_key_first($ready)];
    }

    protected static function applyModifier(string $modifier, $value)
    {
        if (strpos($modifier, '::') !== false && ! is_null($modifierClass = (static::$classMap[explode('::', $modifier)[0]] ?? null))){
            $method = array_reverse(explode('::', $modifier, 2))[0];
            if (method_exists($modifierClass, $method)){
                $value = $modifierClass::$method($value);
            }
        }elseif (function_exists($modifier)){
            $value = $modifier($value);
        }

        return $value;
    }

    protected static function prepareReplacements(array $replacements): array
    {
        $plain = array_map(fn ($val) => static::prepareReplacementKey($val), array_keys($replacements));
        $regex = array_map(fn ($val) => static::prepareReplacementKey($val, 'regularDelimiter'), $plain);
        $modifiers = array_map(fn ($val) => static::prepareReplacementKey($val, 'modifierDelimiter'), $plain);

        return ['plain' => $plain, 'regex' => $regex, 'modifiers' => $modifiers, 'combined' => array_combine($plain, array_values($replacements))];
    }

    protected static function prepareReplacementKey(string $key, string $process = 'plain'): string
    {
        if ($process == 'plain'){
            $key = strtoupper(empty(static::$keyRulePattern) ? $key : preg_replace(static::$keyRulePattern, static::$keyRuleReplace, $key));
        }elseif ($process == 'regularDelimiter'){
            $key = static::$leftPattern . $key . static::$rightPattern;
        }elseif ($process == 'modifierDelimiter'){
            $key = static::$leftPattern . static::$modifierPattern . $key . static::$rightPattern;
        }

        return $key;
    }

    protected static function standardiseReplacementSource(array $data): array
    {
        $data = static::arrDot($data);
        $keys = array_map(fn ($val) => static::standardiseReplacementSourceKey($val), array_keys($data));

        return array_combine($keys, array_values($data));
    }

    protected static function standardiseReplacementSourceKey(string $key): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '.', $key), '. '));
    }

    protected static function arrDot($array, $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                $results = array_merge($results, static::arrDot($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }
}