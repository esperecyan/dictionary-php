<?php
namespace esperecyan\dictionary_php\parser;

use Psr\Log\LogLevel;

class CatchmParserTest extends \PHPUnit\Framework\TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    
    /**
     * @param string $input
     * @param string|null $filename
     * @param string|null $title
     * @param (string|string[]|float)[][][] $jsonable
     * @param (string|string[])[] $metadata
     * @param string[] $logLevels
     * @dataProvider dictionaryProvider
     */
    public function testParse(
        string $input,
        string $filename = null,
        string $title = null,
        array $jsonable = null,
        array $metadata = null,
        array $logLevels = []
    ) {
        $parser = new CatchmParser();
        $parser->setLogger($this);
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        $dictionary = $parser->parse($temp, $filename, $title);
        
        $this->assertEquals($jsonable, $dictionary->getWords());
        $this->assertEquals($metadata, $dictionary->getMetadata());
        $this->assertEquals($logLevels, $this->logLevels);
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
                        'description' => [['lml' => '安', 'html' => "<p>安</p>\n"]],
                    ],
                    [
                        'text' => ['い'],
                        'description' => [['lml' => '以', 'html' => "<p>以</p>\n"]],
                    ],
                    [
                        'text' => ['う'],
                        'description' => [['lml' => '宇', 'html' => "<p>宇</p>\n"]],
                    ],
                    [
                        'text' => [']非コメント'],
                    ],
                    [
                        'text' => ['え'],
                        'description' => [['lml' => '衣', 'html' => "<p>衣</p>\n"]],
                    ],
                    [
                        'text' => ['お'],
                        'description' => [['lml' => '於', 'html' => "<p>於</p>\n"]],
                    ],
                    [
                        'text' => ['か'],
                        'description' => [['lml' => '加 ]', 'html' => "<p>加 ]</p>\n"]],
                    ],
                    [
                        'text' => ['き'],
                        'description' => [['lml' => '[幾]', 'html' => "<p>[幾]</p>\n"]],
                    ],
                    [
                        'text' => ['く'],
                        'description' => [['lml' => '久', 'html' => "<p>久</p>\n"]],
                    ],
                    [
                        'text' => ['け'],
                        'description' => [['lml' => '; 計', 'html' => "<p>; 計</p>\n"]],
                    ],
                    [
                        'text' => ['こ'],
                        'answer' => ['こ', 'ご'],
                    ],
                ],
                ['@title' => '天体 [dummy]'],
            ],
            [
                '゜
                ゛
                テスト
                ',
                null,
                null,
                [
                    [
                        'text' => ['テスト'],
                    ],
                ],
                [],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
        ];
    }
    
    /**
     * @param string $input
     * @dataProvider invalidDictionaryProvider
     */
    public function testSyntaxException(string $input)
    {
        $temp = new \SplTempFileObject();
        $temp->fwrite(preg_replace('/\\n */u', "\r\n", $input));
        $this->expectException(\esperecyan\dictionary_php\exception\SyntaxException::class);
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
