<?php
namespace esperecyan\dictionary_php\validator;

use Psr\Log\LogLevel;

class SVGValidatorTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    
    /**
     * XML文書のインデントを整えます。
     * @param string $xml
     * @return string
     */
    public function formatXML(string $xml): string
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);
        $document->formatOutput = true;
        foreach ((new \DOMXPath($document))->query('//text()[normalize-space()=""]') as $node) {
            $node->parentNode->removeChild($node);
        }
        return $document->saveXML();
    }
    
    /**
     * @param string $input
     * @param string $output
     * @param string[] $logLevels
     * @dataProvider svgProvider
     */
    public function testCorrect(string $input, string $output, array $logLevels = [])
    {
        $validator = new SVGValidator();
        $validator->setLogger($this);
        $this->assertEquals($this->formatXML($output), $this->formatXML($validator->correct($input)));
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function svgProvider(): array
    {
        return [
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" />
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" />
                </svg>',
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <foreignObject x="100" width="100" height="100">
                        <p xmlns="http://www.w3.org/1999/xhtml">段落</p>
                    </foreignObject>
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <foreignObject x="100" width="100" height="100" />
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <script>window.alert("警告");</script>
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100">
                        <animate attributeType="CSS" attributeName="opacity" from="1" to="0" dur="5s" />
                    </rect>
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" />
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100">
                        <set attributeName="fill" to="transparent" begin="1s" dur="2s" />
                    </rect>
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" />
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100">
                        <animateTransform attributeName="transform" attributeType="XML" from="0" to="100" dur="5s" />
                    </rect>
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" />
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <defs>
                        <g id="test">
                            <rect width="100" height="100" />
                        </g>
                    </defs>
                    <a xlink:href="https://example.com/">
                        <use xlink:href="#test" />
                    </a>
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <defs>
                        <g id="test">
                            <rect width="100" height="100" />
                        </g>
                    </defs>
                    <a>
                        <use xlink:href="#test" />
                    </a>
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg" onload="alert(\'警告\');">
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg" xml:base="https://example.com/">
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg" xml:base="https://example.com/">
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                </svg>',
                [LogLevel::ERROR],
            ],
            [
                '<?xml version="1.0" ?>
                <?xml-stylesheet href="data:text/css,rect%7Bfill:green;%7D" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" />
                </svg>',
                '<?xml version="1.0" ?>
                <svg xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" />
                </svg>',
                [LogLevel::ERROR],
            ],
        ];
    }
    
    /**
     * @param string $input
     * @expectedException \esperecyan\dictionary_php\exception\SyntaxException
     * @dataProvider invalidSVGProvider
     */
    public function testSyntaxException(string $input)
    {
        (new SVGValidator())->correct($input);
    }
    
    public function invalidSVGProvider(): array
    {
        return [
            [mb_convert_encoding('<?xml version="1.0" ?>
            <svg xmlns="http://www.w3.org/2000/svg">
                <!-- 符号化方式がUTF-8でない。 -->
                <rect width="100" height="100" />
            </svg>', 'UTF-16', 'UTF-8')
            ],
            ['<?xml version="1.0" ?>
            <svg xmlns="http://www.w3.org/2000/svg">
                <rect width="100" height="100" xlink:href="整形式になっていない。" />
            </svg>'
            ],
            ['<?xml version="1.0" ?>
            <svg>
                ルート要素がSVG名前空間に属していない。
            </svg>'
            ],
        ];
    }
}
