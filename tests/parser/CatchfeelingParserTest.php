<?php
namespace esperecyan\dictionary_php\parser;

use Psr\Log\LogLevel;

class CatchfeelingParserTest extends \PHPUnit\Framework\TestCase implements \Psr\Log\LoggerInterface
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
        $parser = new CatchfeelingParser();
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
            [
                '゜
                ゛
                テスト    // 「UTF-8において、表意空白 U+3000 (0xE3 0x80 0x80) に含まれるバイトが除去されないことをテスト」
                / /
                ／てすと／
                ',
                null,
                null,
                [
                    [
                        'text' => ['テスト'],
                        'description' => [[
                            'lml' => '「UTF-8において、表意空白 U+3000 (0xE3 0x80 0x80) に含まれるバイトが除去されないことをテスト」',
                            'html' => "<p>「UTF-8において、表意空白 U+3000 (0xE3 0x80 0x80) に含まれるバイトが除去されないことをテスト」</p>\n",
                        ]],
                    ],
                    [
                        'text' => ['てすと'],
                    ],
                ],
                [],
                [LogLevel::ERROR, LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                "すいへいたぶ    // 「\t」
                ",
                null,
                null,
                [
                    [
                        'text' => ['すいへいたぶ'],
                        'description' => [['lml' => '「    」', 'html' => "<p>「    」</p>\n"]],
                    ],
                ],
                [],
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
        $dictionary = (new CatchfeelingParser())->parse($temp);
    }
    
    public function invalidDictionaryProvider(): array
    {
        return [
            ['  // 天体
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
            ['てすと
            　
            '],
        ];
    }
}
