<?php

declare(strict_types=1);

namespace TermeGest\Type;

use const FILTER_FLAG_ALLOW_FRACTION;
use const FILTER_FLAG_ALLOW_SCIENTIFIC;
use const FILTER_FLAG_ALLOW_THOUSAND;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;
use const LIBXML_COMPACT;
use const LIBXML_HTML_NOIMPLIED;
use const LIBXML_NOBLANKS;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMNamedNodeMap;
use DOMXPath;
use SimpleXMLElement;
use stdClass;
use Throwable;

/**
 * Class ComplexTypeConverter
 * Converts between PHP object and SOAP TermeGest complexType objects.
 */
class AnyXML
{
    protected int $xmlOptions = LIBXML_HTML_NOIMPLIED | LIBXML_NOBLANKS | LIBXML_COMPACT;

    /**
     * @param self|string $body
     */
    public function __construct(protected $body) {}

    /**
     * @return stdClass[]
     */
    public function convertXmlToPhpObject(): array
    {
        $schema = $this->extractSchema($this->body);
        $content = $this->extractContent($this->body);

        return $this->composeObject($schema, $content);
    }

    protected function extractSchema(string $xml): string
    {
        $endSchemaMarker = '</xs:schema>';

        $endSchema = mb_stripos($xml, $endSchemaMarker);

        return mb_substr($xml, 0, $endSchema + mb_strlen($endSchemaMarker));
    }

    protected function extractContent(string $xml): string
    {
        $endSchemaMarker = '</xs:schema>';

        $endSchema = mb_stripos($xml, $endSchemaMarker);

        return mb_substr($xml, $endSchema + mb_strlen($endSchemaMarker));
    }

    /**
     * @return stdClass[]
     */
    protected function composeObject(string $schema, string $content): array
    {
        $entity = $this->extractEntityFromSchema($schema);
        $types = $this->convertSchemaToTypesArray($schema);
        $body = $this->convertContentToArray($content, $entity);

        return $this->parseContentArray($body, $types, $entity);
    }

    protected function extractEntityFromSchema(string $schema): string
    {
        $domDocument = new DOMDocument();
        $domDocument->loadXML($schema, $this->xmlOptions);

        $domxPath = new DOMXPath($domDocument);

        $entity = $domxPath->query('//xs:element//xs:complexType//xs:element');

        if ($entity->length === 0) {
            return '';
        }

        /** @var DOMNamedNodeMap $attrs */
        $attrs = $entity->item(0)->attributes;

        if ($attrs->length !== 1) {
            return '';
        }

        $value = $attrs->getNamedItem('name');

        if ($value === null) {
            return '';
        }

        return $value->nodeValue;
    }

    /**
     * @return string[]
     */
    protected function convertSchemaToTypesArray(string $schema): array
    {
        $domDocument = new DOMDocument();
        $domDocument->loadXML($schema, $this->xmlOptions);

        $domxPath = new DOMXPath($domDocument);

        $sequence = $domxPath->query('//xs:sequence');

        if ($sequence->length === 0) {
            return [];
        }

        $types = [];

        foreach ($sequence->item(0)->childNodes as $item) {
            /* @var DOMElement $item */

            /** @var DOMNamedNodeMap $attrs */
            $attrs = $item->attributes;

            if ($attrs->length === 0) {
                continue;
            }

            $key = $attrs->getNamedItem('name');
            $value = $attrs->getNamedItem('type');

            if ($key === null) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $types[$key->nodeValue] = str_ireplace('xs:', '', $value->nodeValue);
        }

        return $types;
    }

    /**
     * @return SimpleXMLElement[]
     */
    protected function convertContentToArray(string $content, string $entity): array
    {
        $body = simplexml_load_string($content, 'SimpleXMLElement', $this->xmlOptions);

        if ($body === false) {
            return [];
        }

        $items = $body->xpath('//'.$entity);

        if (empty($items)) {
            return [];
        }

        return $items;
    }

    /**
     * @param SimpleXMLElement[] $body
     * @param string[] $types
     * @return stdClass[]
     */
    protected function parseContentArray(array $body, array $types, string $entity): array
    {
        $array = [];

        foreach ($body as $item) {
            $obj = $this->parseContentItem($item, $types, $entity);

            if (empty(array_filter((array) $obj))) {
                continue;
            }

            $array[] = $obj;
        }

        return $array;
    }

    /**
     * @param string[] $types
     */
    protected function parseContentItem(SimpleXMLElement $item, array $types, string $entity): stdClass
    {
        $class = $this->mapEntityToClass($entity);

        $object = new $class();

        foreach ($item->children() as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (! property_exists($object, $key)) {
                continue;
            }

            if ($value->count() > 0) {
                $object->{$key} = $this->parseContentItem($value, $types, $entity);

                continue;
            }

            $object->{$key} = $this->transformValueFromTypes($value, $types[$key] ?? '');
        }

        return $object;
    }

    protected function mapEntityToClass(string $entity): string
    {
        $entity = str_replace(' ', '', ucwords(str_replace('_', ' ', $entity)));

        if (class_exists('\\TermeGest\\Type\\'.$entity)) {
            return '\\TermeGest\\Type\\'.$entity;
        }

        return 'stdClass';
    }

    /**
     * @return bool|float|int|string
     */
    protected function transformValueFromTypes(SimpleXMLElement $value, string $type)
    {
        switch ($type) {
            default:
            case 'string':
                $ret = sanitize_text_field(wp_unslash($value->__toString()));
                break;
            case 'int':
                $ret = (int) filter_var($value->__toString(), FILTER_VALIDATE_INT);
                break;
            case 'float':
                $ret = (float) filter_var($value->__toString(), FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND | FILTER_FLAG_ALLOW_SCIENTIFIC | FILTER_FLAG_ALLOW_FRACTION);
                $ret = (float) number_format($ret, 2, '.', '');
                break;
            case 'bool':
                $ret = filter_var($value->__toString(), FILTER_VALIDATE_BOOLEAN);
                break;
            case 'dateTime':
                try {
                    $dateTimeImmutable = new DateTimeImmutable($value->__toString());
                    $dateTimeImmutable->setTimezone(new DateTimeZone(date_default_timezone_get()));
                    $ret = $dateTimeImmutable->format('Y-m-d H:i:s');
                } catch (Throwable) {
                    $ret = '';
                }

                break;
        }

        if ($type !== 'bool' && $ret === false) {
            return '';
        }

        return $ret;
    }
}
