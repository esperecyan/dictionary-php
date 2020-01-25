<?php
namespace esperecyan\dictionary_php\validator;

use esperecyan\dictionary_php\exception\SyntaxException;
use esperecyan\dictionary_php\parser\GenericDictionaryParser;
use Intervention\Image\{ImageManager, Image, Exception\NotReadableException};

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
     * 入力された画像がExifであればエラーを記録し、向きを補正します。
     * @param Image $image JFIFかExif。
     * @return 
     */
    protected function convertExifToJFIF(Image $image)
    {
        if (strpos($image->exif('SectionsFound'), 'EXIF') === false) {
            return $image;
        }

        $this->logger->error(sprintf(_('「%s」はExif形式です。'), $this->filename));
        return $image->orientate();
    }
    
    /**
     * 幅と高さをチェックします。
     * @param Image $image
     */
    protected function checkSize($image)
    {
        $width = $image->getWidth();
        if ($width > self::MAX_RECOMMENDED_IMAGE_WIDTH) {
            $this->logger->warning(sprintf(
                _('画像の幅は %1$s 以下にすべきです。「%2$s」の幅は %3$s です。'),
                self::MAX_RECOMMENDED_IMAGE_WIDTH . 'px',
                $this->filename,
                $width . 'px'
            ));
        }

        $height = $image->getHeight();
        if ($height > self::MAX_RECOMMENDED_IMAGE_HEIGHT) {
            $this->logger->warning(sprintf(
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
    
    public function correct(string $input): string
    {
        if ($this->type === 'image/svg+xml') {
            try {
                $validator = new SVGValidator();
                $validator->setLogger($this->logger);
                $corrected = $validator->correct($input);
            } catch (SyntaxException $e) {
                throw new SyntaxException($this->generateErrorMessage(), 0, $e);
            }
            return $corrected;
        }
        
        $manager = new ImageManager();
        // ファイルパスではなくバイナリデータを読み込むと、Exif情報が失われる問題への対処
        // Unable to read exif data unless loaded from path · Issue #745 · Intervention/image
        // <https://github.com/Intervention/image/issues/745>
        $path = (new GenericDictionaryParser())->generateTempFile($input);
        try {
            $image = $manager->make($path);
        } catch (NotReadableException $e) {
            throw new SyntaxException($this->generateErrorMessage(), 0, $e);
        }

        if ($image->mime() !== $this->type) {
            throw new SyntaxException($this->generateErrorMessage());
        }

        if ($this->type === 'image/jpeg') {
            $this->convertExifToJFIF($image);
        }

        $this->checkSize($image);

        $corrected = $image->encode();
        $image->destroy();
        
        return $corrected;
    }
}
