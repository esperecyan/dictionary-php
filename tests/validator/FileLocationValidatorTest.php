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
            array_column($this->logs, 'level')
        );
    }
    
    public function filenameProvider(): array
    {
        return [
            [
                'image',
                [],
                'test/太陽.png',
                'test/太陽.png',
            ],
            [
                'image',
                [],
                'test/太陽.jpg',
                'test/太陽.jpg',
            ],
            [
                'image',
                [],
                'test/太陽.jpeg',
                'test/太陽.jpeg',
            ],
            [
                'image',
                [],
                'test/太陽.svg',
                'test/太陽.svg',
            ],
            [
                'image',
                [],
                'test/太陽.jpe',
                'local/太陽.png',
            ],
            [
                'audio',
                [],
                'four-seasons.mp4',
                'local/four-seasons.mp4',
            ],
            [
                'audio',
                [],
                'C:\\Users\\山田太郎\\Desktop\\曲\\four-seasons.mp4',
                'local/four-seasons.mp4',
            ],
            [
                'audio',
                [],
                'four-seasons',
                'local/four-seasons.mp4',
            ],
            [
                'audio',
                ['four-seasons.mp4'],
                'four-seasons',
                'local/four-seasons.mp4',
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
                'local/four-seasons_ver1．0.mp4',
            ],
            [
                'audio',
                [],
                'four-seasons.mp4.ja',
                'local/four-seasons．mp4.mp4',
            ],
            [
                'audio',
                [],
                '.mp4',
                '/^local\\/[0-9a-f]{8}\\.mp4$/u',
            ],
            [
                'audio',
                [],
                'C:\\Users\\山田太郎\\Desktop\\曲\\四季『春』.mp4',
                'local/四季『春』.mp4',
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
        ];
    }
    
    /**
     * @param string $fieldName
     * @expectedException \DomainException
     * @dataProvider invalidFieldNameProvider
     */
    public function testInvalidFieldName(string $fieldName)
    {
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
