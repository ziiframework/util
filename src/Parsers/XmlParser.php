<?php

declare(strict_types=1);

namespace Zii\Util\Parsers;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use yii\base\BaseObject;
use yii\web\RequestParserInterface;

final class XmlParser extends BaseObject implements RequestParserInterface
{
    /**
     * {@inheritdoc}
     */
    public function parse($rawBody, $contentType): array
    {
        $xml_encoder = new XmlEncoder([XmlEncoder::ROOT_NODE_NAME => 'xml', XmlEncoder::ENCODING => 'UTF-8']);

        $serializer = (new Serializer([], [$xml_encoder]));

        return $serializer->decode($rawBody,'xml');
    }
}
