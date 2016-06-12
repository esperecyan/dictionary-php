<?php
namespace esperecyan\dictionary_php\validator;

use esperecyan\url\URLSearchParams;
use Psr\Log\LogLevel;

class WordValidatorTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    
    /**
     * @param string[][] $input
     * @param (string|string[]|float|URLSearchParams)[][] $output
     * @param string[] $logLevels
     * @dataProvider wordProvider
     */
    public function testParse(array $input, array $output, array $logLevels = [])
    {
        $wordValidator = new WordValidator();
        $wordValidator->setLogger($this);
        $this->assertEquals($output, $wordValidator->parse($input));
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
                    'text' => ['サファイア'],
                ],
                [
                    'text' => ['サファイア'],
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
                [LogLevel::WARNING],
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
                [LogLevel::WARNING],
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
                [LogLevel::ERROR, LogLevel::WARNING],
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
                [LogLevel::WARNING],
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
                [LogLevel::WARNING, LogLevel::WARNING],
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
                [LogLevel::WARNING, LogLevel::ERROR, LogLevel::ERROR],
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
                [LogLevel::WARNING, LogLevel::WARNING, LogLevel::ERROR],
            ],
            [
                [
                    'text' => ['party'],
                    'answer' => ['/ぱーてぃー?/'],
                ],
                [
                    'text' => ['party'],
                ],
                [LogLevel::WARNING, LogLevel::ERROR, LogLevel::WARNING],
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
                    'image-source' => [['lml' => 'テスト', 'html' => "<p>テスト</p>\n"]],
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
                    'audio-source' => [['lml' => 'テスト', 'html' => "<p>テスト</p>\n"]],
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
                    'video-source' => [['lml' => 'テスト', 'html' => "<p>テスト</p>\n"]],
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
                    'description' => [['lml' => '<span>テスト</span>', 'html' => "<p><span>テスト</span></p>\n"]],
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
                    'weight' => [0.5],
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
                    'specifics' => [new URLSearchParams('length=0.5')],
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
                [LogLevel::WARNING, LogLevel::WARNING],
            ],
            [
                [
                    'text' => ['おだい'],
                    '@title' => ['メタデータ'],
                ],
                [
                    'text' => ['おだい'],
                    '@title' => ['メタデータ'],
                ],
            ],
            [
                [
                    'text' => [str_repeat('あ', 400)],
                    'description' => [str_repeat('あ', 10000)],
                ],
                [
                    'text' => [str_repeat('あ', 400)],
                    'description' => [[
                        'lml' => str_repeat('あ', 10000),
                        'html' => '<p>' . str_repeat('あ', 10000) . "</p>\n",
                    ]],
                ],
            ],
        ];
    }
    
    /**
     * @param string[] $input
     * @param (string|string[])[] $output
     * @param string[] $logLevels
     * @dataProvider metadataProvider
     */
    public function testParseMetadata(array $input, array $output, array $logLevels = [])
    {
        $wordValidator = new WordValidator();
        $wordValidator->setLogger($this);
        $this->assertEquals($output, $wordValidator->parseMetadata($input));
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function metadataProvider(): array
    {
        return [
            [
                [
                    '@title' => 'con.///\\\\',
                ],
                [
                    '@title' => 'con.///\\\\',
                ],
            ],
            [
                [
                    '@summary' => '<span data-invalid="">テスト</span>',
                ],
                [
                    '@summary' => ['lml' => '<span>テスト</span>', 'html' => "<p><span>テスト</span></p>\n"],
                ],
                [LogLevel::ERROR],
            ],
            [
                [
                    '@regard' => '[a-z]',
                ],
                [
                    '@regard' => '[a-z]',
                ],
            ],
            [
                [
                    '@regard' => 'a-z',
                ],
                [],
                [LogLevel::ERROR],
            ],
            [
                [
                    'text' => 'おだい',
                    '@title' => 'メタデータ',
                ],
                [
                    'text' => 'おだい',
                    '@title' => 'メタデータ',
                ],
            ],
            [
                [
                    '@title' => str_repeat('あ', 400),
                    '@summary' => str_repeat('あ', 10000),
                ],
                [
                    '@title' => str_repeat('あ', 400),
                    '@summary' => [
                        'lml' => str_repeat('あ', 10000),
                        'html' => '<p>' . str_repeat('あ', 10000) . "</p>\n",
                    ],
                ],
            ],
        ];
    }
    
    /**
     * @param string[][] $input
     * @param bool $metadata
     * @expectedException \esperecyan\dictionary_php\exception\SyntaxException
     * @dataProvider invalidWords
     */
    public function testSyntaxException(array $input, bool $metadata = false)
    {
        $wordValidator = new WordValidator();
        if ($metadata) {
            $wordValidator->parseMetadata($input);
        } else {
            $wordValidator->parse($input);
        }
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
            [
                [
                    'text' => [str_repeat('あ', 401)],
                ],
            ],
            [
                [
                    'text' => ['テスト'],
                    'description' => [str_repeat('あ', 10001)],
                ],
            ],
            [
                [
                    '@title' => str_repeat('あ', 401),
                ],
                true,
            ],
            [
                [
                    '@summary' => str_repeat('あ', 10001),
                ],
                true,
            ],
        ];
    }
}
