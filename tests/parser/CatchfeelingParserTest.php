<?php
namespace esperecyan\dictionary_api\parser;

class CatchfeelingParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $input
     * @param string|null $filename
     * @param string|null $title
     * @param string[][][] $output
     * @dataProvider dictionaryProvider
     */
    public function testParse(string $input, string $filename = null, string $title = null, array $output = null)
    {
        $parser = new CatchfeelingParser();
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        if (is_null($output)) {
            $this->setExpectedException('\esperecyan\dictionary_api\exception\SyntaxException');
        }
        $this->assertSame($output, array_map(function (\esperecyan\dictionary_api\internal\Word $word): array {
            return $word->getFieldsAsMultiDimensionalArray();
        }, $parser->parse($temp, $filename, $title)->getWords()));
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
                        'description' => ['太陽'],
                    ],
                    [
                        'text' => ['ちきゅう'],
                        'description' => ['地球'],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
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
                        'description' => ['太陽'],
                        '@title' => ['天体'],
                    ],
                    [
                        'text' => ['ちきゅう'],
                        'description' => ['地球'],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
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
                        'description' => ['太陽'],
                        '@title' => ['星 [dummy].cfq'],
                    ],
                    [
                        'text' => ['ちきゅう'],
                        'description' => ['地球'],
                    ],
                    [
                        'text' => ['カロン'],
                    ],
                ],
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
            ],
        ];
    }
    
    /**
     * @param string $input
     * @expectedException \esperecyan\dictionary_api\exception\SyntaxException
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
