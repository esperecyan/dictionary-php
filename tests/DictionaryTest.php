<?php
namespace esperecyan\dictionary_php;

use Psr\Log\LogLevel;

class DictionaryTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    use \esperecyan\dictionary_php\PreprocessingTrait;
    
    public function testGetFiles()
    {
        $files = new \FilesystemIterator((new parser\GenericDictionaryParser())->generateTempDirectory());
        $dictionary = new Dictionary($files);
        $this->assertSame($files, $dictionary->getFiles());
    }

    /**
     * @param string[][][] $input
     * @param (string|string[]|float|URLSearchParams)[][][] $output
     * @param string[] $logLevels
     * @dataProvider multiDimensionalArraysProvider
     */
    public function testAddWord(array $input, array $output, array $logLevels = [])
    {
        $dictionary = new Dictionary();
        $dictionary->setLogger($this);
        foreach ($input as $multiDimensionalArray) {
            $dictionary->addWord($multiDimensionalArray);
        }
        
        $this->assertEquals($output, $dictionary->getWords());
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
                        '@title' => ['食べ物'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string[][]|null $fieldsAsMultiDimensionalArray
     * @param string[] $metaFields
     * @param string $title
     * @dataProvider titleProvider
     */
    public function testGetTitle(
        array $fieldsAsMultiDimensionalArray = null,
        array $metaFields = [],
        string $title = ''
    ) {
        $dictionary = new Dictionary();
        if ($fieldsAsMultiDimensionalArray) {
            $dictionary->addWord($fieldsAsMultiDimensionalArray);
        }
        $dictionary->setMetadata($metaFields);
        $this->assertSame($title, $dictionary->getTitle());
    }
    
    public function titleProvider(): array
    {
        return [
            [
                null,
                [],
                '',
            ],
            [
                [
                    'text' => ['テスト'],
                ],
                [],
                '',
            ],
            [
                [
                    'text' => ['テスト'],
                    '@title' => ['辞書名'],
                ],
                [],
                '',
            ],
            [
                [
                    'text' => ['テスト'],
                ],
                [
                    '@title' => '辞書名',
                ],
                '辞書名',
            ],
        ];
    }
    
    /**
     * @param string[] $metadata
     * @param (string|string[]|float|URLSearchParams)[][][] $jsonable
     * @dataProvider metadataProvider
     */
    public function testSetMetadata(array $metadata, array $jsonable)
    {
        $dictionary = new Dictionary();
        $dictionary->setMetadata($metadata);
        $this->assertEquals($jsonable, $dictionary->getMetadata());
    }
    
    public function metadataProvider(): array
    {
        return [
            [
                [
                    '@title' => '辞書名',
                ],
                [
                    '@title' => '辞書名',
                ],
            ],
            [
                [
                    '@title' => '辞書名',
                    '@summary' => '説明',
                ],
                [
                    '@title' => '辞書名',
                    '@summary' => [
                        'lml' => '説明',
                        'html' => "<p>説明</p>\n",
                    ],
                ],
            ],
        ];
    }
}
