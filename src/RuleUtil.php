<?php

declare(strict_types=1);

namespace Zii\Util;

use yii\helpers\Json;
use yii\validators\DateValidator;

use DiDom\Document as DiDomDocument;
use Sabberworm\CSS\OutputFormat as CssOutputFormat;
use Sabberworm\CSS\Rule\Rule as CssRule;
use Sabberworm\CSS\Parser as CssParser;
use Sabberworm\CSS\RuleSet\RuleSet as CssRuleSet;

class RuleUtil
{
    public static function strOrNull($value): ?string
    {
        if (is_numeric($value) && !is_string($value)) {
            $value = (string)$value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public static function strJsonFormat($value): ?string
    {
        if (is_array($value)) {
            return Json::encode($value);
        }
        if (is_string($value)) {
            $json = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return Json::encode($json);
            }
        }

        return null;
    }

    public static function strOrNullNoTrim($value): ?string
    {
        if (is_numeric($value) && !is_string($value)) {
            $value = (string)$value;
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
    }

    public static function intOrNull($value): ?int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }

    public static function boolOrNull($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (bool)($value * 1);
        }

        return null;
    }

    public static function dateOrNull($value, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            $validator = new DateValidator(['format' => "php:$format"]);
            if ($validator->validate($value)) {
                return $value;
            }

            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date($format, $timestamp);
            }
        }

        return null;
    }

    public static function htmlOrNull($value): ?string
    {
        if (!is_string($value) || empty(trim($value))) {
            return null;
        }

        $DDD = new DiDomDocument($value);
        $DOMElements = $DDD->find('*');

        foreach ($DOMElements as $DOMElement) {
            $isIMG = $DOMElement->tag === 'img';
            $isP = $DOMElement->tag === 'p';

            $DOMElement_attributes = $DOMElement->attributes();
            if (is_array($DOMElement_attributes) && !empty($DOMElement_attributes)) {
                foreach ($DOMElement_attributes as $DOMElement_attribute__k => $DOMElement_attribute__v) {
                    if (is_string($DOMElement_attribute__v) && trim($DOMElement_attribute__v) === '') {
                        $DOMElement->removeAttribute($DOMElement_attribute__k);
                    } else if ($DOMElement_attribute__k === 'class' || $DOMElement_attribute__k === 'id' || mb_strpos($DOMElement_attribute__k, 'data-') === 0 || mb_strpos($DOMElement_attribute__k, 'aria-') === 0) {
                        $DOMElement->removeAttribute($DOMElement_attribute__k);
                    }
                }
            }

            $DOMElement_style = $DOMElement->getAttribute('style');

            if (!is_string($DOMElement_style) || trim($DOMElement_style) === '') {
                $DOMElement->removeAttribute('style');
                if ($isIMG) {
                    $DOMElement->setAttribute('style', 'max-width:100%;');
                } else if ($isP) {
                    $DOMElement->setAttribute('style', 'word-break:break-all;');
                }
            } else {
                $CssParserContext = (new CssParser($DOMElement->tag . '{' . $DOMElement_style . '}'))->parse();

                foreach($CssParserContext->getAllRuleSets() as $each_RuleSet) {
                    if (!($each_RuleSet instanceof CssRuleSet)) {
                        continue;
                    }

                    if ($isIMG) {
                        $each_RuleSet->removeRule('max-width');

                        $CssRule__max_width = new CssRule('max-width');
                        $CssRule__max_width->setValue('100%');
                        $each_RuleSet->addRule($CssRule__max_width);
                    } else if ($isP) {
                        $each_RuleSet->removeRule('word-break');

                        $CssRule__word_break = new CssRule('word-break');
                        $CssRule__word_break->setValue('break-all');
                        $each_RuleSet->addRule($CssRule__word_break);
                    }

                    $each_RuleSet->removeRule('font-family');
                    $each_RuleSet->removeRule('caret-color');

                    foreach ($each_RuleSet->getRules() as $rule) {
                        // [!important] makes no sense for inline style
                        if ($rule->getIsImportant()) {
                            $rule->setIsImportant(false);
                        }
                        if ($rule->getRule() === 'white-space') {
                            $white_space__value = $rule->getValue();
                            if ($white_space__value === 'normal') {
                                $each_RuleSet->removeRule('white-space');
                            }
                        }
                    }
                }

                $New_style = $CssParserContext->render(CssOutputFormat::createCompact());
                $New_style = mb_substr($New_style, 1 + mb_strlen($DOMElement->tag), -1, 'UTF-8');
                $DOMElement->setAttribute('style', $New_style);
            }
        }

        return $DDD->find('body')[0]->innerHtml();
    }
}
