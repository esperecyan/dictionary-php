<?php
namespace esperecyan\dictionary_api\validator;

use Psr\Log\LogLevel;

class AnswerValidatorTest extends \PHPUnit_Framework_TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_api\LogLevelLoggerTrait;
    
    /**
     * @param string $input
     * @param bool $result
     * @dataProvider stringProvider
     */
    public function testIsRegExp(string $input, bool $result)
    {
        $this->assertSame($result, (new AnswerValidator())->isRegExp($input));
    }
    
    public function stringProvider(): array
    {
        return [
            ['/にとを?おうものはいっとを?もえず/', true],
            ['/[a-z/'   , true ],
            ['/////'    , true ],
            ['/'        , false],
            [''         , false],
            ['/テスト'  , false],
            ['テスト/'  , false],
            ['/テスト/i', false],
            ['/テスト/ ', false],
            [' /テスト/', false],
        ];
    }
    
    /**
     * @param string $input
     * @param bool $result
     * @dataProvider regexpProvider
     */
    public function testValidateRegexp(string $input, bool $result)
    {
        $this->assertSame($result, (new AnswerValidator())->validateRegexp($input));
    }
    
    public function regexpProvider(): array
    {
        return [
            ['/にとを?おうものはいっとを?もえず/', true],
            ['/[a-z/'       , false],
            ['/////'        , false],
            ['/テスト/i'    , false],
            ['/てすと/i'    , false],
            ['/てすと/ '    , false],
            [' /てすと/'    , false],
            [' /てすと/'    , false],
            ['/𩸽/'         , false],
            ['/𩸽/u'        , false],
            ['/\\xF0\\xA9\\xB8\\xBD/', false],
            ['/\\xf0\\xa9\\xb8\\xbd/', true], // 仕様違反
            ['/\\t/'        , true ], // 仕様違反
            ['/[[:alpha:]]/', true ], // 仕様違反
        ];
    }
    
    /**
     * @param string $input
     * @param string $output
     * @param string[] $logLevels
     * @dataProvider answerProvider
     */
    public function testCorrect(string $input, string $output = '', array $logLevels = [])
    {
        $validator = new AnswerValidator();
        $validator->setLogger($this);
        $this->assertSame($output, $validator->correct($input));
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function answerProvider(): array
    {
        return [
            [
                'る〜こと',
                'る〜こと',
            ],
            [
                'る～こと',
                'る〜こと',
                [LogLevel::ERROR],
            ],
            [
                'る~こと',
                'る~こと',
                [LogLevel::NOTICE],
            ],
            [
                '?',
                '?',
                [LogLevel::NOTICE],
            ],
            [
                '？',
                '?',
                [LogLevel::ERROR, LogLevel::NOTICE],
            ],
            [
                '𩸽',
                '𩸽',
                [LogLevel::NOTICE],
            ],
            [
                "か\u{3099}",
                'が',
                [LogLevel::ERROR],
            ],
            [
                "か\u{309A}",
                'か',
                [LogLevel::ERROR],
            ],
            [
                'ｶﾞ',
                'ガ',
                [LogLevel::ERROR],
            ],
            [
                'ｶﾟ',
                'カ',
                [LogLevel::ERROR],
            ],
            [
                'やまだたろう',
                'やまだたろう',
            ],
            [
                'やまだ たろう',
                'やまだたろう',
                [LogLevel::ERROR],
            ],
            [
                ' ',
                '',
                [LogLevel::ERROR],
            ],
        ];
    }
}
