<?php
namespace esperecyan\dictionary_php\parser;

class CatchfeelingParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $input
     * @param string|null $filename
     * @param string|null $title
     * @param (string|string[]|float|URLSearchParams)[][][] $jsonable
     * @param (string|string[])[] $metadata
     * @dataProvider dictionaryProvider
     */
    public function testParse(
        string $input,
        string $filename = null,
        string $title = null,
        array $jsonable = null,
        array $metadata = null
    ) {
        $parser = new CatchfeelingParser();
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        $dictionary = $parser->parse($temp, $filename, $title);
        
        $this->assertEquals($jsonable, $dictionary->getJsonable());
        $this->assertEquals($metadata, $dictionary->getMetadata());
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
                "たいよう//太陽
                ちきゅう 　\t// 地球
                カロン
                ",
                null,
                null,
                [
                    [
                        'text' => ['たいよう'],
                        'description' => [['lml' => '太陽', 'html' => "<p>太陽</p>\n"]],
                    ],
                    [
                        'text' => ['ちきゅう'],
                        'description' => [['lml' => '地球', 'html' => "<p>地球</p>\n"]],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
                [],
            ],
            [
                'たいよう//太陽
                ちきゅう    // 地球
                カロン
                ',
                '天体 [dummy].cfq',
                null,
                [
                    [
                        'text' => ['たいよう'],
                        'description' => [['lml' => '太陽', 'html' => "<p>太陽</p>\n"]],
                    ],
                    [
                        'text' => ['ちきゅう'],
                        'description' => [['lml' => '地球', 'html' => "<p>地球</p>\n"]],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
                ['@title' => '天体'],
            ],
            [
                'たいよう//太陽
                ちきゅう    // 地球
                カロン
                ',
                '天体 [dummy].cfq',
                '星 [dummy].cfq',
                [
                    [
                        'text' => ['たいよう'],
                        'description' => [['lml' => '太陽', 'html' => "<p>太陽</p>\n"]],
                    ],
                    [
                        'text' => ['ちきゅう'],
                        'description' => [['lml' => '地球', 'html' => "<p>地球</p>\n"]],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
                ['@title' => '星 [dummy].cfq'],
            ],
            [
                'ｶﾟ
                ｷﾟ
                ｸﾟ
                ｹﾟ
                ｺﾟ
                ',
                null,
                null,
                [
                    [
                        'text' => ['カ'],
                    ],
                    [
                        'text' => ['キ'],
                    ],
                    [
                        'text' => ['ク'],
                    ],
                    [
                        'text' => ['ケ'],
                    ],
                    [
                        'text' => ['コ'],
                    ],
                ],
                [],
            ],
        ];
    }
    
    /**
     * @param string $input
     * @expectedException \esperecyan\dictionary_php\exception\SyntaxException
     * @dataProvider invalidDictionaryProvider
     */
    public function testSyntaxException(string $input)
    {
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        $dictionary = (new CatchfeelingParser())->parse($temp);
    }
    
    public function invalidDictionaryProvider(): array
    {
        return [
            ['   // 天体
            たいよう//太陽
            ちきゅう    // 地球
            カロン
            '],
            ['// 天体
            たいよう//太陽
            ちきゅう    // 地球
            カロン
            '],
            ['　
            たいよう//太陽
            ちきゅう    // 地球
            カロン
            '],
            ['゜
             ゛
            '],
        ];
    }
}
