<?php
namespace esperecyan\dictionary_php\parser;

use Psr\Log\LogLevel;

class PictsenseParserTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    use \esperecyan\dictionary_php\PreprocessingTrait;
    
    /**
     * @param string $input
     * @param string|null $filename
     * @param string|null $title
     * @param (string|string[]|float|URLSearchParams)[][][] $jsonable
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
        $parser = new PictsenseParser();
        $parser->setLogger($this);
        $dictionary = $parser->parse($this->generateTempFileObject($this->stripIndents($input)), $filename, $title);
        
        $this->assertEquals($jsonable, $dictionary->getWords());
        $this->assertEquals($metadata, $dictionary->getMetadata());
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function dictionaryProvider(): array
    {
        return [
            [
                'たいよう
                ちきゅう
                かろん
                けれす
                はれーすいせい
                ',
                null,
                null,
                [
                    ['text' => ['たいよう']],
                    ['text' => ['ちきゅう']],
                    ['text' => ['かろん']],
                    ['text' => ['けれす']],
                    ['text' => ['はれーすいせい']],
                ],
                [],
            ],
            [
                '
                
                たいよう
                ちきゅう
                
                かろん
                
                けれす
                はれーすいせい
                ちきゅう
                
                ',
                null,
                null,
                [
                    ['text' => ['たいよう']],
                    ['text' => ['ちきゅう']],
                    ['text' => ['かろん']],
                    ['text' => ['けれす']],
                    ['text' => ['はれーすいせい']],
                ],
                [],
            ],
            [
                'たいよう
                ちきゅう
                かろん
                けれす
                はれーすいせい
                ',
                '天体.csv',
                null,
                [
                    ['text' => ['たいよう']],
                    ['text' => ['ちきゅう']],
                    ['text' => ['かろん']],
                    ['text' => ['けれす']],
                    ['text' => ['はれーすいせい']],
                ],
                ['@title' => '天体'],
            ],
            [
                'たいよう
                ちきゅう
                かろん
                けれす
                はれーすいせい
                ',
                '天体.csv',
                '天体.csv',
                [
                    ['text' => ['たいよう']],
                    ['text' => ['ちきゅう']],
                    ['text' => ['かろん']],
                    ['text' => ['けれす']],
                    ['text' => ['はれーすいせい']],
                ],
                ['@title' => '天体.csv'],
            ],
            [
                'っ
                ゐ
                ゑ
                ゎ
                を
                ヴ
                ー
                ',
                null,
                null,
                [
                    ['text' => ['っ']],
                    ['text' => ['ゐ']],
                    ['text' => ['ゑ']],
                    ['text' => ['ゎ']],
                    ['text' => ['を']],
                    ['text' => ['ゔ']],
                    ['text' => ['ー']],
                ],
                [],
            ],
            [
                str_repeat('あ', 32) . '
                い
                う
                え
                お
                ',
                null,
                null,
                [
                    ['text' => [str_repeat('あ', 32)]],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                [],
            ],
            [
                implode("\n", array_slice($this->generateHiraganaWords(), 0, 500)),
                null,
                null,
                array_map(function (string $word): array {
                    return ['text' => [$word]];
                }, array_slice($this->generateHiraganaWords(), 0, 500)),
                [],
            ],
            [
                implode("\n", array_map(function (string $word): string {
                    return $word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8'));
                }, array_slice($this->generateHiraganaWords(), 0, 500))),
                null,
                null,
                array_map(function (string $word): array {
                    return ['text' => [$word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8'))]];
                }, array_slice($this->generateHiraganaWords(), 0, 500)),
                [],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                str_repeat('〜', 30),
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                ['@title' => str_repeat('〜', 30)],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                str_repeat('〜', 31),
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                ['@title' => str_repeat('〜', 31)],
                [LogLevel::ERROR],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                str_repeat('𩸽', 15) . '.csv',
                null,
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                ['@title' => str_repeat('𩸽', 15)],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                str_repeat('𩸽', 16) . '.csv',
                null,
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                ['@title' => str_repeat('𩸽', 16)],
                [LogLevel::ERROR],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                str_repeat('𩸽', 16),
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                ['@title' => str_repeat('𩸽', 16)],
                [LogLevel::ERROR],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                ' ',
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                [],
                [LogLevel::ERROR],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                '　',
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                [],
                [LogLevel::ERROR],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                "\t",
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                [],
                [LogLevel::ERROR],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                " t e s t ",
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                ['@title' => 't e s t'],
                [],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                "\u{00A0}",
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                ['@title' => "\u{00A0}"],
            ],
            [
                'あ
                い
                う
                え
                お
                ',
                null,
                "\u{200c}",
                [
                    ['text' => ['あ']],
                    ['text' => ['い']],
                    ['text' => ['う']],
                    ['text' => ['え']],
                    ['text' => ['お']],
                ],
                ['@title' => "\u{200c}"],
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
        (new PictsenseParser())->parse($this->generateTempFileObject($this->stripIndents($input)));
    }
    
    public function invalidDictionaryProvider(): array
    {
        return [
            ['あ
            い
            う
            え
            ゔ
            '],
            ['あ
            い
            う
            え
            〜
            '],
            ['あ
            い
            う
            え
            ～
            '],
            ['あ
            い
            う
            え
            ゖ
            '],
            ['あ
            い
            う
            え
            ゕ
            '],
            ["あ
            い
            う
            え
            \u{1B001}
            "],
            ['あ
            い
            う
            え
            ～
            '],
            ['あ
            い
            　
            う
            え
            お
            '],
            ['あいうえお
            かきくけこ
            さしすせそ
            たちつてと
            '],
            [str_repeat('あ', 33) . '
            い
            う
            え
            お
            '],
            [implode("\n", array_slice($this->generateHiraganaWords(), 0, 501))],
            [implode("\n", array_map(function (string $word): string {
                return $word . str_repeat('あ', 10 - mb_strlen($word, 'UTF-8'));
            }, array_slice($this->generateHiraganaWords(), 0, 500))) . 'あ'],
        ];
    }
}
