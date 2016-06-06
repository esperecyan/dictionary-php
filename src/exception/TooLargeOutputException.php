<?php
namespace esperecyan\dictionary_php\exception;

class TooLargeOutputException extends \OverflowException implements SerializeExceptionInterface
{
}
