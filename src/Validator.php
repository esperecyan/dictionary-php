<?php
namespace esperecyan\dictionary_php;

use esperecyan\dictionary_php\exception\SyntaxException;

/**
 * 辞書に同梱されている1つのファイルのバリデートを行います。
 */
class Validator extends log\AbstractLoggerAware
{
    /**
     * 画像ファイルの最大容量。
     * @var int
     */
    const MAX_IMAGE_SIZE = 2 ** 20;
    
    /**
     * 推奨される画像ファイルの最大容量。
     * @var int
     */
    const MAX_RECOMMENDED_IMAGE_SIZE = 100 * 2 ** 10;
    
    /**
     * 音声ファイルの最大容量。
     * @var int
     */
    const MAX_AUDIO_SIZE = 16 * 2 ** 20;
    
    /**
     * 推奨される音声ファイルの最大容量。
     * @var int
     */
    const MAX_RECOMMENDED_AUDIO_SIZE = 2 ** 20;
    
    /**
     * 動画ファイルの最大容量。
     * @var int
     */
    const MAX_VIDEO_SIZE = 16 * 2 ** 20;
    
    /**
     * 推奨される動画ファイルの最大容量。
     * @var int
     */
    const MAX_RECOMMENDED_VIDEO_SIZE = 2 ** 20;
    
    /** @var string[][] 有効な拡張子。 */
    const VALID_EXTENSIONS = [
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/svg+xml' => ['svg'],
        'audio/mp4' => ['mp4', 'm4a'],
        'audio/mpeg' => ['mp3'],
        'video/mp4' => ['mp4'],
    ];
    
    /** @var fileinfo\Finfo */
    protected $finfo;

    public function __construct()
    {
        parent::__construct();
        $this->finfo = new fileinfo\Finfo(FILEINFO_MIME_TYPE);
    }
    
    /**
     * ファイルサイズをチェックします。
     * @param int $size
     * @param string $topLevelType
     * @param string $filename
     * @throws SyntaxException
     */
    protected function checkFileSize(int $size, string $topLevelType, string $filename)
    {
        $byteFormatter = new \ScriptFUSION\Byte\ByteFormatter();
        
        $message = sprintf(_('「%1$s」の容量は %2$s です。'), $filename, $byteFormatter->format($size));
        
        switch ($topLevelType) {
            case 'image':
                if ($size > self::MAX_IMAGE_SIZE) {
                    throw new SyntaxException(sprintf(
                        _('画像ファイルの容量は %s 以下にしてください。'),
                        $byteFormatter->format(self::MAX_IMAGE_SIZE)
                    ) . $message);
                } elseif ($size > self::MAX_RECOMMENDED_IMAGE_SIZE) {
                    $this->logger->warning(sprintf(
                        _('画像ファイルの容量は %s 以下にすべきです。'),
                        $byteFormatter->format(self::MAX_RECOMMENDED_IMAGE_SIZE)
                    ) . $message);
                }
                break;
                
            case 'audio':
                if ($size > self::MAX_AUDIO_SIZE) {
                    throw new SyntaxException(sprintf(
                        _('音声ファイルの容量は %s 以下にしてください。'),
                        $byteFormatter->format(self::MAX_AUDIO_SIZE)
                    ) . $message);
                } elseif ($size > self::MAX_RECOMMENDED_AUDIO_SIZE) {
                    $this->logger->warning(sprintf(
                        _('音声ファイルの容量は %s 以下にすべきです。'),
                        $byteFormatter->format(self::MAX_RECOMMENDED_AUDIO_SIZE)
                    ) . $message);
                }
                break;
                
            case 'video':
                if ($size > self::MAX_VIDEO_SIZE) {
                    throw new SyntaxException(sprintf(
                        _('動画ファイルの容量は %s 以下にしてください。'),
                        $byteFormatter->format(self::MAX_VIDEO_SIZE)
                    ) . $message);
                } elseif ($size > self::MAX_RECOMMENDED_VIDEO_SIZE) {
                    $this->logger->warning(sprintf(
                        _('動画ファイルの容量は %s 以下にすべきです。'),
                        $byteFormatter->format(self::MAX_RECOMMENDED_VIDEO_SIZE)
                    ) . $message);
                }
                break;
        }
    }
    
    /**
     * @param string|\SplFileInfo $file
     * @param string $filename
     * @return string[]
     */
    public function correct($file, string $filename): array
    {
        if (!(new validator\FilenameValidator())->validate($filename)) {
            throw new SyntaxException(sprintf(_('「%s」は妥当なファイル名ではありません。'), $filename));
        }

        $type = is_string($file) ? $this->finfo->buffer($file) : $this->finfo->file($file->getRealPath());

        if (empty(self::VALID_EXTENSIONS[$type])) {
            throw new SyntaxException(sprintf(_('「%s」は妥当な画像、音声、動画ファイルではありません。'), $filename));
        }

        if (!in_array((new \SplFileInfo($filename))->getExtension(), self::VALID_EXTENSIONS[$type])) {
            throw new SyntaxException(sprintf(_('「%s」の拡張子は次のいずれかにしなければなりません:'), $filename)
                . ' ' . implode(', ', self::VALID_EXTENSIONS[$type]));
        }

        $topLevelType = explode('/', $type)[0];

        $this->checkFileSize(is_string($file) ? mb_strlen($file, 'ASCII') : $file->getSize(), $topLevelType, $filename);

        if ($topLevelType === 'image') {
            $validator = new validator\ImageValidator($type, $filename);
            $validator->setLogger($this->logger);
            $file = $validator->correct(is_string($file) ? $file : (new Parser())->getBinary($file));
        }
        
        return [
            'bytes' => $file,
            'type' => $type === 'image/svg+xml' ? 'image/svg+xml; charset=UTF-8' : $type,
            'name' => $filename,
        ];
    }
}
