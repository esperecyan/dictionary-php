<?php
namespace esperecyan\dictionary_php\parser;

class CatchmParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $input
     * @param string|null $filename
     * @param string|null $title
     * @param string[][][] $output
     * @dataProvider dictionaryProvider
     */
    public function testParse(
        string $input,
        string $filename = null,
        string $title = null,
        array $output = null
    ) {
        $parser = new CatchmParser();
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        $this->assertSame($output, array_map(function (\esperecyan\dictionary_php\internal\Word $word): array {
            return $word->getFieldsAsMultiDimensionalArray();
        }, $parser->parse($temp, $filename, $title)->getWords()));
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
                "あ/安
                い  [ 以
                う　　; 宇
                /コメント
                [コメント
                ;コメント
                ]非コメント
                え\t\t// 衣
                お  [ 於 ]
                
                か  [ 加 ] 
                き ;[幾]
                く ;; 久
                け ; ; 計
                こ,ご,",
                '天体 [dummy].dat',
                null,
                [
                    [
                        'text' => ['あ'],
                        'description' => ['安'],
                        '@title' => ['天体 [dummy]'],
                    ],
                    [
                        'text' => ['い'],
                        'description' => ['以'],
                    ],
                    [
                        'text' => ['う'],
                        'description' => ['宇'],
                    ],
                    [
                        'text' => [']非コメント'],
                    ],
                    [
                        'text' => ['え'],
                        'description' => ['衣'],
                    ],
                    [
                        'text' => ['お'],
                        'description' => ['於'],
                    ],
                    [
                        'text' => ['か'],
                        'description' => ['加 ]'],
                    ],
                    [
                        'text' => ['き'],
                        'description' => ['[幾]'],
                    ],
                    [
                        'text' => ['く'],
                        'description' => ['久'],
                    ],
                    [
                        'text' => ['け'],
                        'description' => ['; 計'],
                    ],
                    [
                        'text' => ['こ'],
                        'answer' => ['こ', 'ご'],
                    ],
                ],
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
        (new CatchmParser())->parse($temp);
    }
    
    public function invalidDictionaryProvider(): array
    {
        return [
            [
                '    
                あ // ↑スペースのみで構成された行
                い
                う
                ',
            ],
            [
                '    // コメントが付いたスペースのみで構成された行
                あ
                い
                う
                ',
            ],
        ];
    }
}
