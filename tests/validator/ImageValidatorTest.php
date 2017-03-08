<?php
namespace esperecyan\dictionary_php\validator;

use Psr\Log\LogLevel;
use lsolesen\pel;

class ImageValidatorTest extends \PHPUnit\Framework\TestCase implements \Psr\Log\LoggerInterface
{
    use \esperecyan\dictionary_php\LogLevelLoggerTrait;
    
    /**
     * @param string $type
     * @param \Closure $generateImage
     * @param \Closure $checkOutput
     * @param array $logLevels
     * @dataProvider imageProvider
     */
    public function testCorrect(
        string $type,
        \Closure $generateImage,
        \Closure $checkOutput,
        array $logLevels = []
    ) {
        $validator = new ImageValidator($type, '');
        $validator->setLogger($this);
        $checkOutput->call($this, $validator->correct($generateImage()));
        $this->assertEquals($logLevels, $this->logLevels);
    }
    
    public function imageProvider(): array
    {
        return [
            [
                'image/png',
                function (): string {
                    $image = imagecreatetruecolor(1000, 1000);
                    ob_start();
                    imagepng($image);
                    imagedestroy($image);
                    return ob_get_clean();
                },
                function (string $output) {
                    $image = imagecreatefromstring($output);

                    $imageSize = getimagesizefromstring($output);
                    $this->assertSame(1000, $imageSize[0]);
                    $this->assertSame(1000, $imageSize[1]);
                    $this->assertSame('image/png', $imageSize['mime']);

                    $color = imagecolorsforindex($image, imagecolorat($image, 999, 999));
                    $this->assertSame(0, $color['red']);
                    $this->assertSame(0, $color['green']);
                    $this->assertSame(0, $color['blue']);
                },
            ],
            [
                'image/jpeg',
                function (): string {
                    return file_get_contents(__DIR__ . '/../resources/exif.jpg');
                },
                function (string $output) {
                    $image = imagecreatefromstring($output);

                    $imageSize = getimagesizefromstring($output);
                    $this->assertSame(1001, $imageSize[0]);
                    $this->assertSame(1001, $imageSize[1]);
                    $this->assertSame('image/jpeg', $imageSize['mime']);

                    $color = imagecolorsforindex($image, imagecolorat($image, 999, 999));
                    $this->assertSame(0, $color['red']);
                    $this->assertSame(0, $color['green']);
                    $this->assertSame(0, $color['blue']);
                    
                    $fp = tmpfile();
                    fwrite($fp, $output);
                    $this->assertArrayNotHasKey('UserComment', exif_read_data(stream_get_meta_data($fp)['uri']));
                },
                [LogLevel::ERROR, LogLevel::WARNING, LogLevel::WARNING],
            ],
            [
                'image/svg+xml',
                function (): string {
                    return '<?xml version="1.0" ?>
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                        <a xlink:href="https://example.com/">
                            <rect width="1000" height="1000" />
                        </a>
                    </svg>';
                },
                function (string $output) {
                    $svgValidatorTest = new SVGValidatorTest();
                    $this->assertEquals($svgValidatorTest->formatXML('<?xml version="1.0" ?>
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <a>
                                <rect width="1000" height="1000" />
                            </a>
                        </svg>
                    '), $svgValidatorTest->formatXML($output));
                },
                [LogLevel::ERROR],
            ],
        ];
    }
    
    /**
     * @param string $type
     * @param \Closure $generateImage
     * @expectedException \esperecyan\dictionary_php\exception\SyntaxException
     * @dataProvider invalidImageProvider
     */
    public function testSyntaxException(string $type, \Closure $generateImage)
    {
        (new ImageValidator($type, ''))->correct($generateImage());
    }
    
    public function invalidImageProvider(): array
    {
        return [
            ['image/svg+xml', function () {
                return '<?xml version="1.0" ?>
                    <svg>
                        ルート要素がSVG名前空間に属していない。
                    </svg>
                ';
            }],
            ['image/png', function () {
                return file_get_contents(__DIR__ . "/../resources/dummy.zip");
            }],
            ['image/png', function () {
                $image = imagecreatetruecolor(1000, 1000);
                ob_start();
                imagejpeg($image);
                imagedestroy($image);
                return ob_get_clean();
            }],
        ];
    }
    
    /**
     * @param string $type
     * @expectedException \DomainException
     * @dataProvider invalidTypeProvider
     */
    public function testDomainException(string $type)
    {
        new ImageValidator($type, '');
    }
    
    public function invalidTypeProvider(): array
    {
        return [
            ['image/gif'],
            ['audio/mp4'],
            ['invalid'],
        ];
    }
}
