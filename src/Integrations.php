<?php

declare(strict_types=1);

namespace Zii\Util;

use ErrorException;
use Stichoza\GoogleTranslate\GoogleTranslate;

class Integrations
{
    public static function GoogleTranslate(string $str, string $from, string $to): ?string
    {
        if (pf_is_string_empty($str)) {
            return null;
        }

        $translator = new GoogleTranslate($to, $from, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6,es;q=0.5,pl;q=0.4,fr;q=0.3,hu;q=0.2,mt;q=0.1,ar;q=0.1,zh-TW;q=0.1,ru;q=0.1',
                'Connection' => 'keep-alive',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36 Edg/90.0.818.56',
            ],
        ]);

        $translator->setUrl('https://translate.google.cn/translate_a/single');

        try {
            $result = $translator->translate($str);
        } catch (ErrorException $e) {
            return null;
        }

        if (pf_is_string_filled($result)) {
            return $result;
        }

        return null;
    }
}
