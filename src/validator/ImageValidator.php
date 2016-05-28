<?php
namespace esperecyan\dictionary_php\validator;

use esperecyan\dictionary_php\exception\SyntaxException;

/**
 * 画像ファイルの矯正。
 */
class ImageValidator extends AbstractFieldValidator
{
    /**
     * 推奨される画像の最大の幅。ピクセル数。
     * @var int
     */
    const MAX_RECOMMENDED_IMAGE_WIDTH = 1000;
    
    /**
     * 推奨される画像の最大の高さ。ピクセル数。
     * @var int
     */
    const MAX_RECOMMENDED_IMAGE_HEIGHT = 1000;
    
    /** @var string */
    protected $type;
    
    /** @var string */
    protected $filename;
    
    /**
     * @param string $type MIMEタイプ。
     * @param string $filename エラーメッセージで使用するファイル名。
     * @throws \DomainException MIMEタイプが image/png、image/jpeg、image/svg+xml のいずれでもないとき。
     */
    public function __construct(string $type, string $filename)
    {
        parent::__construct();
        
        if (in_array($type, ['image/png', 'image/jpeg', 'image/svg+xml'])) {
            $this->type = $type;
        } else {
            throw new \DomainException();
        }
        $this->filename = $filename;
    }
    
    /**
     * 入力された画像がExifであればエラーを記録し、JFIFに変換します。
     * @param \Imagick $imagick JFIFかExif。
     */
    protected function convertExifToJFIF(\Imagick $imagick)
    {
        if ($imagick->getImageProperties('exif:*')) {
            $this->logger->error(sprintf(_('「%s」はExif形式です。'), $this->filename));
            switch ($imagick->getImageOrientation()) {
                case \Imagick::ORIENTATION_TOPRIGHT:
                    $imagick->flopImage();
                    break;
                case \Imagick::ORIENTATION_BOTTOMRIGHT:
                    $imagick->rotateImage('none', 180);
                    break;
                case \Imagick::ORIENTATION_BOTTOMLEFT:
                    $imagick->rotateImage('none', 180);
                    $imagick->flopImage();
                    break;
                case \Imagick::ORIENTATION_LEFTTOP:
                    $imagick->rotateImage('none', 90);
                    $imagick->flopImage();
                    break;
                case \Imagick::ORIENTATION_RIGHTTOP:
                    $imagick->rotateImage('none', 90);
                    break;
                case \Imagick::ORIENTATION_RIGHTBOTTOM:
                    $imagick->rotateImage('none', 270);
                    $imagick->flopImage();
                    break;
                case \Imagick::ORIENTATION_LEFTBOTTOM:
                    $imagick->rotateImage('none', 270);
                    break;
            }
            $imagick->stripImage();
        }
    }
    
    /**
     * 幅と高さをチェックします。
     * @param \Imagick $imagick
     */
    protected function checkSize($imagick)
    {
        $width = $imagick->getImageWidth();
        if ($width > self::MAX_RECOMMENDED_IMAGE_WIDTH) {
            $this->logger->notice(sprintf(
                _('画像の幅は %1$s 以下にすべきです。「%2$s」の幅は %3$s です。'),
                self::MAX_RECOMMENDED_IMAGE_WIDTH . 'px',
                $this->filename,
                $width . 'px'
            ));
        }

        $height = $imagick->getImageHeight();
        if ($height > self::MAX_RECOMMENDED_IMAGE_HEIGHT) {
            $this->logger->notice(sprintf(
                _('画像の高さは %1$s 以下にすべきです。「%2$s」の高さは %3$s です。'),
                self::MAX_RECOMMENDED_IMAGE_HEIGHT . 'px',
                $this->filename,
                $height . 'px'
            ));
        }
    }
    
    /**
     * MIMEタイプをもとにエラーメッセージを生成します。
     * @return string
     */
    protected function generateErrorMessage(): string
    {
        switch ($this->type) {
            case 'image/png':
                $readableType = _('PNGファイル');
                break;
            case 'image/jpeg':
                $readableType = _('JFIF (JPEG) ファイル');
                break;
            case 'image/svg+xml':
                $readableType = _('SVGファイル');
                break;
        }
        return sprintf(_('「%1$s」は妥当な%2$sではありません。'), $this->filename, $readableType);
    }
    
    /**
     * PHPがWindowsでコンパイルされていれば真を返します。
     * @return bool
     */
    protected function isWindows():bool
    {
        return strpos(PHP_OS, 'WIN') === 0;
    }
    
    public function correct(string $input): string
    {
        if ($this->type === 'image/svg+xml') {
            try {
                $validator = new SVGValidator();
                $validator->setLogger($this->logger);
                $input = $validator->correct($input);
            } catch (SyntaxException $e) {
                throw new SyntaxException($this->generateErrorMessage(), 0, $e);
            }
        }
        
        if ($this->isWindows() && $this->type === 'image/svg+xml') {
            // IM_MOD_RL_svg_.dllを利用するとPHPがクラッシュするため
            $corrected = $input;
        } else {
            $imagick = new \Imagick();
            try {
                $imagick->readImageBlob($input);
            } catch (\ImagickException $e) {
                throw preg_match('/`(PNG|JPEG|SVG)\'/u', $e->getMessage()) === 1
                    ? $e
                    : new SyntaxException($this->generateErrorMessage(), 0, $e);
            }

            if (str_replace('/x-', '/', $imagick->getImageMimeType()) !== $this->type) {
                throw new SyntaxException($this->generateErrorMessage());
            }

            if ($this->type === 'image/jpeg') {
                $this->convertExifToJFIF($imagick);
            }

            $this->checkSize($imagick);

            $corrected = $imagick->getImageBlob();
            $imagick->clear();
        }
        
        return $corrected;
    }
}
