<?php
namespace esperecyan\dictionary_api\internal;

use Psr\Log\LogLevel;

class WordTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_api\LogLevelLoggerTrait;
    
    public function testGetFieldsAsMultiDimensionalArray()
    {
        $fieldsAsMultiDimensionalArray = [
            'text' => ['テスト'],
            'answer' => ['テスト', 'しけん', 'test'],
        ];
        
        $word = new Word();
        (function ($fieldsAsMultiDimensionalArray) {
            $this->fieldsAsMultiDimensionalArray = $fieldsAsMultiDimensionalArray;
        })->call($word, $fieldsAsMultiDimensionalArray);
        $this->assertEquals($fieldsAsMultiDimensionalArray, $word->getFieldsAsMultiDimensionalArray());
    }
    
    /**
     * @expectedException \BadMethodCallException
     */
    public function testBadGetFieldsAsMultiDimensionalArrayCallException()
    {
        (new Word())->getFieldsAsMultiDimensionalArray();
    }
    
    /**
     * @param string[][] $input
     * @param string[][] $output
     * @param string[] $logLevels
     * @dataProvider wordProvider
     */
    public function testSetFieldsAsMultiDimensionalArray(array $input, array $output, array $logLevels = [])
    {
        $word = new Word();
        $word->setLogger($this);
        $word->setFieldsAsMultiDimensionalArray($input);
        $this->assertEquals($output, $word->getFieldsAsMultiDimensionalArray());
        $this->assertEquals($logLevels, $this->logLevels);
    }

    public function wordProvider(): array
    {
        return [
            [
                [
                    'text' => ['テスト'],
                ],
                [
                    'text' => ['テスト'],
                ],
            ],
            [
                [
                    'text' => ['甘藍'],
                    'answer' => ['キャベツ', 'かんらん'],
                ],
                [
                    'text' => ['甘藍'],
                    'answer' => ['キャベツ', 'かんらん'],
                ],
            ],
            [
                [
                    'text' => ['る〜こと'],
                ],
                [
                    'text' => ['る〜こと'],
                ],
            ],
            [
                [
                    'text' => ['る～こと'],
                ],
                [
                    'text' => ['る〜こと'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['る～こと'],
                    'answer' => ['る〜こと'],
                ],
                [
                    'text' => ['る～こと'],
                    'answer' => ['る〜こと'],
                ],
            ],
            [
                [
                    'text' => ['る～こと'],
                    'answer' => ['る~こと'],
                ],
                [
                    'text' => ['る～こと'],
                    'answer' => ['る~こと'],
                ],
                [LogLevel::NOTICE],
            ],
            [
                [
                    'text' => ['? (疑問符)'],
                    'answer' => ['?'],
                ],
                [
                    'text' => ['? (疑問符)'],
                    'answer' => ['?'],
                ],
                [LogLevel::NOTICE],
            ],
            [
                [
                    'text' => ['? (疑問符)'],
                    'answer' => ['？'],
                ],
                [
                    'text' => ['? (疑問符)'],
                    'answer' => ['?'],
                ],
                [LogLevel::ERROR, LogLevel::NOTICE],
            ],
            [
                [
                    'text' => ['𩸽 (ほっけ)'],
                    'answer' => ['𩸽'],
                ],
                [
                    'text' => ['𩸽 (ほっけ)'],
                    'answer' => ['𩸽'],
                ],
                [LogLevel::NOTICE],
            ],
            [
                [
                    'text' => ['蛾'],
                    'answer' => ["か\u{3099}"],
                ],
                [
                    'text' => ['蛾'],
                    'answer' => ['が'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ["か\u{309A}"],
                ],
                [
                    'text' => ['か'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['ｶﾞ'],
                ],
                [
                    'text' => ['ガ'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['ｶﾟ'],
                ],
                [
                    'text' => ['カ'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['山田太郎'],
                    'answer' => ['やまだたろう'],
                ],
                [
                    'text' => ['山田太郎'],
                    'answer' => ['やまだたろう'],
                ],
            ],
            [
                [
                    'text' => ['山田太郎'],
                    'answer' => ['やまだ たろう'],
                ],
                [
                    'text' => ['山田太郎'],
                    'answer' => ['やまだたろう'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['スペース'],
                    'answer' => [' '],
                ],
                [
                    'text' => ['スペース'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => [' '],
                    'answer' => ['スペース'],
                ],
                [
                    'text' => [' '],
                    'answer' => ['スペース'],
                ],
            ],
            [
                [
                    'text' => ['party'],
                    'answer' => ['party', '/ぱーてぃー?/'],
                ],
                [
                    'text' => ['party'],
                    'answer' => ['party', '/ぱーてぃー?/'],
                ],
                [LogLevel::NOTICE, LogLevel::NOTICE],
            ],
            [
                [
                    'text' => ['party'],
                    'answer' => ['party', '/ぱーてぃー?[/', '/パーティー?/'],
                ],
                [
                    'text' => ['party'],
                    'answer' => ['party'],
                ],
                [LogLevel::NOTICE, LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['party'],
                    'answer' => ['/ぱーてぃー?/', 'party'],
                ],
                [
                    'text' => ['party'],
                    'answer' => ['party', '/ぱーてぃー?/'],
                ],
                [LogLevel::NOTICE, LogLevel::NOTICE, LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['party'],
                    'answer' => ['/ぱーてぃー?/'],
                ],
                [
                    'text' => ['party'],
                ],
                [LogLevel::NOTICE, LogLevel::ERROR, LogLevel::NOTICE],
            ],
            [
                [
                    'text' => ['四季'],
                    'audio' => ['four-seasons.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [
                    'text' => ['四季'],
                    'audio' => ['local/four-seasons.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['四季'],
                    'audio' => ['C:\\Users\\山田太郎\\Desktop\\曲\\four-seasons.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [
                    'text' => ['四季'],
                    'audio' => ['local/four-seasons.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['四季'],
                    'audio' => ['four-seasons'],
                    'answer' => ['しき', 'はる'],
                ],
                [
                    'text' => ['四季'],
                    'audio' => ['local/four-seasons.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['四季'],
                    'audio' => ['four-seasons_ver1.0.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [
                    'text' => ['四季'],
                    'audio' => ['local/four-seasons_ver1．0.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['四季'],
                    'audio' => ['four-seasons.mp4.ja'],
                    'answer' => ['しき', 'はる'],
                ],
                [
                    'text' => ['四季'],
                    'audio' => ['local/four-seasons．mp4.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['四季'],
                    'audio' => ['four-seasons.mp4.m4a.mp3.svg'],
                    'answer' => ['しき', 'はる'],
                ],
                [
                    'text' => ['四季'],
                    'audio' => ['local/four-seasons．mp4．m4a．mp3.mp4'],
                    'answer' => ['しき', 'はる'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'image' => ['テスト.png'],
                ],
                [
                    'text' => ['テスト'],
                    'image' => ['local/テスト.png'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'video' => ['テスト.mp4'],
                ],
                [
                    'text' => ['テスト'],
                    'video' => ['local/テスト.mp4'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'image-source' => ['**テスト**'],
                ],
                [
                    'text' => ['テスト'],
                    'image-source' => ['テスト'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'audio-source' => ['**テスト**'],
                ],
                [
                    'text' => ['テスト'],
                    'audio-source' => ['テスト'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'video-source' => ['**テスト**'],
                ],
                [
                    'text' => ['テスト'],
                    'video-source' => ['テスト'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'description' => ['<span data-invalid="">テスト</span>'],
                ],
                [
                    'text' => ['テスト'],
                    'description' => ['<span>テスト</span>'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'weight' => ['0.50'],
                ],
                [
                    'text' => ['テスト'],
                    'weight' => ['0.5'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'specifics' => ['length=0.50'],
                ],
                [
                    'text' => ['テスト'],
                    'specifics' => ['length=0.5'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'question' => ['問 題 文'],
                ],
                [
                    'text' => ['テスト'],
                    'question' => ['問 題 文'],
                ],
            ],
            [
                [
                    'text' => ['テ ス ト'],
                    'option' => ['ﾃｽﾄ'],
                ],
                [
                    'text' => ['テスト'],
                    'option' => ['テスト'],
                ],
                [LogLevel::ERROR, LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テ ス ト'],
                    'option' => ['ﾃｽﾄ'],
                    'type' => ['selection'],
                ],
                [
                    'text' => ['テ ス ト'],
                    'option' => ['テスト'],
                    'type' => ['selection'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['テスト'],
                    'option' => ['選択肢A', '選択肢B'],
                    'type' => ['selection'],
                ],
                [
                    'text' => ['テスト'],
                    'option' => ['選択肢A', '選択肢B'],
                    'type' => ['selection'],
                ],
                [LogLevel::NOTICE, LogLevel::NOTICE],
            ],
            [
                [
                    'text' => ['テスト'],
                    '@title' => ['con.///\\\\'],
                ],
                [
                    'text' => ['テスト'],
                    '@title' => ['con.///\\\\'],
                ],
            ],
            [
                [
                    'text' => ['テスト'],
                    '@summary' => ['<span data-invalid="">テスト</span>'],
                ],
                [
                    'text' => ['テスト'],
                    '@summary' => ['<span>テスト</span>'],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['test'],
                    '@regard' => ['[a-z]'],
                ],
                [
                    'text' => ['test'],
                    '@regard' => ['[a-z]'],
                ],
                [LogLevel::NOTICE],
            ],
            [
                [
                    'text' => ['test'],
                    '@regard' => ['a-z'],
                ],
                [
                    'text' => ['test'],
                ],
                [LogLevel::ERROR, LogLevel::NOTICE],
            ],
        ];
    }
    
    /**
     * @expectedException \BadMethodCallException
     */
    public function testBadSetFieldsAsMultiDimensionalArrayCallException()
    {
        $word = new Word();
        $word->setFieldsAsMultiDimensionalArray(['text' => ['テスト']]);
        $word->setFieldsAsMultiDimensionalArray(['text' => ['テスト']]);
    }
    
    /**
     * @param string[][] $input
     * @expectedException \esperecyan\dictionary_api\exception\SyntaxException
     * @dataProvider invalidWords
     */
    public function testSyntaxException($input)
    {
        (new Word())->setFieldsAsMultiDimensionalArray($input);
        
    }
    
    public function invalidWords(): array
    {
        return [
            [
                [
                ],
            ],
            [
                [
                    'answer' => ['テスト'],
                ],
            ],
            [
                [
                    'text' => [' '],
                ],
            ],
            [
                [
                    'text' => [' '],
                    'answer' => [' '],
                ],
            ],
            [
                [
                    'text' => ['optionフィールドが存在しない'],
                    'type' => ['selection'],
                ],
            ],
        ];
    }
}
