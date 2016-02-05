<?php
namespace esperecyan\dictionary_api\internal;

use Psr\Log\LogLevel;

class DictionaryTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_api\LogLevelLoggerTrait;
    
    public function testGetWords()
    {
        $words = [new Word(), new Word(), new Word(), new Word()];
        $dictionary = new Dictionary();
        (function ($words) {
            $this->words = $words;
        })->call($dictionary, $words);
        $this->assertEquals($words, $dictionary->getWords());
        
        $this->assertEquals([], (new Dictionary())->getWords());
    }
    
    public function testAddWord()
    {
        $multiDimensionalArrays = [
            [
                'text' => ['テスト'],
            ],
            [
                'text' => ['テスト'],
                '@title' => ['辞書名'],
            ],
        ];
        
        $dictionary = new Dictionary();
        foreach ($multiDimensionalArrays as $multiDimensionalArray) {
            $word = new Word($multiDimensionalArrays);
            $word->setFieldsAsMultiDimensionalArray($multiDimensionalArray);
            $dictionary->addWord($word);
        }
        
        $this->assertEquals($multiDimensionalArrays, array_map(function (Word $word): array {
            return $word->getFieldsAsMultiDimensionalArray();
        }, $dictionary->getWords()));
    }

    /**
     * @param string[][][] $input
     * @param string[][][] $output
     * @param string[] $logLevels
     * @dataProvider multiDimensionalArraysProvider
     */
    public function testAddWordAsMultiDimensionalArray(array $input, array $output, array $logLevels = [])
    {
        $dictionary = new Dictionary();
        $dictionary->setLogger($this);
        foreach ($input as $multiDimensionalArray) {
            $dictionary->addWordAsMultiDimensionalArray($multiDimensionalArray);
        }
        
        $this->assertEquals($output, array_map(function (Word $word): array {
            return $word->getFieldsAsMultiDimensionalArray();
        }, $dictionary->getWords()));
        $this->assertEquals($logLevels, $this->logLevels);
    }

    public function multiDimensionalArraysProvider(): array
    {
        return [
            [
                [
                    [
                        'text' => ['りんご'],
                    ],
                    [
                        'text' => ['みかん'],
                    ],
                ],
                [
                    [
                        'text' => ['りんご'],
                    ],
                    [
                        'text' => ['みかん'],
                    ],
                ],
            ],
            [
                [
                    [
                        'text' => ['ﾘﾝｺﾞ'],
                    ],
                    [
                        'text' => ['ﾐｶﾝ'],
                    ],
                ],
                [
                    [
                        'text' => ['リンゴ'],
                    ],
                    [
                        'text' => ['ミカン'],
                    ],
                ],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                [
                    [
                        'text' => ['りんご'],
                        '@title' => ['食べ物'],
                    ],
                    [
                        'text' => ['みかん'],
                    ],
                ],
                [
                    [
                        'text' => ['りんご'],
                        '@title' => ['食べ物'],
                    ],
                    [
                        'text' => ['みかん'],
                    ],
                ],
            ],
            [
                [
                    [
                        'text' => ['りんご'],
                    ],
                    [
                        'text' => ['みかん'],
                        '@title' => ['食べ物'],
                    ],
                ],
                [
                    [
                        'text' => ['りんご'],
                    ],
                    [
                        'text' => ['みかん'],
                    ],
                ],
                [LogLevel::ERROR],
            ],
        ];
    }

    /**
     * @param string[][]|null $fieldsAsMultiDimensionalArray
     * @param string $title
     * @dataProvider titleProvider
     */
    public function testGetTitle(array $fieldsAsMultiDimensionalArray = null, string $title = '')
    {
        $dictionary = new Dictionary();
        if ($fieldsAsMultiDimensionalArray) {
            $dictionary->addWordAsMultiDimensionalArray($fieldsAsMultiDimensionalArray);
        }
        $this->assertSame($title, $dictionary->getTitle());
    }
    
    public function titleProvider(): array
    {
        return [
            [
                null,
                '',
            ],
            [
                [
                    'text' => ['テスト'],
                ],
                '',
            ],
            [
                [
                    'text' => ['テスト'],
                    '@title' => ['辞書名'],
                ],
                '辞書名',
            ],
        ];
    }
    

    /**
     * @param string[][] $input
     * @param string[][] $metaFieldsAsMultiDimensionalArray
     * @param string[][] $output
     * @dataProvider metaFieldsProvider
     */
    public function testSetMetaFields(array $input, array $metaFieldsAsMultiDimensionalArray, array $output)
    {
        $dictionary = new Dictionary();
        $dictionary->addWordAsMultiDimensionalArray($input);
        $dictionary->setMetaFields($metaFieldsAsMultiDimensionalArray);
        $this->assertEquals([$output], array_map(function (Word $word): array {
            return $word->getFieldsAsMultiDimensionalArray();
        }, $dictionary->getWords()));
    }
    
    public function metaFieldsProvider(): array
    {
        return [
            [
                [
                    'text' => ['テスト'],
                ],
                [
                    '@title' => ['辞書名'],
                ],
                [
                    'text' => ['テスト'],
                    '@title' => ['辞書名'],
                ],
            ],
            [
                [
                    'text' => ['テスト'],
                    '@title' => ['既存の辞書名'],
                ],
                [
                    '@title' => ['辞書名'],
                    '@summary' => ['説明'],
                ],
                [
                    'text' => ['テスト'],
                    '@title' => ['辞書名'],
                    '@summary' => ['説明'],
                ],
            ],
            [
                [
                    'text' => ['テスト'],
                    '@title' => ['既存の辞書名'],
                ],
                [
                    '@summary' => ['説明'],
                ],
                [
                    'text' => ['テスト'],
                    '@title' => ['既存の辞書名'],
                    '@summary' => ['説明'],
                ],
            ],
        ];
    }
    
    /**
     * @expectedException \BadMethodCallException
     */
    public function testBadSetMetaFieldsCallException()
    {
        (new Dictionary())->setMetaFields([
            '@title' => ['辞書名'],
        ]);
    }
}
