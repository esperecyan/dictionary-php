<?php
namespace esperecyan\dictionary_api\validator;

use esperecyan\dictionary_api\exception\SyntaxException;

/**
 * SVG画像の矯正。
 */
class SVGValidator extends AbstractFieldValidator
{
    /** @var string SVG名前空間。 */
    const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
    
    /** @var string XLink名前空間。 */
    const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    
    /** @var string 許可しないノード。XPath。 */
    const BLACKLIST = '//processing-instruction("xml-stylesheet")
        | //*[namespace-uri()!="' . self::SVG_NAMESPACE . '"
            or starts-with(local-name(), "animate") or local-name()="set" or local-name()="script"]
        | //@*[namespace-uri()!="" and namespace-uri()!="' . self::XLINK_NAMESPACE . '"
            or starts-with(local-name(),"on")
            or namespace-uri()="' . self::XLINK_NAMESPACE . '" and local-name()="href"
                and not(starts-with(string(), "#"))]';
    
    public function correct(string $input): string
    {
        if (!mb_check_encoding($input, 'utf-8')) {
            throw new SyntaxException(_('SVGファイルの符号化方式 (文字コード) は utf-8 でなければなりません。'));
        }
        
        $parser = xml_parser_create_ns();
        $isValid = xml_parse_into_struct($parser, $input, $nodes);
        xml_parser_free($parser);
        if (!$isValid) {
            throw new SyntaxException(_('整形式になっていません。'));
        }
        
        $document = new \DOMDocument('1.0', 'utf-8');
        $document->loadXML($input);

        // ノードの削除
        $root = $document->documentElement;
        foreach ((new \DOMXPath($document))->query(self::BLACKLIST) as $node) {
            if ($node === $root) {
                throw new SyntaxException(_('ルート要素がSVG名前空間に属していません。'));
            }
            if ($this->logger) {
                $this->logger->error(sprintf(_('SVG中にノード %s を含めることはできません。'), $node->nodeName));
            }
            if ($node->nodeType === XML_ATTRIBUTE_NODE) {
                $node->ownerElement->removeAttributeNode($node);
            } elseif ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        return $document->saveXML();
    }
}
