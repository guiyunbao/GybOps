<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 生成结果不符合正文提示词的变量契约。
 */
final class ContentPromptContractException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('invalid_prompt_contract');
    }
}
