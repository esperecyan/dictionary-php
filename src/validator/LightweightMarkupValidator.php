<?php
namespace esperecyan\dictionary_php\validator;

use esperecyan\html_filter\Filter as HTMLFilter;
use esperecyan\{webidl\TypeError, url\URL};

/**
 * image-source、audio-source、video-source、description、@summary フィールドの矯正。
 */
class LightweightMarkupValidator extends AbstractFieldValidator implements \Psr\Log\LoggerInterface
{
    use \Psr\Log\LoggerTrait;
    
    /** @var int エラーメッセージ中に表示するフィールド値の最大文字数。 */
    const FIELD_ELLIPSIS_LENGTH = 20;
    
    /** @var (string|(string|callable|string[])[])[] description、@summary フィールドで利用できる要素・属性。 */
    const DESCRIPTION_WHITELIST = [
        '*' => [
            'dir' => ['ltr', 'rtl', 'auto'],
            'lang' => '/^[a-z]+(-[0-9a-z]+)*$/iu',
            'title',
            'translate' => ['', 'yes', 'no'],
        ],
        'a' => ['href' => self::class . '::isURLWithHTTPScheme'], 'abbr', 'audio' => ['src' => '/./u'],
        'b', 'bdi', 'bdo', 'blockquote' => ['cite' => self::class . '::isURLWithHTTPScheme'], 'br',
        'caption', 'cite', 'code',
        'col' => ['span' => '/^[1-9][0-9]*$/u'], 'colgroup' => ['span' => '/^[1-9][0-9]*$/u'], 'dd',
        'del' => ['cite' => self::class . '::isURLWithHTTPScheme', 'datetime'], 'details',
        'dfn', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i',
        'img' => ['alt', 'height' => '/^[1-9][0-9]*$/u', 'src' => '/./u', 'width' => '/^[1-9][0-9]*$/u'],
        'ins' => ['cite' => self::class . '::isURLWithHTTPScheme', 'datetime'], 'kbd', 'li',
        'ol' => ['reversed' => [''], 'start' => '/^(?:0|-?[1-9][0-9]*)$/u', 'type' => ['1', 'A', 'a', 'i', 'I']],
        'p', 'pre', 'q' => ['cite' => self::class . '::isURLWithHTTPScheme'],
        'rp', 'rt', 'ruby', 's', 'samp', 'small', 'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody',
        'td' => ['colspan' => '/^[1-9][0-9]*$/u', 'rowspan' => '/^[1-9][0-9]*$/u'], 'tfoot',
        'th' => [
            'abbr', 'colspan' => '/^[1-9][0-9]*$/u', 'rowspan' => '/^[1-9][0-9]*$/u',
            'scope' => ['row', 'col', 'rowgroup', 'colgroup'],
        ],
        'thead', 'time' => 'datetime', 'tr', 'u', 'ul', 'var',
        'video' => ['height' => '/^[1-9][0-9]*$/u', 'src' => '/./u', 'width' => '/^[1-9][0-9]*$/u'],
        'wbr',
    ];
    
    /** @var (string|(string|callable|string[])[])[] image-source、audio-source、video-source フィールドで利用できる要素・属性。 */
    const SOURCE_WHITELIST = [
        '*' => [
            'dir' => ['ltr', 'rtl', 'auto'],
            'lang' => '/^[a-z]+(-[0-9a-z]+)*$/iu',
            'title',
            'translate' => ['', 'yes', 'no'],
        ],
        'a' => ['href' => self::class . '::isURLWithHTTPScheme'],
        'b', 'bdi', 'bdo', 'br', 'cite', 'i', 'p', 'rp', 'rt', 'ruby', 'sub', 'sup', 'time' => 'datetime', 'u', 'wbr',
    ];
    
    /** @var bool */
    protected $source;
    
    /** @var string[] */
    protected $filenames = [];
    
    /**
     * 与えられた文字列が絶対URL、または素片付き絶対URL、かつスキームが「http」「https」のいずれかであれば真を返します。
     * @param string $value
     * @return bool
     */
    public static function isURLWithHTTPScheme(string $value): bool
    {
        try {
            $url = new URL($value);
        } catch (TypeError $exception) {
            return false;
        }
        return in_array($url->protocol, ['http:', 'https:']);
    }
    
    /**
     * @param bool $source image-source、audio-source、video-sourceフィールドの矯正であれば真を指定。
     * @param string[] $filenames src属性の検証に使用するファイル名のリスト。
     */
    public function __construct(bool $source = false, $filenames = [])
    {
        parent::__construct();
        
        $this->source = $source;
        $this->filenames = $filenames;
    }
    
    /** @var string[] フィルタリング時のログメッセージ。 */
    protected $errorMessages = [];
    
    /**
     * @param string $level
     * @param (int|string)[] $message
     * @param array $context
     */
    public function log($level, $message, array $context = [])
    {
        $this->errorMessages[] = $message;
    }
    
    /**
     * HTMLフィルターを生成します。
     * @return HTMLFilter
     */
    protected function createHTMLFilter(): HTMLFilter
    {
        $filter = new HTMLFilter(
            $this->source ? static::SOURCE_WHITELIST : static::DESCRIPTION_WHITELIST,
            ['before' => $this->source ? null : function (\DOMElement $body): void {
                foreach (['img', 'audio', 'video'] as $elementName) {
                    foreach ($body->getElementsByTagName($elementName) as $element) {
                        if ($element->hasAttribute('src')) {
                            $fileLocationValidator = new \esperecyan\dictionary_php\validator\FileLocationValidator(
                                $elementName === 'img' ? 'image' : $elementName,
                                $this->filenames
                            );
                            $fileLocationValidator->setLogger($this);
                            $element
                                ->setAttribute('src', $fileLocationValidator->correct($element->getAttribute('src')));
                        }
                    }
                }
            }]
        );
        $filter->setLogger($this);
        return $filter;
    }
    
    /**
     * CommonMark を HTML に変換します。
     * @param string $lightweightMarkup
     * @return string
     */
    protected function convertToHTML(string $lightweightMarkup): string
    {
        return (new \League\CommonMark\CommonMarkConverter())->convertToHtml($lightweightMarkup);
    }
    
    /**
     * HTML を CommonMark に変換します。
     * @param string $html
     * @return string
     */
    protected function convertFromHTML(string $html): string
    {
        return (new \League\HTMLToMarkdown\HtmlConverter())->convert($html);
    }
    
    public function correct(string $input): string
    {
        $filter = $this->createHTMLFilter();
        
        $filtered = $filter->filter($this->convertToHTML($input));
        if ($this->errorMessages) {
            $this->logger->error(sprintf(
                _('「%s」に次のエラーが出ています:'),
                mb_strlen($input) > self::FIELD_ELLIPSIS_LENGTH
                    ? mb_substr($input, 0, self::FIELD_ELLIPSIS_LENGTH) . '…'
                    : $input
            ) . array_reduce($this->errorMessages, function ($errors, $error) {
                return $errors .= "\n• $error";
            }, ''));
            $this->errorMessages = [];
            
            // CommonMarkに変換
            $output = $this->convertFromHTML($filtered);
            
            // 再チェック
            $filtered = $filter->filter($this->convertToHTML($output));
            if ($this->errorMessages) {
                $output = $this->convertFromHTML($filtered);
            }
            
            if (trim($output) === '') {
                $output = '';
            }
        } else {
            $output = $input;
        }
        return $output;
    }
}
