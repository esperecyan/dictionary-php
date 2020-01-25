<?php
namespace esperecyan\dictionary_php\validator;

class FileLocationValidatorTest extends \PHPUnit\Framework\TestCase implements \Psr\Log\LoggerInterface
{
    use \Psr\Log\LoggerTrait;
    
    /** @var (string|string[])[] */
    protected $logs = [];

    public function log($level, $message, array $context = [])
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
    
    /**
     * @param string $fieldName
     * @param string[] $filenames
     * @param string $input
     * @param string|null $output
     * @dataProvider filenameProvider
     */
    public function testValidate(string $fieldName, array $filenames, string $input, string $output = null)
    {
        $this->assertSame(
            $input === $output,
            (new FileLocationValidator($fieldName, $filenames))->validate($input)
            && empty($filenames[(new FileLocationValidator($fieldName, $filenames))->getBasename($input)])
        );
    }
    
    /**
     * @param string $fieldName
     * @param string[] $filenames
     * @param string $input
     * @param string $output
     * @dataProvider filenameProvider
     */
    public function testCorrect(string $fieldName, array $filenames, string $input, string $output)
    {
        $validator = new FileLocationValidator($fieldName, $filenames);
        $validator->setLogger($this);
        $this->{$output[0] === '/' ? 'assertRegExp' : 'assertSame'}($output, $validator->correct($input));
        $this->assertEquals(
            $input !== $output && (empty($filenames[$input]) || $filenames[$input] !== $output)
                ? [\Psr\Log\LogLevel::ERROR]
                : [],
            array_column($this->logs, 'level'),
            $this->logs ? $this->logs[0]['message'] : ''
        );
    }
    
    public function filenameProvider(): array
    {
        return [
            [
                'image',
                [],
                'https://resource.test/sun.png',
                'https://resource.test/sun.png',
            ],
            [
                'image',
                [],
                'http://resource.test/sun.png',
                'https://resource.test/sun.png',
            ],
            [
                'image',
                [],
                'tag:pokemori.jp,2016:local:sun.png',
                'tag:pokemori.jp,2016:local:sun.png',
            ],
            [
                'image',
                [],
                'tag:pokemori.jp,2016:local:sun',
                'tag:pokemori.jp,2016:local:sun',
            ],
            [
                'image',
                [],
                'test/太陽.png',
                'tag:pokemori.jp,2016:local:%E5%A4%AA%E9%99%BD.png',
            ],
            [
                'image',
                [],
                'tag:pokemori.jp,2016:local:%E5%A4%AA%E9%99%BD.png',
                'tag:pokemori.jp,2016:local:%E5%A4%AA%E9%99%BD.png',
            ],
            [
                'image',
                [],
                'test/sun.jpg',
                'tag:pokemori.jp,2016:local:sun.jpg',
            ],
            [
                'image',
                [],
                'test/sun.jpeg',
                'tag:pokemori.jp,2016:local:sun.jpeg',
            ],
            [
                'image',
                [],
                'test/sun.svg',
                'tag:pokemori.jp,2016:local:sun.svg',
            ],
            [
                'image',
                [],
                'test/sun.jpe',
                'tag:pokemori.jp,2016:local:sun.jpe',
            ],
            [
                'audio',
                [],
                'four-seasons.mp4',
                'tag:pokemori.jp,2016:local:four-seasons.mp4',
            ],
            [
                'audio',
                [],
                'C:\\Users\\山田太郎\\Desktop\\曲\\four-seasons.mp4',
                'tag:pokemori.jp,2016:local:four-seasons.mp4',
            ],
            [
                'audio',
                [],
                'four-seasons',
                'tag:pokemori.jp,2016:local:four-seasons',
            ],
            [
                'audio',
                ['four-seasons.mp4'],
                'four-seasons',
                'tag:pokemori.jp,2016:local:four-seasons',
            ],
            [
                'audio',
                ['four-seasons.mp4'],
                'four-seasons.mp4',
                'four-seasons.mp4',
            ],
            [
                'audio',
                [],
                'four-seasons_ver1.0.mp4',
                'tag:pokemori.jp,2016:local:four-seasons_ver1.0.mp4',
            ],
            [
                'audio',
                [],
                'four-seasons.mp4.ja',
                'tag:pokemori.jp,2016:local:four-seasons.mp4.ja',
            ],
            [
                'audio',
                [],
                '.mp4',
                'tag:pokemori.jp,2016:local:.mp4',
            ],
            [
                'audio',
                [],
                '',
                '/^tag:pokemori.jp,2016:local:[0-9a-f]{8}$/u',
            ],
            [
                'audio',
                [],
                'C:\\Users\\山田太郎\\Desktop\\曲\\四季『春』.mp4',
                'tag:pokemori.jp,2016:local:%E5%9B%9B%E5%AD%A3%E3%80%8E%E6%98%A5%E3%80%8F.mp4',
            ],
            [
                'audio',
                ['四季『春』.mp4' => 'shiki-haru.mp4'],
                'C:\\Users\\山田太郎\\Desktop\\曲\\四季『春』.mp4',
                'shiki-haru.mp4',
            ],
            [
                'audio',
                ['四季『春』.mp4'],
                'C:\\Users\\山田太郎\\Desktop\\曲\\四季『春』.mp4',
                '四季『春』.mp4',
            ],
            [
                'audio',
                ['-AUX.mp4' => 'aux-.mp4'],
                'C:\\Users\\山田太郎\\Desktop\\曲\\-AUX.mp4',
                'aux-.mp4',
            ],
            [
                'audio',
                ['test--test.mp4'],
                'C:\\Users\\山田太郎\\Desktop\\曲\\test--test.mp4',
                'test--test.mp4',
            ],
            [
                'audio',
                ['test--TEST.mp4' => 'test-test.mp4'],
                'C:\\Users\\山田太郎\\Desktop\\曲\\test--TEST.mp4',
                'test-test.mp4',
            ],
            [
                'audio',
                ['test--TEST.mp4' => 'test-test.mp4'],
                'test--TEST.mp4',
                'test-test.mp4',
            ],
            [
                'audio',
                ['test--TEST.mp4' => 'test-test.mp4'],
                'web-service-identifier/test--TEST.mp4',
                'test-test.mp4',
            ],
            [
                'image',
                ['test.png'],
                'TEST.png',
                'test.png',
            ],
            [
                'image',
                ['TEST.png' => 'test.png'],
                'TEST.png',
                'test.png',
            ],
        ];
    }
    
    /**
     * @param string $fieldName
     * @dataProvider invalidFieldNameProvider
     */
    public function testInvalidFieldName(string $fieldName)
    {
        $this->expectException(\DomainException::class);
        new FileLocationValidator($fieldName);
    }
    
    public function invalidFieldNameProvider(): array
    {
        return [
            ['text'],
            //['image'],
            //['audio'],
            //['video'],
            ['image-source'],
            ['audio-source'],
            ['video-source'],
            ['answer'],
            ['description'],
            ['weight'],
            ['specifics'],
            ['question'],
            ['option'],
            ['type'],
            ['@title'],
            ['@summary'],
            ['@regard'],
            ['@image'],
            [' image'],
            ['image '],
            [' '],
            [''],
        ];
    }
}
